<?php
//Sync::checkAndRun();
//Masternode::checkAndRun();
//Dapps::checkAndRun();
//NodeMiner::checkAndRun();
//Cron::runTask();

if(!defined("CRON")) {
    Nodeutil::runAtInterval("check-cron", 60, function () {
        Util::checkCron();
    });
}
