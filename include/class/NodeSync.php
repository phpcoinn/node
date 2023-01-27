<?php

class NodeSync
{

	public $peers;
	public $peerBlocks;

	function __construct($peers)
	{
		$this->peers = $peers;
	}

	public function start($largest_height, $largest_id)
	{

		global $db;
		Config::setSync(1);

		$peers_count = $this->peers==null ? 0 : @count($this->peers);

		$syncing = true;
		$loop_cnt = 0;
		$max_removed = 0;
		while ($syncing) {
			$loop_cnt++;
			if ($loop_cnt > 1000) {
				_log("Exit after added 1000 blocks", 4);
				break;
			}

			if($max_removed > 100) {
				_log("Max removed. break", 4);
				break;
			}

			$current = Block::current();
			$syncing = (($current['height'] < $largest_height && $largest_height > 1)
				|| ($current['height'] == $largest_height && $current['id'] != $largest_id));
			_log("check syncing syncing=$syncing current_height=" . $current['height'] . " largest_height=$largest_height current_id=" .
				$current['id'] . " largest_id=$largest_id", 3);

			if (!$syncing) {
				break;
			}

			$failed_peer = 0;
			$failed_block = 0;
			$ok_block = 0;
			$height = $current['height'];
			$peers_count = 0;
			$min_ok_blocks = count($this->peers)*2/3;
			$min_failed_blocks =count($this->peers)*1/3;
			$good_peers = [];
			foreach ($this->peers as $host) {
				$peers_count++;
				$peer_block = $this->getPeerBlock($host, $height);
				if ($peer_block) {
					_log("peer_block=" . $peer_block['id'] . " current_id=" . $current['id'], 4);
					if ($peer_block['id'] != $current['id']) {
						$failed_block++;
						$failed_peer++;
						_log("We have wrong block $height failed_block=$failed_block");
						if($failed_peer >= $min_failed_blocks) {
							break;
						}
					} else {
						$ok_block++;
						$good_peers[]=$host;
						_log("We have ok block $height ok_block=$ok_block", 4);
						if($ok_block >= $min_ok_blocks) {
							break;
						}
					}
				} else {
					$failed_peer++;
					if($failed_peer >= $min_failed_blocks) {
						break;
					}
				}

			}

			_log("Check peers peers_count=$peers_count ok_block=$ok_block failed_peer=$failed_peer", 2);

			if ($failed_block > 0 && $failed_block > ($peers_count - $failed_peer) / 2) {
				_log("Failed block on blockchain $failed_block - remove");
				$res = Block::pop();
				$max_removed++;
				if (!$res) {
					_log("Can not delete block on height $height");
					$syncing = false;
					break;
				}
			} else if ($ok_block == ($peers_count - $failed_peer)) {
				_log("last block $height is ok - get next", 4);
				$failed_next_peer = 0;
				$next_block_peers = [];
				$height++;

				$min_elapsed = PHP_INT_MAX;
				foreach ($good_peers as $host) {
					$next_block = $this->getPeerBlock($host, $height);
					if (!$next_block) {
						_log("Could not get block from $host - " . $height, 3);
						$failed_next_peer++;
						continue;
					}
					$prev_block = Block::getAtHeight($height - 1);
					$elapsed = $next_block['date'] - $prev_block['date'];
					if ($elapsed < $min_elapsed) {
						$min_elapsed = $elapsed;
					}
					$next_block_peers[$elapsed][$host] = $next_block;
				}


				if (count(array_keys($next_block_peers)) == 0) {
					_log("No next block on peers", 3);
					break;
				} else {
					if (count(array_keys($next_block_peers)) == 1) {
						$id = array_keys($next_block_peers)[0];
						_log("All peers return same block - checking block $height", 4);
						$hosts = array_keys($next_block_peers[$id]);
						$host = $hosts[0];
						$next_block = $next_block_peers[$id][$host];
//				_log("Checking block ". print_r($next_block,1));
						$block = Block::getFromArray($next_block);
						if (!$block->check()
						) {
							_log("Invalid block mined at height " . $height);
							$syncing = false;
							_log("Blacklist node $host");
							$peer = Peer::findByHostname($host);
							Peer::blacklist($peer['id'], 'Invalid block '.$height);
							break;
						} else {
							$prev_block = Block::getAtHeight($next_block['height'] - 1);
							$prev_block_id = $prev_block['id'];
							$block = Block::getFromArray($next_block);
							$block->prevBlockId = $prev_block_id;
							$res = $block->add();
							if (!$res) {
								_log("Block add: could not add block at height $height");
								$syncing = false;
								_log("Blacklist node $host");
								$peer = Peer::findByHostname($host);
								Peer::blacklist($peer['id'], 'Invalid block '.$height);
								break;
							} else {
								$vblock = Block::export("", $height);
								$res = Block::getFromArray($vblock)->verifyBlock();
								if (!$res) {
									_log("Block is not verified");
									$syncing = false;
									_log("Blacklist node $host");
									$peer = Peer::findByHostname($host);
									Peer::blacklist($peer['id'], 'Invalid block '.$height);
									break;
								}
							}
						}
					} else {
						_log("Different blocks on same height $height on peers - skip ", 2);

						$best_block_peers = $next_block_peers[$min_elapsed];
						foreach ($next_block_peers as $elapsed => $nodes) {
							if ($elapsed != $min_elapsed) {
								foreach ($nodes as $node => $b) {
									_log("Blacklist node $node");
									$peer = Peer::findByHostname($node);
									Peer::blacklist($peer['id'], 'Invalid block '.$height);
								}
							}
						}

					}
				}


			} else {
				_log("Not enough valid blocks for sync ok_block=$ok_block peers_count=$peers_count failed_peer=$failed_peer");
				break;
			}

		}

		Config::setSync(0);

		if($syncing) {
			_log("NodeSync finished", 3);
		}

	}

	function calculateNodeScoreNew() {
		global $db;
		$sql="select sum(if(p.ok=1, 1, 0)) / count(*) as node_score
		from (
		         select p.id,
		                (p.height <= (select max(height) from blocks) and
		                 p.block_id = (select b.id from blocks b where b.height = p.height)) as ok
		         from peers p
		         where p.blacklisted < unix_timestamp()
		           and unix_timestamp() - p.ping < 2 * 60
		     ) as p";
		$res = $db->single($sql);
		$node_score = round($res * 100, 2);
		$db->setConfig('node_score', $node_score);
		_log("Node score: $node_score");
	}

	function calculateNodeScore() {
		global $db;
		$skipped_peer = 0;
		$failed_block = 0;
		$ok_block = 0;
		$peers = Peer::getPeersForSync();
		$peers_count = count($peers);
		$current = Block::current();
		$t1=microtime(true);

		$blocksMap = [];

		if ($peers_count) {
			foreach ($peers as $index => $peer) {
				$host = $peer['hostname'];
				if(empty($peer['block_id'])) {
					$url = $host . "/peer.php?q=";
					$res = peer_post($url . "currentBlock", [], 5);
					if ($res === false) {
						_log("Node score: skipped $host because response is false", 3);
						$skipped_peer++;
						continue;
					} else{
						$data = $res['block'];
					}
				} else {
					$data['id']=$peer['block_id'];
					$data['height']=$peer['height'];
				}

				if(empty($data['id'])) {
					_log("Node score: skipped $host because block id is null", 3);
					$skipped_peer++;
					continue;
				}

				if ($data['height'] == $current['height']) {
					$ok_block++;
				} else if($current['height'] > $data['height']) {
					if(!isset($blocksMap[$data['height']])) {
						$block = Block::get($data['height']);
						$blocksMap[$data['height']]=$block;
					}
					$block = $blocksMap[$data['height']];
					if($block['id']==$data['id']) {
						$ok_block++;
					} else {
						_log("Node score: Invalid block for peer $host");
						$failed_block++;
						Peer::blacklist($peer['id'], 'Invalid block '.$data['height']);
					}
				} else {
					_log("Node score: my height is lower", 4);
				}

				$data['id']=$peer['block_id'];
				_log("Node score: Checking peer ".($index+1)." / $peers_count $host block id=" . $data['id'] .  " height=" . $data['height'] . " current=" . $current['id'] . " height=".$current['height'], 5);
			}
		}

		if($peers_count - $skipped_peer == 0 ) {
			$node_score = 0;
		} else {
			$node_score = ($ok_block / ($peers_count  - $skipped_peer))*100;
		}
		$t2=microtime(true);
		$diff = $t2 - $t1;
		_log("Node score: time=$diff ok_block=$ok_block peers_count=$peers_count failed_peer=$failed_block skipped_peer=$skipped_peer node_score=$node_score", 2);
		$db->setConfig('node_score', $node_score);
	}

	function getPeerBlock($host, $height) {

		if(isset($this->peerBlocks[$host][$height])) {
			return $this->peerBlocks[$host][$height];
		} else {
			$limit = 10;
			$url = $host."/peer.php?q=";
			_log("Reading blocks from $height from peer $host", 3);
			$peer_blocks = peer_post($url."getBlocks", ["height" => $height - $limit]);
			if ($peer_blocks === false) {
				_log("Could not get block from $host - " . $height, 3);
			} else {
				if(is_array($peer_blocks)) {
					foreach($peer_blocks as $peer_block) {
						$this->peerBlocks[$host][$peer_block['height']]=$peer_block;
					}
				}
			}
			return $this->peerBlocks[$host][$height];
		}
	}

	static function recheckLastBlocks() {
		global $_config, $db;
		$current = Block::current();
		$num = $_config['sync_recheck_blocks'];
		if(empty($num)) {
			$num = 10;
		}
		if ($num > 0) {
			_log("Rechecking blocks",3);
			$blocks = [];
			$all_blocks_ok = true;
			$start = $current['height'] - $num;
			if ($start < 2) {
				$start = 2;
			}
			$r = $db->run("SELECT * FROM blocks WHERE height>=:height ORDER by height ASC", [":height" => $start]);
			foreach ($r as $x) {
				$blocks[$x['height']] = $x;
				$max_height = $x['height'];
			}

			for ($i = $start + 1; $i <= $max_height; $i++) {
				$data = $blocks[$i];

				$block_ok = true;
				if(empty($data)) {
					$block_ok = false;
				}

				if($block_ok) {
					$block = Block::getFromArray($data);
					if (!$block->mine()) {
						$block_ok = false;
					}
				}

				if (!$block_ok) {
					_log("Invalid block detected. Deleting block height ".$i);
					$all_blocks_ok = false;
					Block::delete($i);
					break;
				}
			}
			if ($all_blocks_ok) {
				_log("All checked blocks are ok", 3);
			}
		}
	}

	static function verifyLastBlocks($num=10) {
		$current = Block::current();
		if ($num > 0) {
			_log("Rechecking blocks",3);
			$all_blocks_ok = true;
			$start = $current['height'] - $num;
			if ($start < 2) {
				$start = 2;
			}
			$max_height = $current['height'];
			for ($i = $start + 1; $i <= $max_height; $i++) {
				$block = Block::export("",$i);
				$res = Block::getFromArray($block)->verifyBlock($error);
				if(!$res) {
					_log("Invalid block detected. Deleting block height ".$i);
					$all_blocks_ok = false;
					Block::delete($i);
					break;
				}
			}
			if ($all_blocks_ok) {
				_log("All checked blocks are ok", 3);
			}
		}
	}

	static function getPeerBlocksMap() {
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

	static function checkForkedBlocks() {
		$blocksMap = self::getPeerBlocksMap();
//      _log("blocksMap: ".print_r(array_keys($blocksMap), 1));
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
//				        _log("Peer response = ".json_encode($peer_block));
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
						return NodeSync::compareBlocks($b1, $b2);
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
						return;
					}
				}
			}
		}
	}

	static function staticGetPeerBlock($host, $height) {
		static $peerBlocks;
		if(isset($peerBlocks[$host][$height])) {
			return $peerBlocks[$host][$height];
		} else {
			$limit = 10;
			$url = $host."/peer.php?q=";
			_log("Reading blocks from $height from peer $host", 3);
			$peer_blocks = peer_post($url."getBlocks", ["height" => $height - $limit]);
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

	static function syncBlocks() {

		$peersForSync = Peer::getValidPeersForSync();
		$best_height = $peersForSync[0]['height'];
		$block_id = $peersForSync[0]['block_id'];

		$current_height = Block::getHeight();


		_log("Get best height = $best_height our height=$current_height");

		if($current_height == $best_height) {
			_log("Blockchain is synced");
		} else if ($best_height < $current_height) {
			_log("We are ahead of peers");
		} else {
			_log("Need to sync blokchain");

				$syncing = true;
				$limit_cnt = 0;
				while($syncing) {
					$limit_cnt++;
					if($limit_cnt > 1000) {
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
					$added = false;
//					_log("hostnames".print_r($hostnames,1));
					foreach($peersForSync as $peer) {
						$hostname = $peer['hostname'];
						$peers_cnt++;
						if($peers_cnt > $limit_peers) {
							$syncing = false;
							break 2;
						}
						_log("Get block ".$current['height']." from peer $hostname");
						$peer_block = self::staticGetPeerBlock($hostname, $current['height']);
//				        _log("get peer block $hostname res=".json_encode($peer_block));
						if(!$peer_block) {
							_log("Not get block for peer - check other peer");
							continue;
						}
						if ($peer_block['id'] != $current['id']) {
							_log("Invalid block get from peer");
							continue;
						}
						_log("We got ok block - go to next");
						$next_block = self::staticGetPeerBlock($hostname, $current['height']+1);
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
						$added = true;
						break;
					}

					if(!$added) {
						_log("Can not add new block");
						$syncing = false;
						break;
					}

				}
		}

	}

	static function compareBlocks($block1, $block2) {
		if($block1['elapsed'] == $block2['elapsed']) {
			if($block1['date'] == $block2['date']) {
				//wins block with lower id
				return strcmp($block2['id'], $block1['id']);
			} else {
				//wind block with prev date
				return $block2['date'] - $block1['date'];
			}
		} else {
			//wins faster block
			return $block2['elapsed'] - $block1['elapsed'];
		}
	}

	static function compareCheckPoints() {
		global $checkpoints;
		require_once ROOT . "/include/checkpoints.php";
		foreach($checkpoints as $height => $block_id) {
			$block = Block::get($height);
			if(!empty($block)) {
				$block_ok = $block['id'] == $block_id;
				_log("Compare checkpoint $height - $block_id block_ok=$block_ok");
				if(!$block_ok) {
					return false;
				}
			}
		}
		return true;
	}

	static function checkBlocks() {
		global $db;
		$sql="select count(id) from blocks";
		$count = $db->single($sql);
		$sql="select max(height) from blocks";
		$max = $db->single($sql);
		return $count == $max;
	}


}



