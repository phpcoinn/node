<?php
_log("Daemon: check daemons", 5);
Dapps::checkDaemon();
NodeMiner::checkDaemon();
Sync::checkDaemon();
Masternode::checkDaemon();
Cron::checkDaemon();
Test::checkDaemon();

//$executor_enabled = true;
//if(!$executor_enabled) {
//    _log("Executor: not enabled", 5);
//    return;
//}
//$lock_file = ROOT."/tmp/executor-lock";
//$max_run_time = 10*60;
//if (!file_exists($lock_file)) {
//    _log("Executor: lock file not exists - check process", 5);
//    $cmd = "ps uax | grep '".ROOT."/cli/executor.php' | grep -v grep";
//    $res = shell_exec($cmd);
//    if(empty($res)) {
//        _log("Executor: process not exists - start it");
//        $dir = ROOT . "/cli";
//        $cmd = "php $dir/executor.php > /dev/null 2>&1  &";
//        _log("Executor: Start executor daemon: $cmd");
//        system($cmd);
//    } else {
//        _log("Executor: process exists without lock!", 3);
//    }
//} else {
//    $pid_time = filemtime($lock_file);
//    $elapsed = time() - $pid_time;
//    if($elapsed > $max_run_time) {
//        _log("Executor: running more than $max_run_time - remove lock", 5);
//        @rmdir($lock_file);
//    }
//}
