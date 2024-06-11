<?php
//Sync::checkAndRun();
//Masternode::checkAndRun();
//Dapps::checkAndRun();
//NodeMiner::checkAndRun();
//Cron::runTask();

Nodeutil::runAtInterval("task-cron-new", 60, function () {
    Util::checkCron();
});
