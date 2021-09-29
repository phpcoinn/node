<?php

class NodeSync
{

	public $peers;
	public $peerBlocks;

	function __construct($peers)
	{
		$this->peers = $peers;
	}

	public function start($largest_height, $most_common)
	{

		$block = new Block();

		global $db;
		$db->run("UPDATE config SET val=1 WHERE cfg='sync'");

		$peers_count = count($this->peers);

		$syncing = true;
		$loop_cnt = 0;
		while($syncing) {
			$loop_cnt++;
			if($loop_cnt > $largest_height) {
				break;
			}

			$current = $block->current();
			$syncing = (($current['height'] < $largest_height && $largest_height > 1)
				|| ($current['height'] == $largest_height && $current['id']!=$most_common));
			_log("check syncing syncing=$syncing current_height=".$current['height']." largest_height=$largest_height current_id=".
				$current['id']." most_common=$most_common");

			if(!$syncing) {
				break;
			}

			$failed_peer = 0;
			$skipped_peer = 0;
			$failed_block = 0;
			$ok_block = 0;
			$height = $current['height'];
			foreach ($this->peers as $host) {

				$peer_block = $this->getPeerBlock($host, $height);
				if($peer_block) {
					_log("peer_block=".$peer_block['id']. " current_id=".$current['id']);
					if ($peer_block['id'] != $current['id']) {
						$failed_block++;
						$failed_peer++;
						_log("We have wrong block $height failed_block=$failed_block",0);
					} else {
						$ok_block++;
						_log("We have ok block $height ok_block=$ok_block",0);
					}
				} else {
					$skipped_peer++;
				}

			}

			if($peers_count - $failed_peer - $skipped_peer == 0 ) {
				$node_score = 0;
			} else {
				$node_score = ($ok_block / ($peers_count - $failed_peer - $skipped_peer))*100;
			}
			_log("NODE SCORE ok_block=$ok_block peers_count=$peers_count failed_peer=$failed_peer skipped_peer=$skipped_peer node_score=$node_score");
			$db->setConfig('node_score', $node_score);

			if($failed_block > 0 && $failed_block > ($peers_count - $failed_peer) / 2 ) {
				_log("Failed block on blockchain $failed_block - remove", 0);
				$res=$block->pop();
				if(!$res) {
					_log("Can not delete block on height $height");
					$syncing = false;
					break;
				}
			} else if ($ok_block == ($peers_count - $failed_peer)) {
				_log("last block $height is ok - get next", 0);
				$failed_next_peer = 0;
				$next_block_peers = [];
				$height++;

				$min_elapsed = PHP_INT_MAX;
				foreach ($this->peers as $host) {
					$next_block = $this->getPeerBlock($host, $height);
					if (!$next_block) {
						_log("Could not get block from $host - " . $height);
						$failed_next_peer++;
						continue;
					}
					$prev_block = Block::getAtHeight($height-1);
					$elapsed = $next_block['date'] - $prev_block['date'];
					if($elapsed < $min_elapsed) {
						$min_elapsed = $elapsed;
					}
					$next_block_peers[$elapsed][$host]=$next_block;
				}



				if(count(array_keys($next_block_peers))==0) {
					_log("No next block on peers");
					break;
				} else {
					if(count(array_keys($next_block_peers))==1) {
						$id = array_keys($next_block_peers)[0];
						_log("All peers return same block - checking block $height", 0);
						$host = array_keys($next_block_peers[$id])[0];
						$next_block = $next_block_peers[$id][$host];
//				_log("Checking block ". print_r($next_block,1));
						if (!$block->check($next_block)
						) {
							_log("Invalid block mined at height ".$height);
							$syncing = false;
							_log("Blacklist node $host");
							$peer = Peer::findByHostname($host);
							Peer::blacklist($peer['id'], 'Invalid block');
							break;
						} else {
							$prev_block = Block::getAtHeight($next_block['height']-1);
							$prev_block_id = $prev_block['id'];
							$res = $block->add(
								$next_block['height'],
								$next_block['public_key'],
								$next_block['miner'],
								$next_block['nonce'],
								$next_block['data'],
								$next_block['date'],
								$next_block['signature'],
								$next_block['difficulty'],
								$next_block['argon'],
								$prev_block_id
							);
							if (!$res) {
								_log("Block add: could not add block at height $height", 3);
								$syncing = false;
								_log("Blacklist node $host");
								$peer = Peer::findByHostname($host);
								Peer::blacklist($peer['id'], 'Invalid block');
								break;
							} else {
								$bl = new Block();
								$vblock = $bl->export("", $height);
								$res = Block::verifyBlock($vblock);
								if(!$res) {
									_log("Block is not verified", 3);
									$syncing = false;
									_log("Blacklist node $host");
									$peer = Peer::findByHostname($host);
									Peer::blacklist($peer['id'], 'Invalid block');
									break;
								}
							}
						}
					} else {
						_log("Different blocks on same height $height on peers - skip ");

						$best_block_peers = $next_block_peers[$min_elapsed];
						foreach ($next_block_peers as $elapsed=>$nodes) {
							if($elapsed != $min_elapsed) {
								foreach ($nodes as $node=>$b) {
									_log("Blacklist node $node");
									$peer = Peer::findByHostname($node);
									Peer::blacklist($peer['id'], 'Invalid block');
								}
							}
						}
//
//						$block_counts = [];
//						$total_count = 0;
//						foreach ($next_block_peers as $id=>$nodes) {
//							$block_counts[$id] = count($nodes);
//							$total_count+=count($nodes);
//						}
//
//						uasort($block_counts, function ($a, $b) {
//							return $b - $a;
//						});
//
//						$top_block = array_keys($block_counts)[0];
//
//						$top_block_count = count($next_block_peers[$top_block]);
//						_log("Top block is $top_block on $top_block_count nodes");
//
//						if($top_block_count >= 2/3 * $total_count) {
//							foreach ($next_block_peers as $id=>$nodes) {
//								if($id != $top_block) {
//									foreach ($nodes as $node=>$b) {
//										_log("Blacklist node $node");
//										$peer = Peer::findByHostname($node);
//										Peer::blacklist($peer['id'], 'Invalid block');
//									}
//								}
//							}
//						}
						
					}
				}


			}

		}

		$t = time();
		$db->run("UPDATE config SET val=0 WHERE cfg='sync'", [":time" => $t]);
		if($syncing) {
			_log("Blockchain SYNCED",0);
		}

	}

	function getPeerBlock($host, $height) {
		if(isset($this->peerBlocks[$host][$height])) {
			return $this->peerBlocks[$host][$height];
		} else {
			$limit = 10;
			$url = $host."/peer.php?q=";
			_log("Reading blocks from $height from peer $host", 0);
			$peer_blocks = peer_post($url."getBlocks", ["height" => $height - $limit], 5);
			if ($peer_blocks === false) {
				_log("Could not get block from $host - " . $height,0);
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

}



