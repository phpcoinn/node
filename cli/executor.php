<?php
declare(ticks = 1);
define("EXECUTOR_SKIP", true);
define("CLI_UTIL", isset($_SERVER['CLI_UTIL']) ? $_SERVER['CLI_UTIL'] : 1);
require_once dirname(__DIR__).'/include/init.inc.php';


$lock_file = ROOT."/tmp/executor-lock";
$ignore_lock = in_array("--ignore-lock", $argv);
if (!@mkdir($lock_file, 0777) && !$ignore_lock) {
    _log("Executor: lock file in place - exit", 3);
    exit;
}

register_shutdown_function(function () use ($lock_file) {
    $error = error_get_last();
    if ($error['type'] === E_ERROR) {
        _log("Executor: Error in execution ". $error['message']);
    }
    _log("Executor: exit function" , 3);
    @rmdir($lock_file);
});

pcntl_signal(SIGINT, function() {
    _log("Executor: Caught SIGINT" , 3);
    exit;
});
pcntl_signal(SIGTERM, function() {
    _log("Executor: Caught SIGTERM", 3);
    exit;
});

$control_file_md5 = md5_file(__FILE__);
$control_file_time = filemtime(__FILE__);
_log("Executor: control_file md5=$control_file_md5 time=$control_file_time", 5);

$running = true;
$started_time = time();
$max_run_time = 5*60;

_log("Executor:start");
$cnt = 0;

$last_check_time_60 = null;
$last_check_time_dapps = null;
$last_check_time_miner = null;
$last_check_time_sync = null;
$last_check_time_masternode = null;
$last_check_time_cron = null;

while($running) {
    sleep(10);
    $cnt++;
    _log("Executor: loop $cnt");


    if(time()-$last_check_time_60 > 60) {
        _log("Executor: execute at 30 sec");
        $running = file_exists($lock_file);
        _log("Executor: running = $running lock_file=$lock_file");
        if(!$running) {
            _log("Executor: Not exists lock file - exit" , 3);
            break;
        }

        clearstatcache(__FILE__);
        $control_file_md5_check = md5_file(__FILE__);
        $control_file_time_check = filemtime(__FILE__);
        _log("Executor: control_file start md5=$control_file_md5 time=$control_file_time", 5);
        _log("Executor: control_file check md5=$control_file_md5_check time=$control_file_time_check", 5);


        if($control_file_md5 != $control_file_md5_check) {
            _log("Executor: Control file md5 check - unlock", 5);
            break;
        } else if($control_file_time != $control_file_time_check) {
            _log("Executor: Control file time check - unlock", 5);
            break;
        }

        if(time() - $started_time > $max_run_time) {
            _log("Executor: Exceeded max run time - exit", 1);
            break;
        }

        $last_check_time_60 = time();
    }

    if(time()-$last_check_time_dapps > 30) {
        Nodeutil::runSingleProcess("php ".ROOT."/cli/dapps.php run");
        $last_check_time_dapps = time();
    }

    if(time()-$last_check_time_miner > 30) {
        Nodeutil::runSingleProcess("php ".ROOT."/cli/miner.php run");
        $last_check_time_miner = time();
    }

    if(time()-$last_check_time_sync > 30) {
        Nodeutil::runSingleProcess("php ".ROOT."/cli/sync.php run");
        $last_check_time_sync = time();
    }

    if(time()-$last_check_time_masternode > 30) {
        Nodeutil::runSingleProcess("php ".ROOT."/cli/masternode.php run");
        $last_check_time_masternode = time();
    }

    if(time()-$last_check_time_cron > 60) {
        Nodeutil::runSingleProcess("php ".ROOT."/cli/cron.php run");
        $last_check_time_cron = time();
    }


}

@rmdir($lock_file);

_log("Executor:end");
