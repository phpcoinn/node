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
			$min_ok_blocks = 1;
			$good_peers = [];
			foreach ($this->peers as $ip) {
				$peers_count++;
				$peer_block = $this->getPeerBlock($ip, $height);
				if ($peer_block) {
					_log("peer_block=" . $peer_block['id'] . " current_id=" . $current['id'], 4);
					if ($peer_block['id'] != $current['id']) {
						$failed_block++;
						$failed_peer++;
						_log("We have wrong block $height failed_block=$failed_block");
					} else {
						$ok_block++;
						$good_peers[]=$ip;
						_log("We have ok block $height ok_block=$ok_block", 4);
						if($ok_block >= $min_ok_blocks) {
							break;
						}
					}
				} else {
					$failed_peer++;
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
				foreach ($good_peers as $ip) {
					$next_block = $this->getPeerBlock($ip, $height);
					if (!$next_block) {
						_log("Could not get block from $ip - " . $height, 3);
						$failed_next_peer++;
						continue;
					}
					$prev_block = Block::getAtHeight($height - 1);
					$elapsed = $next_block['date'] - $prev_block['date'];
					if ($elapsed < $min_elapsed) {
						$min_elapsed = $elapsed;
					}
					$next_block_peers[$elapsed][$ip] = $next_block;
				}


				if (count(array_keys($next_block_peers)) == 0) {
					_log("No next block on peers", 3);
					break;
				} else {
					if (count(array_keys($next_block_peers)) == 1) {
						$id = array_keys($next_block_peers)[0];
						_log("All peers return same block - checking block $height", 4);
						$ips = array_keys($next_block_peers[$id]);
						$ip = $ips[0];
						$next_block = $next_block_peers[$id][$ip];
//				_log("Checking block ". print_r($next_block,1));
						$block = Block::getFromArray($next_block);
						if (!$block->check()
						) {
							_log("Invalid block mined at height " . $height);
							$syncing = false;
							_log("Blacklist node $ip");
							$peer = Peer::findByIp($ip);
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
								_log("Blacklist node $ip");
								$peer = Peer::findByIp($ip);
								Peer::blacklist($peer['id'], 'Invalid block '.$height);
								break;
							} else {
								$vblock = Block::export("", $height);
								$res = Block::getFromArray($vblock)->verifyBlock();
								if (!$res) {
									_log("Block is not verified");
									$syncing = false;
									_log("Blacklist node $ip");
									$peer = Peer::findByIp($ip);
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
								foreach ($nodes as $ip => $b) {
									_log("Blacklist node $ip");
									$peer = Peer::findByIp($ip);
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
				$host = Peer::getPeerUrl($peer['ip']);
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

	function getPeerBlock($ip, $height) {
		if(isset($this->peerBlocks[$ip][$height])) {
			return $this->peerBlocks[$ip][$height];
		} else {
			$limit = 10;
			$url = Peer::getPeerUrl($ip)."/peer.php?q=";
			_log("Reading blocks from $height from peer $ip", 3);
			$peer_blocks = peer_post($url."getBlocks", ["height" => $height - $limit], 5);
			if ($peer_blocks === false) {
				_log("Could not get block from $ip - " . $height, 3);
			} else {
				if(is_array($peer_blocks)) {
					foreach($peer_blocks as $peer_block) {
						$this->peerBlocks[$ip][$peer_block['height']]=$peer_block;
					}
				}
			}
			return $this->peerBlocks[$ip][$height];
		}
	}

	static function recheckLastBlocks() {
		global $_config, $db;
		$current = Block::current();
		if ($_config['sync_recheck_blocks'] > 0) {
			_log("Rechecking blocks",3);
			$blocks = [];
			$all_blocks_ok = true;
			$start = $current['height'] - $_config['sync_recheck_blocks'];
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

}



