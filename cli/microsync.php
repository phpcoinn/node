<?php

$ip = trim($argv[1]);


//TODO: prevent if sync is running
require_once dirname(__DIR__).'/include/init.inc.php';

_log("Call microsync args = ".json_encode($argv));

if (!empty($ip)) {
	do {
		_log("Microsync: Find peer by ip = $ip", 3);
		// the microsync runs only against 1 specific peer
		$x = Peer::findByIp($ip);
		$current = Block::current();

		if (!$x) {
			echo "Invalid node - $ip\n";
			_log("Microsync: Invalid node $ip");
			break;
		}
		_log("Microsync: Get block ".$current['height']." from peer ".$x['hostname'],3);
		$url = $x['hostname']."/peer.php?q=";
		$data = peer_post($url."getBlock", ["height" => $current['height']]);

		if (!$data) {
			echo "Invalid getBlock result\n";
			_log("Microsync: Invalid getBlock result");
			break;
		}
		$data['id'] = san($data['id']);
		$data['height'] = san($data['height']);
		// nothing to be done, same blockchain
		if ($data['id'] == $current['id']) {
			echo "Same block\n";
			_log("Microsync: nothing to be done, same blockchain",2);
			break;
		}

		// delete the last block
		Block::pop(1);

		// add the new block
		echo "Starting to sync last block from {$x['hostname']}\n";
		_log("Microsync: Starting to sync last block from {$x['hostname']}");
		$b = $data;
		$prev = Block::current();
		$block = Block::getFromArray($b);
		$block->prevBlockId = $prev['id'];
		$res = $block->check();
		if (!$res) {
			_log("Microsync: block check failed - {$b['id']} - {$b['height']}");
			break;
		}
		$res = $block->add();
		if (!$res) {
			_log("Microsync: could not add block - {$b['id']} - {$b['height']}");
			break;
		}

		_log("Microsync: Synced block from ".$x['hostname']." - {$b['height']} {$b['difficulty']}", 2);
	} while (0);
}
