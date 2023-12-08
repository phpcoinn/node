<?php

class Test extends Daemon
{

    static $name = "test";
    static $title = "Test";

    static $max_run_time = 60 * 60;
    static $run_interval = 60;

    static function isEnabled() {
        return true;
    }

    static function process() {
        Nodeutil::runSingleProcess("php ".ROOT."/cli/test.php run");
    }

    static function run() {
        _log("Daemon: test: start");
        $t1=time();
        sleep(70);
        $t2=time();
        _log("Daemon: test: finished after ".($t2-$t1));
    }

}
