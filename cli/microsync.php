<?php

$ip = trim($argv[1]);


//TODO: prevent if sync is running
require_once dirname(__DIR__).'/include/init.inc.php';

if (!empty($ip)) {

	if (Config::isSync()) {
		_log('['.$ip."] Block rejected due to sync", 5);
		api_err("sync");
	}

	Config::setSync(1);


	do {
		_log("Microsync: Find peer by ip = $ip", 3);
		// the microsync runs only against 1 specific peer
		$peer = Peer::findByIp($ip);
		_log("Microsync: peer=".json_encode($peer));
		$current = Block::current();

		if (!$peer) {
			_log("Microsync: Invalid peer for ip=$ip");
			break;
		}
		$url = $peer['hostname']."/peer.php?q=";
		_log("Microsync: Read block ".$current['height']." from peer ".$peer['hostname'],3);
		$data = peer_post($url."getBlock", ["height" => $current['height']], 30, $err);
		_log("Microsync: res=".json_encode($data),3);

		if (!$data) {
			_log("Microsync: Invalid getBlock res = ".json_encode($data)." err=$err");
			break;
		}
		if ($data['id'] == $current['id']) {
			_log("Microsync: nothing to be done, same blockchain",2);
			break;
		}

		_log("Microsync: pop invalid block");

		// delete the last block
		Block::pop(1);

		// add the new block
		_log("Microsync: Starting to sync last block from {$peer['hostname']}");
		$b = $data;
		$prev = Block::current();
		$block = Block::getFromArray($b);
		$block->prevBlockId = $prev['id'];
		$res = $block->check();
		if (!$res) {
			_log("Microsync: block check failed - {$b['id']} - {$b['height']}");
			break;
		}
		$res = $block->add($err);
		if (!$res) {
			_log("Microsync: could not add block - {$b['id']} - {$b['height']} err=$err");
			break;
		}

		_log("Microsync: Synced block from ".$peer['hostname']." - {$b['height']} {$b['difficulty']}", 2);
	} while (0);
	
	Propagate::blockToAll("current");

	Config::setSync(0);
	_log("Microsync: finished");
}
