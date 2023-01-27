<?php
require_once dirname(__DIR__).'/include/init.inc.php';

_log("Sync: start");

NodeSync::checkForkedBlocks();

NodeSync::syncBlocks();

_log("Sync: end");

