<?php
define("TEST_DAEMON_SKIP", true);
require_once dirname(__DIR__).'/include/init.inc.php';
$run = @trim(@$argv[1]);
if($run == "run") {
    Test::run();
} else {
    Test::runDaemon();
}

