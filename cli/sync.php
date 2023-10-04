<?php
set_time_limit(60*60);
$run = @trim(@$argv[1]);
if ($run == "tick") {
    require_once dirname(__DIR__) . '/include/init.inc.php';
    Sync::tick();
    return;
}

define("SYNC_DAEMON_SKIP", true);
define("CLI_UTIL", isset($_SERVER['CLI_UTIL']) ? $_SERVER['CLI_UTIL'] : 1);
require_once dirname(__DIR__).'/include/init.inc.php';
Sync::runDaemon();
