<?php

if(false) {
    return;
}

$lockFile = ROOT . '/tmp/tick.lock';

$lockFileHandle = fopen($lockFile, 'a+');

if ($lockFileHandle === false || !flock($lockFileHandle, LOCK_EX | LOCK_NB)) {
    if ($lockFileHandle) {
        fclose($lockFileHandle);
    }
    return;
}


$timestamp = (int)@file_get_contents($lockFile);
$now = time();
$diff = $now - $timestamp;

if (empty($timestamp) || ($diff >= 10)) {

    _log("Tick: [".php_sapi_name()."] execute tick calls diff=$diff");


    if(Cron::isEnabled()) {
        shell_exec("php ".ROOT."/cli/cron.php tick > /dev/null 2>&1 &");
    }
    if(Sync::isEnabled()) {
        shell_exec("php ".ROOT."/cli/sync.php tick > /dev/null 2>&1 &");
    }
    if(Dapps::isEnabled()) {
        shell_exec("php ".ROOT."/cli/dapps.php tick > /dev/null 2>&1 &");
    }
    if(Masternode::isEnabled()) {
        shell_exec("php ".ROOT."/cli/masternode.php tick > /dev/null 2>&1 &");
    }


    shell_exec("php ".ROOT."/cli/tick.php > /dev/null 2>&1 &");

    ftruncate($lockFileHandle, 0);
    fwrite($lockFileHandle, time());
}

flock($lockFileHandle, LOCK_UN);
fclose($lockFileHandle);

