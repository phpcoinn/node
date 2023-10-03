<?php

class Cron extends Daemon
{
    static $name = "cron";
    static $title = "Cron";

    static $max_run_time = 60 * 60;
    static $run_interval = 60;

    static function isEnabled() {
        return true;
    }

    static function process() {
        Nodeutil::runSingleProcess("php ".ROOT."/cli/cron.php run");
    }

    static function isDbNeeded() {
        return true;
    }

    static function run() {
        $time = date("H:i");
        _log("CRON: Run at time: " .$time, 2);
        $hour = intval(date("H"));
        $min = intval(date("i"));

        if($min % 5 == 0 && !DEVELOPMENT) {
            Nodeutil::runSingleProcess("php ".ROOT."/cli/util.php update");
            Sync::checkLongRunning();
            Dapps::checkLongRunning();
            NodeMiner::checkLongRunning();
            Masternode::checkLongRunning();
            Cache::resetCache();
            Peer::deleteBlacklisted();
            Peer::deleteWrongHostnames();
            Dapps::createDir();
            $mnCount = Masternode::getCount();
        }

        if($hour == 2 && $min == 30) {
            Nodeutil::runSingleProcess("php ".ROOT."/cli/util.php check-accounts");
        }
        if($min == 15) {
            Nodeutil::runSingleProcess("php ".ROOT."/cli/util.php recalculate-masternodes");
        }
        if($min % 60 == 0) {
            Nodeutil::clearOldMiningStat();
            Nodeutil::runSingleProcess("php ".ROOT."/cli/util.php get-more-peers");
        }
        if($hour == 3 && $min == 0) {
            Nodeutil::resetMiningStats();
        }
    }
}
