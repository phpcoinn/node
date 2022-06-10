<?php
define("DAPPS_DAEMON_SKIP", true);
require_once dirname(__DIR__).'/include/init.inc.php';
Dapps::runDaemon();

