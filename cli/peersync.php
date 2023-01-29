<?php
require_once dirname(__DIR__).'/include/init.inc.php';

$hostname = $argv[1];
if(empty($hostname)) {
	_log("PeerSync: Empty hostname");
	exit;
}

_log("PeerSync: start sync with $hostname");

$syncing = true;
while($syncing) {
	$current = Block::current();
	$height = $current['height'] + 1;
	_log("PeerSync: our height = $height");
	$peer_block_data = NodeSync::staticGetPeerBlock($hostname, $height);
	if ($peer_block_data) {
		$peer_block = Block::getFromArray($peer_block_data);
		$res = $peer_block->check($err);
		if (!$res) {
			_log("PeerSync: Peer block check failed: $err");
			$syncing = false;
			break;
		}
		_log("PeerSync: Peer block check ok");
		$peer_block->prevBlockId = $current['id'];
		$res = $peer_block->add($err);
		if (!$res) {
			_log("PeerSync: Peer block add failed: $err");
			$syncing = false;
			break;
		}
		_log("PeerSync: Peer block added ok");
		$res = $peer_block->verifyBlock($err);
		_log("PeerSync: verifyBlock res=".json_encode($res));
		if (!$res) {
			_log("PeerSync: Error verify block: $err");
			$syncing = false;
			break;
		}
		_log("PeerSync: Block verified");
	} else {
		_log("PeerSync: NO peer block data");
		$syncing = false;
		break;
	}
}

Propagate::blockToAll("current");

_log("PeerSync: end");
