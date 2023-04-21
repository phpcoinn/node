<?php
_log("Daemon: check daemons", 5);
Dapps::checkDaemon();
NodeMiner::checkDaemon();
Sync::checkDaemon();
Masternode::checkDaemon();
Cron::checkDaemon();
