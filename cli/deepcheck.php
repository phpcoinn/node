<?php
require_once dirname(__DIR__).'/include/init.inc.php';

$hostname = $argv[1];
if(empty($hostname)) {
	_log("PeerSync: Empty hostname");
	exit;
}


$height1 = Block::getHeight();
$url = $hostname."/peer.php?q=currentBlock";
$peer_block = peer_post($url);
$start_height = min($height1, $peer_block['block']['height']);

$min_height = 1;
$max_height = $start_height;

$height=$start_height;
_log("PeerCheck: start check blocks with $hostname from height $height");
$peer = Peer::findByHostname($hostname);
while(true) {
	_log("PeerCheck: check height $height");
	$block = Block::export("", $height);
	if(!$block) {
		break;
	}

	$url = $hostname."/peer.php?q=getBlock";
	$peer_block = peer_post($url, ["height"=>$height]);

	if ($peer_block) {
		$blocks_ok = $peer_block['id'] == $block['id'];
		if ($blocks_ok) {
			_log("PeerCheck: we have same block");
			$min_height = $height;
		} else {
			_log("PeerCheck: we DO NOT HAVE SAME BLOCK");
			$invalid_height = $height;
			$max_height = $height;
		}
		if(abs($max_height - $min_height) <=1 ) {
			break;
		}
		$height =intval(($max_height - $min_height) / 2 + $min_height);
	} else {
		_log("PeerCheck: no block from peer");
		Peer::blacklist($peer['id'], "Unresponsive");
		exit;
	}
}


if(empty($invalid_height)) {
	_log("No invalid height");
	exit;
}
_log("Invalid height = $invalid_height");

$block = Block::export("",$invalid_height);
$url = $hostname."/peer.php?q=getBlock";
$peer_block = peer_post($url, ["height"=>$invalid_height]);

_log("my block: ".print_r($block,1));
_log("peer block: ".print_r($peer_block,1));

$res = NodeSync::compareBlocks($block, $peer_block);
$winner = null;
if($res>0) {
	_log("My block is winner");
	$my_winner = true;
	$winner = $block;
	Peer::blacklist($peer['id'], "Invalid block $height");
	$url = $peer['hostname'] . "/peer.php?q=deepCheck";
	$res = peer_post($url, [], 5, $err );
	_log("Requested deep check res=".json_encode($res));
} else if ($res<0) {
	_log("Other block is winner");
	$my_winner = false;
	$winner = $peer_block;
	$height = Block::getHeight();
	$diff = $height - $invalid_height;
	$delete_height = $invalid_height;
	if($diff > 100) {
		_log("Delete only last 100 blocks");
		$delete_height = $height - 100;
	}
	_log("Delete up to height $delete_height");
	Block::delete($delete_height);
} else {
	_log("Blocks are actually same");
}

_log("PeerCheck: end");
