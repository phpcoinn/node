<?php
//Local mode miner
define("MINER_RUN", true);
global $_config;
if(php_sapi_name() !== 'cli') exit;
require_once dirname(__DIR__).'/include/init.inc.php';
$block = new Block();
$acc = new Account();

define("MINER_LOCK_PATH", ROOT.'/tmp/miner-lock');
_log("Running miner cli", 0);
// make sure there's only a single miner process running at the same time
if (file_exists(MINER_LOCK_PATH)) {
	_log("Miner lock in place: " .MINER_LOCK_PATH);
	die("Miner lock in place".PHP_EOL);
}

$peers = Peer::getCount(true);
if(empty($peers)) {
	_log("No peers for miner");
	exit;
}


_log("Starting miner");


if(!$_config['miner']) {
	_log("Miner not enabled");
	exit;
}

if(!$_config['miner_public_key']) {
	_log("Miner public key not defined");
	exit;
}
if(!$_config['miner_private_key']) {
	_log("Miner private key not defined");
	exit;
}

$peers = Peer::getCount(true);
if($peers==0) {
	_log("Can not find any peers to mine", 1);
	return false;
}

_log("Writing lock file", 0);
$lock = fopen(MINER_LOCK_PATH, "w");
fclose($lock);

$miner = new Miner($_config['miner_public_key'], $_config['miner_private_key'], $_config['hostname']);
$miner->miningPeerFn = function($m) {
	global $db, $block;

	if(empty($m->node)) {
		$peers = Peer::getActive();
	} else {
		$peers = [['hostname'=>$m->node]];
	}

	foreach ($peers as $peer) {
		$peerHostname=$peer['hostname'];
		_log("Contacting peer ".$peerHostname, 4);
		$info = file_get_contents($peerHostname."/mine.php?q=info&".XDEBUG);
		if(!$info) {
			_log("Error contacting peer $peerHostname");
		} else {
			break;
		}
	}
	$m->node = $peerHostname;
	if(!$info) {
		_log("Can not retrieve mining info");
		return false;
	}

	$info = json_decode($info, true);
	if ($info['status'] != "ok") {
		_log("Wrong status for node:".json_encode($info));
		return false;
	}

	$current = $block->current();
	if($current['height'] < $info['data']['height']) {
		_log("Current node not synced");
		return false;
	}
	return $info;
};
$miner->checkRunningFn = function(){
	$minerRunning = file_exists(MINER_LOCK_PATH);
	return $minerRunning;
};

$res = $miner->start();
if($res === false) {
	_log("Miner failed to start",0);
	@unlink(MINER_LOCK_PATH);
	exit;
}

@unlink(MINER_LOCK_PATH);
_log("Miner stopped", 0);
