<?php
define("SYNC_DAEMON_SKIP", true);
define("CLI_UTIL", isset($_SERVER['CLI_UTIL']) ? $_SERVER['CLI_UTIL'] : 1);
require_once dirname(__DIR__).'/include/init.inc.php';
$run = @trim(@$argv[1]);
if($run == "run") {
    Sync::checkAndRunDaemon();
} else {
    Sync::processArgs();
}
