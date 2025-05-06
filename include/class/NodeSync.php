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
			if ($loop_cnt > 100) {
				_log("Exit after added 100 blocks", 4);
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
		$sql="select sum(case when p.ok=1 then 1 else 0 end) / count(*) as node_score
		from (
		         select p.id,
		                (p.height <= (select max(height) from blocks) and
		                 p.block_id = (select b.id from blocks b where b.height = p.height)) as ok
		         from peers p
		         where p.blacklisted < ".DB::unixTimeStamp()."
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

	static function verifyLastBlocks($minutes=30, $num=60) {
        Nodeutil::runAtInterval("verifyLastBlocks", 60*$minutes, function() use ($num) {
            $current = Block::current();
            if ($num > 0) {
                _log("verifyLastBlocks: Rechecking blocks",3);
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
                        _log("verifyLastBlocks: Invalid block detected. Deleting block height ".$i);
                        $all_blocks_ok = false;
                        Block::delete($i);
                        break;
                    }
                }
                if ($all_blocks_ok) {
                    _log("verifyLastBlocks: All checked blocks are ok", 3);
                }
            }
        });



	}

	static function getPeerBlocksMap() {
		global $db;
		$sql="select * from peers p where
			p.blacklisted < ".DB::unixTimeStamp()."
			order by response_time/response_cnt";
		$peers = $db->run($sql);
		_log("Sync: Found ".count($peers)." to sync");
		$blocksMap = [];
		foreach ($peers as $peer) {
			$height = $peer['height'];
			if(empty($height)) continue;
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
					uasort($forked_blocks, function ($b1, $b2) {
						return NodeSync::compareBlocks($b2, $b1);
					});
					foreach($forked_blocks as $block_id => $block) {
						_log("   block_id=$block_id elapsed=".$block['elapsed']." date=".$block['date']. " difficulty=".$block['difficulty']. " tx_cnt=".count($block['data']));
					}
					$forked_block_winner = array_shift($forked_blocks);
					$our_block = Block::export("", $height);
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
						_log("Compare our block with winner our-other elapsed=".$our_block['elapsed']."-".$forked_block_winner['elapsed']);
						$res = NodeSync::compareBlocks($our_block, $forked_block_winner);
						if($res>0) {
							_log("Our block is winner");
						} else if ($res < 0) {
							_log("Other block is winner");
							$diff = Block::getHeight() - $height;
							_log("Our block is forked  diff=$diff");
							if($diff < 100) {
								_log("Delete up to height $height");
								Block::delete($height);
							} else {
								$peer_hostname = array_keys($blocks[$forked_block_winner['id']])[0];
								_log("Diff to high - need full check with peer $peer_hostname");
								$dir = ROOT."/cli";
								$cmd = "php $dir/deepcheck.php ".$peer_hostname;
								$check_cmd = "php $dir/deepcheck.php";
								_log("submitBlock: run peer check with ".$peer_hostname);
								Nodeutil::runSingleProcess($cmd, $check_cmd);
							}
						}
						return;
					}
				} else {
					_log("No forked blocks ".print_r($forked_blocks,1));
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
			return @$peerBlocks[$host][$height];
		}
	}

	static function syncBlocks() {

		global $db;
		$sql="select p.height as best_height, count(p.id) as peers_cnt, count(distinct p.block_id) as unique_blocks,
		       max(p.block_id) as best_block_id
		from peers p
		where
		        p.blacklisted < ".DB::unixTimeStamp()."
		  and ".DB::unixTimeStamp()." - p.ping < 2 * 60
		group by p.height
		having unique_blocks = 1
		order by count(p.id) desc
		limit 1";
		$best_chain = $db->row($sql);
		$best_height = $best_chain['best_height'];
		$best_block_id = $best_chain['best_block_id'];
		$best_peers_cnt =  $best_chain['peers_cnt'];

		_log("best chain height=".$best_height." block=".$best_block_id." peers=".$best_peers_cnt);


		$sql="select max(p.height) as longest_height, count(p.id) as peers_cnt, count(distinct p.block_id) as unique_blocks,
		       max(p.block_id) as longest_block_id
		from peers p
		where
		        p.blacklisted < ".DB::unixTimeStamp()."
		  and ".DB::unixTimeStamp()." - p.ping < 2 * 60
		group by p.height
		having unique_blocks = 1 and peers_cnt > 1
		order by p.height desc
		limit 1";
		$longest_chain = $db->row($sql);
		$longest_height = $longest_chain['longest_height'];
		$longest_block_id = $longest_chain['longest_block_id'];
		$longest_peers_cnt =  $longest_chain['peers_cnt'];
		_log("longest chain height=".$longest_height." block=".$longest_block_id." peers=".$longest_peers_cnt);


		$current = Block::current();
		$current_height = $current['height'];

        $sync_height = min($longest_height, $best_height);
        $elapsed = time() - $current['date'];
        if($elapsed > 60*60) {
            _log("elapsed more than hour - get max height");
            $sync_height = max($longest_height, $best_height);
        }
        if($sync_height == $longest_height) {
            $sync_block_id = $longest_block_id;
        } else {
            $sync_block_id = $best_block_id;
        }

		$sql="select * from peers p
			where p.height = :height
			  and p.block_id = :block_id
			   and p.blacklisted < ".DB::unixTimeStamp()."
			  and ".DB::unixTimeStamp()." - p.ping < 2 * 60
			  order by p.response_time / p.response_cnt";

		$peersForSync = $db->run($sql, [":height"=>$sync_height, ":block_id"=>$sync_block_id]);
		_log("Found ".count($peersForSync)." peer to sync", 3);

        if(count($peersForSync)==0) {
            _log("NO peers for sync - refresh peers");
            $dir = ROOT."/cli";
            $cmd = "php $dir/util.php refresh-peers";
            Nodeutil::runSingleProcess($cmd);
            return;
        }
        Config::setSync(1);
		_log("Sync height = $sync_height block=$sync_block_id peers=".count($peersForSync));


//		return;

//		$blocksMap = self::getPeerBlocksMap();
//		$heightsMap = [];
//		foreach ($blocksMap as $height=>$blocks) {
//			foreach($blocks as $block_id=>$hostnames) {
//				_log("blocksMap: height=$height cnt_blocks=".count($blocks)." cnt_hostnames=".count($hostnames)." block=$block_id");
//				if(count($blocks)>1) {
//					break 2;
//				}
//
//				//check blocks
//				$our_block = Block::export("", $height);
//				if(!$our_block) {
//					break 2;
//				}
//				if($our_block['id']!=$block_id) {
//					_log("WE HAVE INVALID BLOCK AT HEIGHT $height our_id=".$our_block['id']. " block_id=".$block_id);
//					$hostname = array_keys($hostnames)[0];
//					_log("Check hostname ".$hostname);
//					$url = $hostname . "/peer.php?q=getBlock";
//					$peer_block = peer_post($url, ["height" => $height], 5, $err );
//					if($peer_block) {
//						$res = NodeSync::compareBlocks($our_block, $peer_block);
//						if($res>0) {
//							_log("Our block is winner");
//							$peer = $hostnames[$hostname];
//							if($peer) {
//								Peer::blacklist($peer['id'], "Invalid block $height");
//							}
//						} else if ($res < 0) {
//							_log("Other block is winner");
//							$diff = Block::getHeight() - $height;
//							_log("Our block is forked  diff=$diff");
//							if($diff < 100) {
//								_log("Delete up to height $height");
//								Block::delete($height);
//							} else {
//								_log("Diff to high - need full check with peer $hostname");
//								$dir = ROOT."/cli";
//								$cmd = "php $dir/deepcheck.php ".$hostname;
//								$check_cmd = "php $dir/deepcheck.php";
//								_log("submitBlock: run peer check with ".$hostname);
//								Nodeutil::runSingleProcess($cmd, $check_cmd);
//							}
//						}
//					} else {
//						_log("No block form peer");
//						$peer = $hostnames[$hostname];
//						Peer::blacklist($peer['id'], "Unresponsive");
//					}
//					break 2;
//				}
//				_log("our_block=".$our_block['id']);
//
//
//				$heightsMap[$height]['count']=count($hostnames);
//				$heightsMap[$height]['block']=$block_id;
//			}
//		}

//		$best_height = $height;
//		_log("Get best height = $best_height our height=$current_height");

//		$peersForSync=$blocksMap[$best_height][$block_id];

		$start_height = $current_height;

		if($current_height == $sync_height) {
			_log("Blockchain is synced");
		} else {
			_log("Need to sync blokchain");

		for($i=0;$i<count($peersForSync);$i++){
			$peer = $peersForSync[$i];
            $hostname = $peer['hostname'];
			self::peerSync($hostname,100,10,$ret);
			if($ret=="no_peer_data"){
				_log("Select next peer",3);
				continue;
            }
			break;
        }

        Config::setSync(0);
        _log("Finished sync");
        return;

				$syncing = true;
				$limit_cnt = 0;
				while($syncing) {
					$limit_cnt++;
					if($limit_cnt > 1000) {
						break;
					}
					$current = Block::export("", Block::getHeight());
					$syncing = (($current['height'] < $sync_height && $sync_height > 1)
						|| ($current['height'] == $sync_height && $current['id'] != $sync_block_id)
                        || ($longest_height != $best_height));

					if (!$syncing) {
						break;
					}

					_log("Syncing current_height= ".$current['height']." sync_height=$sync_height current_id=".$current['id']." block_id=$sync_block_id",3);
					$limit_peers = 10;
					$peers_cnt = 0;
					$peer_blocks = [];
//					_log("hostnames".print_r($hostnames,1));
					foreach($peersForSync as $peer) {
						$hostname = $peer['hostname'];
						$peers_cnt++;
						if($peers_cnt > $limit_peers) {
							$syncing = false;
							break;
						}
						_log("Get block ".$current['height']." from peer $hostname", 5);
						$peer_block = self::staticGetPeerBlock($hostname, $current['height']);
//				        _log("get peer block $hostname res=".json_encode($peer_block));
						if(!$peer_block) {
//                            Peer::blacklist($peer['id'], "Unresponsive", 1);
							_log("Not get block for peer - check other peer");
							continue;
						}
						$peer_blocks[$peer_block['id']]=$peer_block;
//						_log("Get peer block ".print_r($peer_block,1)." my=".print_r($current,1));
						if ($peer_block['id'] != $current['id']) {
							_log("Blocks does not match peer=".$peer_block['id']. " my=".$current['id']);
							continue;
						}
						_log("We got ok block - go to next", 5);
						$next_block = self::staticGetPeerBlock($hostname, $current['height']+1);
						if(!$next_block) {
							_log("Not get next block for peer - check other peer");
                            $syncing = false;
                            break;
						}
						$block = Block::getFromArray($next_block);
						if (!$block->check()) {
							_log("Next block check failed");
							continue;
						}
						_log("Block check ok", 4);
						$block->prevBlockId = $current['id'];
						$res = $block->add($err, true);
						if(!$res) {
							_log("Error adding block: $err");
							continue;
						}
						_log("Block added", 4);
						$res=$block->verifyBlock($err);
						if(!$res) {
							_log("Error verify block: $err");
                            $diff = $sync_height - $current['height'];
                            _log("Can not add new block  sync_height=$sync_height height=".$current['height']." diff=$diff err=$err");
                            if(strpos($err, "Invalid schash")!== false) {
                                Block::pop();
                                $smart_contracts = [];
                                foreach ($block->data as $x) {
                                    $tx = Transaction::getFromArray($x);
                                    $type = $tx->type;
                                    if ($type == TX_TYPE_SC_CREATE) {
                                        $smart_contracts[$tx->dst][$tx->id]=$tx;
                                    }
                                    if ($type == TX_TYPE_SC_EXEC) {
                                        $smart_contracts[$tx->dst][$tx->id]=$tx;
                                    }
                                    if ($type == TX_TYPE_SC_SEND) {
                                        $smart_contracts[$tx->src][$tx->id]=$tx;
                                    }
                                }
                                $addresses=array_keys($smart_contracts);
                                _log("Pop blocks because of schash addresses=".json_encode($addresses));
                                $params = [];
                                foreach ($addresses as $index=>$address) {
                                    $name=":p".$index;
                                    $params[$name]=$address;
                                }
                                $in_params=implode(",", array_keys($params));
                                $sql="select max(height) from smart_contract_state s where sc_address in ($in_params)";
                                $max_height=$db->single($sql, $params);
                                $delete_blocks = $block->height - $max_height;
                                _log("DELETE invalid blocks $delete_blocks");
                                Block::pop($delete_blocks);
                            } else {
                                Block::pop();
                                $dir = ROOT."/cli";
                                $peer = $peersForSync[0];
                                _log("Trigger deep check with ".$peer['hostname']);
                                $cmd = "php $dir/deepcheck.php ".$peer['hostname'];
                                $check_cmd = "php $dir/deepcheck.php";
                                Nodeutil::runSingleProcess($cmd, $check_cmd);
                            }
							$syncing = false;
							break;
						}
						_log("Block verified", 4);
						break;
					}

					_log("Finish check peers", 4);


				}

				$current_height = Block::getHeight();
				if($current_height > $start_height) {
                    Propagate::blockToAll('current');
				}
		}

        Config::setSync(0);

	}

    static function peerSync($hostname, $add_limit=100, $delete_limit=10,&$ret=null) {
        _log("Syncing with peer ".$hostname);

        $syncing = true;
        $add_cnt = 0;
        $delete_cnt = 0;
        while($syncing) {
            $add_cnt++;
            if($add_limit>0 && $add_cnt > $add_limit) {
                _log("Sync limit reached");
                $syncing = false;
                break;
            }

            $current = Block::current();
            $height = $current['height'] + 1;
            _log("PeerSync: syncing $height",2);

            $peer_block_data = NodeSync::staticGetPeerBlock($hostname, $height);
            if ($peer_block_data) {
                $peer_block = Block::getFromArray($peer_block_data);
                $res = $peer_block->check($err);
                if (!$res) {
                    _log("PeerSync: Peer block check failed: $err");
                    Block::pop();
                    $delete_cnt++;
                    if($delete_limit>0 && $delete_cnt > $delete_limit) {
                        $syncing = false;
                        break;
                    } else {
                        continue;
                    }
                }
                _log("Peer block ok",3);
                $peer_block->prevBlockId = $current['id'];
                $res = $peer_block->add($err, true);
                if (!$res) {
                    _log("PeerSync: Peer block add failed: $err");
                    if($err=="Block already added"){
                        $syncing = false;
                        break;
                    }
                    if($err=="block-add-locked"){
                        $syncing = false;
                        break;
                    }

                    if(FEATURE_SMART_CONTRACTS && $err!=null && strpos($err, "Invalid schash")!== false) {
                        _log("PeerSync: invalid hash - run sync state");
                        $cmd = "php " . ROOT . "/cli/util.php sync-sc-state $hostname";
                        Nodeutil::runSingleProcess($cmd);
                        $syncing = false;
                        break;
                    }

                    Block::pop();
                    $delete_cnt++;
                    if($delete_limit>0 && $delete_cnt > $delete_limit) {
                        $syncing = false;
                        break;
                    } else {
                        continue;
                    }
                }
                _log("Peer block added",3);
                $res = $peer_block->verifyBlock($err);
                _log("PeerSync: verifyBlock res=".json_encode($res),3);
                if (!$res) {
                    _log("PeerSync: Error verify block: $err");
                    Block::pop();
                    $delete_cnt++;
                    if($delete_limit>0 && $delete_cnt > $delete_limit) {
                        $syncing = false;
                        break;
                    } else {
                        continue;
                    }
                }
                _log("PeerSync: Block verified",3);
            } else {
                _log("PeerSync: NO peer block data");
                $syncing = false;
		        $ret="no_peer_data";
                break;
            }
        }
        Propagate::blockToAll("current");
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
		$invalid_height = null;
		foreach($checkpoints as $height => $block_id) {
			$block = Block::get($height);
			if(!empty($block)) {
				$block_ok = $block['id'] == $block_id;
				_log("Compare checkpoint $height - $block_id block_ok=$block_ok", 2);
				if(!$block_ok) {
					$invalid_height = $height;
					break;
				}
			}
		}
		if(empty($invalid_height)) {
            _log("compareCheckPoints: no invalid height", 4);
			return true;
		} else {
		    _log("compareCheckPoints: invalid_height=$invalid_height");
			$diff = Block::getHeight() - $invalid_height;
			_log("delete diff = $diff");
			$dir = ROOT . "/cli";
			$cmd = "php $dir/util.php pop $diff";
			_log("Run cmd $cmd");
			$check_cmd = "php $dir/util.php pop";
			Nodeutil::runSingleProcess($cmd, $check_cmd);
			return false;
		}
	}

	static function checkBlocks() {
		global $db;
        $sql="select count(id) as cnt, max(height) as max_height from blocks";
        $res = $db->row($sql);
        $count = $res['cnt'];
        $max = $res['max_height'];
		_log("checkBlocks count=$count max=$max", 3);
		if($count == $max) {
			return true;
		} else {
			if(!Config::isSync()) {
				$sql = "select min(height) from (
                select b.height, b.id, lead(b.height) over (order by b.height),
                       lead(b.height) over (order by b.height) - b.height as diff
                from blocks b
                order by b.height asc) as b
                where diff <> 1";
				$invalid_height = $db->single($sql);
				if(!empty($invalid_height)) {
					_log("checkBlocks invalid_height=$invalid_height");
					$diff = Block::getHeight() - $invalid_height;
					if ($diff > 100) {
						$diff = 100;
					}
					$dir = ROOT . "/cli";
					$cmd = "php $dir/util.php pop $diff";
					_log("Run cmd $cmd");
					$check_cmd = "php $dir/util.php pop";
					Nodeutil::runSingleProcess($cmd, $check_cmd);
				}
			} else {
                _log("checkBlocks: sync in process");
            }
            return false;
		}
	}


}



