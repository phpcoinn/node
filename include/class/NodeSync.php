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

		global $db;
		$db->run("UPDATE config SET val=1 WHERE cfg='sync'");

		$peers_count = @count($this->peers);

		$syncing = true;
		$loop_cnt = 0;
		$max_removed = 0;
		while ($syncing) {
			$loop_cnt++;
			if ($loop_cnt > $largest_height) {
				break;
			}

			if($max_removed > 10) {
				_log("Max removed. break", 4);
				break;
			}

			$current = Block::current();
			$syncing = (($current['height'] < $largest_height && $largest_height > 1)
				|| ($current['height'] == $largest_height && $current['id'] != $most_common));
			_log("check syncing syncing=$syncing current_height=" . $current['height'] . " largest_height=$largest_height current_id=" .
				$current['id'] . " most_common=$most_common", 3);

			if (!$syncing) {
				break;
			}

			$failed_peer = 0;
			$skipped_peer = 0;
			$failed_block = 0;
			$ok_block = 0;
			$height = $current['height'];
			foreach ($this->peers as $host) {

				$peer_block = $this->getPeerBlock($host, $height);
				if ($peer_block) {
					_log("peer_block=" . $peer_block['id'] . " current_id=" . $current['id'], 4);
					if ($peer_block['id'] != $current['id']) {
						$failed_block++;
						$failed_peer++;
						_log("We have wrong block $height failed_block=$failed_block");
					} else {
						$ok_block++;
						_log("We have ok block $height ok_block=$ok_block", 4);
					}
				} else {
					$skipped_peer++;
				}

			}

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
				foreach ($this->peers as $host) {
					$next_block = $this->getPeerBlock($host, $height);
					if (!$next_block) {
						_log("Could not get block from $host - " . $height);
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
							//TODO: blacklist all peers with invalid block and write height of invalid block
							$peer = Peer::findByHostname($host);
							Peer::blacklist($peer['id'], 'Invalid block');
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
								Peer::blacklist($peer['id'], 'Invalid block');
								break;
							} else {
								$vblock = Block::export("", $height);
								$res = Block::getFromArray($vblock)->verifyBlock();
								if (!$res) {
									_log("Block is not verified");
									$syncing = false;
									_log("Blacklist node $host");
									$peer = Peer::findByHostname($host);
									Peer::blacklist($peer['id'], 'Invalid block');
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
									Peer::blacklist($peer['id'], 'Invalid block');
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

		$this->calculateNodeScore();

		$t = time();
		$db->run("UPDATE config SET val=0 WHERE cfg='sync'", [":time" => $t]);
		if($syncing) {
			_log("Blockchain SYNCED");
		}

	}

	function calculateNodeScore() {
		global $db;
		$skipped_peer = 0;
		$failed_block = 0;
		$ok_block = 0;
		$peers = Peer::getActive();
		$peers_count = count($peers);
		$current = Block::current();
		if ($peers_count) {
			foreach ($peers as $peer) {
				$host = $peer['hostname'];
				$url = $host . "/peer.php?q=";
				$res = peer_post($url . "currentBlock", [], 5);
				if ($res === false) {
					$skipped_peer++;
				} else {
					$data = $res['block'];
					if ($data['id'] == $current['id']) {
						$ok_block++;
					} else {
						$failed_block++;
					}
				}
				_log("Checking peer $host block id=" . $data['id'] . " current=" . $current['id']);
			}
		}

		if($peers_count - $skipped_peer == 0 ) {
			$node_score = 100;
		} else {
			$node_score = ($ok_block / ($peers_count  - $skipped_peer))*100;
		}
		_log("NODE SCORE ok_block=$ok_block peers_count=$peers_count failed_peer=$failed_block skipped_peer=$skipped_peer node_score=$node_score", 2);
		$db->setConfig('node_score', $node_score);
	}

	function getPeerBlock($host, $height) {
		if(isset($this->peerBlocks[$host][$height])) {
			return $this->peerBlocks[$host][$height];
		} else {
			$limit = 10;
			$url = $host."/peer.php?q=";
			_log("Reading blocks from $height from peer $host", 3);
			$peer_blocks = peer_post($url."getBlocks", ["height" => $height - $limit], 5);
			if ($peer_blocks === false) {
				_log("Could not get block from $host - " . $height);
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



