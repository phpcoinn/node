<?php

define("MASTERNODE_PROCESS", true);

set_time_limit(0);
error_reporting(0);

if (php_sapi_name() !== 'cli') {
	die("This should only be run as cli");
}

$lock_file = dirname(__DIR__)."/tmp/mn-lock";
if (!mkdir($lock_file, 0700)) {
	$pid_time = filemtime($lock_file);

	// If the process died, restart after 1 hour
	if (time() - $pid_time > 60 * 60) {
		@rmdir($lock_file);
	}

	echo "Lock file in place".PHP_EOL;
	exit;
}

register_shutdown_function(function () {
	$lock_file = dirname(__DIR__)."/tmp/mn-lock";
	@rmdir($lock_file);
});

global $_config, $db;
require_once dirname(__DIR__).'/include/init.inc.php';

_log("Masternode: start process",5);

$height = Block::getHeight();
if(Masternode::allowedMasternodes($height)) {
	echo "Masternode feature not enabled";
}

$running = true;
$started_time = time();

Masternode::checkLocalMasternode();

while($running) {

	Masternode::processBlock();
	$running = file_exists($lock_file);
	sleep(30);
	if(time() - $started_time > 60 * 60 * 1) {
		break;
	}
}


@rmdir($lock_file);
_log("Masternode: finished process",5);
