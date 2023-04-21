<?php
$run = @trim(@$argv[1]);
if($run == "run") {
    require_once dirname(__DIR__).'/include/init.inc.php';
    Cron::run();
} else {
    define("CRON_DAEMON_SKIP", true);
    define("CLI_UTIL", isset($_SERVER['CLI_UTIL']) ? $_SERVER['CLI_UTIL'] : 1);
    require_once dirname(__DIR__).'/include/init.inc.php';
    Cron::runDaemon();
}

