<?php

class Cron extends Daemon
{
    static $name = "cron";
    static $title = "Cron";

    static $max_run_time = 60 * 60;
    static $run_interval = 30;

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
        _log("CRON: Run at time ".$time, 4);
        $hour = intval(date("H"));
        $min = intval(date("i"));

        if($min % 5 == 0) {
            //Nodeutil::runSingleProcess("php ".ROOT."/cli/util.php update auto_update");
        }

    }
}
