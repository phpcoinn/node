<?php
//Sync::checkAndRun();
//Masternode::checkAndRun();
//Dapps::checkAndRun();
//NodeMiner::checkAndRun();
//Cron::runTask();

_log("tasks cron=".defined("CRON"));
if(!defined("CRON")) {
    Nodeutil::runAtInterval("check-cron", 60, function () {
        _log("check-cron " . $_SERVER['SCRIPT_NAME']);
        Util::checkCron();
    });
}
