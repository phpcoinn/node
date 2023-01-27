<?php
require_once dirname(__DIR__).'/include/init.inc.php';

_log("Sync: start");

function getPeerBlocksMap() {
	$peers = Peer::getPeersForSync();
	_log("Sync: Found ".count($peers)." to sync");
	$blocksMap = [];
	foreach ($peers as $peer) {
		$height = $peer['height'];
		$block_id = $peer['block_id'];
		$hostname = $peer['hostname'];
//		_log("Sync: get peer $hostname height=$height block_id=$block_id");
		$blocksMap[$height][$block_id][$hostname]=$peer;
	}
	ksort($blocksMap);
	return $blocksMap;
}




//first resolve forked blocks
$blocksMap = getPeerBlocksMap();
//_log("blocksMap: ".print_r(array_keys($blocksMap), 1));
foreach ($blocksMap as $height=>$blocks) {
	$diff_blocks = count($blocks);
	$peerInfo = Peer::getInfo();
	if(count($blocks)>1) {
		_log("Found ".count($blocks)." forked blocks at height $height");
		$forked_blocks = [];
		foreach ($blocks as $block_id => $hostnames) {
			$hostname_count = count($hostnames);
			_log(" Found ".count($hostnames)." peers with height=$height and FORKED block_id=$block_id");
			$peers_limit = 5;
			$peers_cnt=0;
			foreach($hostnames as $hostname => $peer) {
				$peers_cnt++;
				if($peers_cnt > $peers_limit) {
					break;
				}
				_log("Contacting peer $hostname about block $block_id");
				$url = $hostname . "/peer.php?q=getBlock";
				$peer_block = peer_post($url, ["height" => $height], 5, $err, $peerInfo );
//				_log("Peer response = ".json_encode($peer_block));
				if($peer_block !== false) {
					if($peer_block['id'] == $block_id) {
						_log("Returned block ".$peer_block['id']." is still forked - add to list");
						$forked_blocks[$block_id]=$peer_block;
						break;
					} else {
						_log("Returned block ".$peer_block['id']." is NO more forked");
					}
				}
			}
		}
		if(count($forked_blocks)>1) {
			_log("Forked blocks for height $height:");
			foreach($forked_blocks as $block_id => $block) {
				_log("   block_id=$block_id elapsed=".$block['elapsed']." date=".$block['date']. " difficulty=".$block['difficulty']. " tx_cnt=".count($block['data']));
			}
			uasort($forked_blocks, function ($b1, $b2) {
				if($b1['elapsed'] == $b2['elapsed']) {
					if($b1['date'] == $b2['date']) {
						return strcmp($b1['id'], $b2['id']);
					} else {
						return $b1['date'] - $b2['date'];
					}
				} else {
					return $b1['elapsed'] - $b2['elapsed'];
				}
			});
			$forked_block_winner = array_shift($forked_blocks);
			$our_block = Block::get($height);
			_log("forked_block_winner=".$forked_block_winner['id']." our block_id=".$our_block['id']);
			foreach($forked_blocks as $block_id => $block) {
				$forked_hostnames = $blocksMap[$height][$block_id];
				foreach ($forked_hostnames as $forked_hostname => $peer) {
					_log("forked hostnames loser for block $block_id = " .$forked_hostname);
					Peer::blacklist($peer['id'], 'Forked block at height '.$height);
					if($our_block['id'] == $forked_block_winner['id']) {
						Propagate::blockToPeer($peer['hostname'], $peer['ip'], $forked_block_winner['id']);
					}
				}
			}
			if(isset($our_block['id']) && $our_block['id']!=$forked_block_winner['id']) {
				_log("Our block is forked - remove it");
				Block::delete($height);
				exit;
			}
		}
	}
}

//exit;

function getPeerBlock($host, $height) {
	static $peerBlocks;
	if(isset($peerBlocks[$host][$height])) {
		return $peerBlocks[$host][$height];
	} else {
		$limit = 10;
		$url = $host."/peer.php?q=";
		_log("Reading blocks from $height from peer $host", 3);
		$peer_blocks = peer_post($url."getBlocks", ["height" => $height - $limit], 5);
		if ($peer_blocks === false) {
			_log("Could not get block from $host - " . $height, 3);
		} else {
			if(is_array($peer_blocks)) {
				foreach($peer_blocks as $peer_block) {
					$peerBlocks[$host][$peer_block['height']]=$peer_block;
				}
			}
		}
		return $peerBlocks[$host][$height];
	}
}

$blocksMap = getPeerBlocksMap();
$best_height = 0;

foreach ($blocksMap as $height=>$blocks) {
	$diff_blocks = count($blocks);
	$peerInfo = Peer::getInfo();
//	_log("Checking height=$height diff_blocks=".count($blocks));
	if(count($blocks)>1) {
		_log("There are still forks at height $height");

		break;
	} else {
		foreach ($blocks as $block_id => $hostnames) {
			$hostname_count = count($hostnames);
			_log(" Found ".count($hostnames)." peers with height=$height and block_id=$block_id");
			if(count($hostnames) > 1) {
				$best_height = $height;
			}
		}
	}
}

$current_height = Block::getHeight();
_log("Get best height = $best_height our height=$current_height");

if($current_height == $best_height) {
	_log("Blockchain is synced");
} else if ($best_height < $current_height) {
	_log("We are ahead of peers");
} else {
	_log("Need to sync blokchain");
	$peerInfo = Peer::getInfo();
	foreach ($blocksMap[$best_height] as $block_id => $hostnames) {
		_log("Sync height $best_height");

		$syncing = true;
		$limit_cnt = 0;
		while($syncing) {
			$limit_cnt++;
			if($limit_cnt > 10) {
				break;
			}
			$current = Block::current();
			$syncing = (($current['height'] < $best_height && $best_height > 1)
				|| ($current['height'] == $best_height && $current['id'] != $block_id));

			if (!$syncing) {
				break;
			}

			_log("Syncing current_height= ".$current['height']." best_height=$best_height current_id=".$current['id']." block_id=$block_id");
			$limit_peers = 10;
			$peers_cnt = 0;
			foreach($hostnames as $hostname => $peer) {
				$peers_cnt++;
				if($peers_cnt > $limit_peers) {
					$syncing = false;
					break 2;
				}
				_log("Get block ".$current['height']." from peer $peers_cnt/$limit_peers $hostname");
				$peer_block = getPeerBlock($hostname, $current['height']);
//				_log("get peer block $hostname res=".json_encode($peer_block));
				if(!$peer_block) {
					_log("Not get block for peer - check other peer");
					continue;
				}
				if ($peer_block['id'] != $current['id']) {
					_log("Invalid block get from peer");
					$current = Block::export($current['id']);
					$res = NodeSync::compareBlocks($current, $peer_block);
					if($res>0) {
						_log("PeerCheck: my block is winner");
					} else if ($res<0) {
						_log("PeerCheck: other block is winner");
					} else {
						_log("PeerCheck: blocks are actually same");
					}
				}
				_log("We got ok block - go to next");
				$next_block = getPeerBlock($hostname, $current['height']+1);
				if(!$next_block) {
					_log("Not get next block for peer - check other peer");
					continue;
				}
				$block = Block::getFromArray($next_block);
				if (!$block->check()) {
					_log("Next block check failed");
					continue;
				}
				_log("Block check ok");
				$block->prevBlockId = $current['id'];
				$res = $block->add($err);
				if(!$res) {
					_log("Error adding block: $err");
					continue;
				}
				_log("Block added");
				$res=$block->verifyBlock($err);
				if(!$res) {
					_log("Error verify block: $err");
					$syncing = false;
					break 2;
				}
				_log("Block verified");
				break;
			}


		}


/*		foreach($hostnames as $hostname => $peer) {
			_log(" Start syncing with hostname $hostname");
			$url = $hostname . "/peer.php?q=getBlocks";
			$peer_blocks = peer_post($url, ["height" => $current_height], 30, $err, $peerInfo);
			if($peer_blocks == false) {
				_log(" No response from peer $hostname - go next");
				continue;
			}
//			_log("Got response = ".print_r($peer_blocks, 1));
			foreach($peer_blocks as $peer_block) {
				_log("Sync peer block ".$peer_block['height']);
				$block = Block::getFromArray($peer_block);
				if (!$block->check()) {
					_log("Error checking block");
					continue;
				}
				_log("Block checked ok");
				$prev_block = Block::getAtHeight($block->height - 1);
				$prev_block_id = $prev_block['id'];
				$block->prevBlockId = $prev_block_id;
				$res = $block->add();
				if(!$res) {
					_log("Block not added");
					continue;
				}
				_log("Block added ok");
			}
			break;
		}*/
	}
}


_log("Sync: end");

