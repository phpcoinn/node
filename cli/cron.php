<?php
define("CRON", true);
require_once dirname(__DIR__).'/include/init.inc.php';
Cron::process();
