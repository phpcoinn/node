<?php
require_once dirname(__DIR__).'/include/init.inc.php';

$hostname = $argv[1];
if(empty($hostname)) {
	_log("PeerSync: Empty hostname");
	exit;
}

$height=$argv[2];
if(empty($height)) {
	_log("PeerCheck: Empty height");
	exit;
}

_log("PeerCheck: start check blocks with $hostname from height $height");
$peer = Peer::findByHostname($hostname);
while(true) {
	_log("PeerCheck: check height $height");
	$block = Block::getAtHeight($height);
	if(!$block) {
		break;
	}
	$peer_block_data = NodeSync::staticGetPeerBlock($hostname, $height);
	if ($peer_block_data) {
		if ($peer_block_data['id'] == $block['id']) {
			_log("PeerCheck: we have same block");
			$height++;
		} else {
			_log("PeerCheck: we DO NOT HAVE SAME BLOCK");
			$block = Block::export($block['id']);
			$res = NodeSync::compareBlocks($block, $peer_block_data);
			if($res>0) {
				_log("PeerCheck: my block is winner");
				if($peer) {
					_log("PeerCheck: send my block to peer");
					Propagate::blockToPeer($peer['hostname'], $peer['ip'], $block['id']);
				}
			} else if ($res<0) {
				_log("PeerCheck: other block is winner");
				Block::delete($height);
			} else {
				_log("PeerCheck: blocks are actually same");
			}
			break;
		}
	} else {
		_log("PeerCheck: no block from peer");
		break;
	}
}

_log("PeerCheck: end");
