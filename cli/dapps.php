<?php
define("DAPPS_DAEMON_SKIP", true);
define("CLI_UTIL", 1);
require_once dirname(__DIR__).'/include/init.inc.php';
Dapps::runDaemon();

