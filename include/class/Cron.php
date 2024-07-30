<?php

class Cron extends Task
{
    static $name = "cron";
    static $title = "Cron";

    static $run_interval = 60;

    static function isEnabled() {
        return true;
    }

    static function canDisable() {
        return false;
    }

    static function process() {
        $time = date("H:i");
        $hour = intval(date("H"));
        $min = intval(date("i"));
        _log("Cron: process $time");

        self::processTasks();

        if($min % 5 == 0 && !DEVELOPMENT) {

            try_catch(function () {
                Nodeutil::runSingleProcess("php ".ROOT."/cli/util.php update");
                Cache::resetCache();
                Peer::deleteBlacklisted();
                Peer::deleteWrongHostnames();
                Dapps::createDir();
                $mnCount = Masternode::getCount();
            });

        }

        if($hour == 2 && $min == 30) {
            Nodeutil::runSingleProcess("php ".ROOT."/cli/util.php check-accounts");
        }
        if($min == 15) {
            Nodeutil::runSingleProcess("php ".ROOT."/cli/util.php recalculate-masternodes");
        }
        if($min % 60 == 0) {
            try_catch(function() {
                Nodeutil::clearOldMiningStat();
            });
            Nodeutil::runSingleProcess("php ".ROOT."/cli/util.php get-more-peers");
        }
        if($hour == 3 && $min == 0) {
            Nodeutil::resetMiningStats();
        }
        if($min % 60 == 0) {
            Nodeutil::checkStuckMempool();
            global $_config;
            $hostname = $_config['initial_peer_list'][0];
            Nodeutil::runSingleProcess("php ".ROOT."/cli/peersync.php $hostname");
        }

        Sync::checkLongRunning();

        _log("CRON: Run at time: " .$time, 2);
    }

    static function processTasks() {
        Util::checkCron();
        Sync::checkAndRun();
        Masternode::checkAndRun();
        Dapps::checkAndRun();
        NodeMiner::checkAndRun();
    }
}
