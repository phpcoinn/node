<?php
define("CRON_DAEMON_SKIP", true);
define("CLI_UTIL", isset($_SERVER['CLI_UTIL']) ? $_SERVER['CLI_UTIL'] : 1);
require_once dirname(__DIR__).'/include/init.inc.php';
$run = @trim(@$argv[1]);
_log("CRON run");
if($run == "run") {
    Cron::checkAndRunDaemon();
} else {
    Cron::processArgs();
}

