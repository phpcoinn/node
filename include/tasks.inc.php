<?php
Sync::checkAndRun();
Masternode::checkAndRun();
Dapps::checkAndRun();
NodeMiner::checkAndRun();
Cron::runTask();
