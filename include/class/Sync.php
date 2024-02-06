<?php

class Sync extends Daemon
{

	static $name = "sync";
	static $title = "Sync";

	static $max_run_time = 60 * 60;
	static $run_interval = 30;

	static function isEnabled() {
		global $_config;
		return !isset($_config["sync_disabled"]) || $_config["sync_disabled"]==0;
	}

	static function enable() {
		global $db;
		$db->setConfig("sync_disabled", 0);
	}

	static function disable() {
		global $db;
		$db->setConfig("sync_disabled", 1);
	}

	static function processNew() {
		global $db, $_config;
		ini_set('memory_limit', '2G');
		$t1 = microtime(true);

		$now = time();
		$sync_last = Config::getVal('sync_last');
		_log("Check sync last: $sync_last elapsed = ".($now - $sync_last), 3);
		if(Config::isSync() && ($now - $sync_last > 60*60*2)) {
			_log("Set sync = 0");
			Config::setSync(0);
			return;
		}

        $height = Block::getHeight();
        if($height >= DELETE_CHAIN_HEIGHT) {
            $diff = $height - DELETE_CHAIN_HEIGHT;
            if($diff > 0) {
                _log("Pop $diff blocks at demand");
                Block::pop($diff);
                return;
            }
        }

		Peer::deleteDeadPeers();
		Peer::blacklistInactivePeers();
		Peer::blacklistIncompletePeers();
		Peer::resetResponseTimes();

		$res = self::checkPeers();
        if(!$res) {
            _log("Wait to initialize peers");
            return;
        }

		$res = NodeSync::checkBlocks();
		if(!$res) {
			_log("Block database is invalid");
			Config::setVal("blockchain_invalid", 1);
			return;
		}
		$res = NodeSync::compareCheckPoints();
		if(!$res) {
			_log("Blockchain is invalid - checkpoints are not correct");
			Config::setVal("blockchain_invalid", 1);
			return;
		}
        NodeSync::recheckLastBlocks();
		Config::setVal("blockchain_invalid", 0);

		Mempool::deleteOldMempool();
//		NodeSync::checkForkedBlocks();
        NodeSync::verifyLastBlocks(); //switch to run on hour
		NodeSync::syncBlocks();
		$peersForSync = Peer::getValidPeersForSync();
		$nodeSync = new NodeSync($peersForSync);
		$nodeSync->calculateNodeScoreNew();


		//rebroadcasting local transactions
		$current = Block::current();
        $r = Mempool::getForRebroadcast($current['height']);
        _log("Rebroadcasting local transactions - ".count($r), 1);
        foreach ($r as $x) {
            Propagate::transactionToAll($x['id']);
        }

		//rebroadcasting transactions
        $forgotten = $current['height'] - 30;
        $r=Mempool::getForgotten($forgotten);
        _log("Rebroadcasting external transactions - ".count($r),1);
        foreach ($r as $x) {
            Propagate::transactionToAll($x['id']);
        }


		Nodeutil::cleanTmpFiles();
		Minepool::deleteOldEntries();
		Cache::clearOldFiles();

        $cmd='find '.ROOT.'/tmp -name "*.lock" -mmin +1 -exec rm -rf {} +';
        _log("Remove lock files cmd=$cmd", 3);
        shell_exec($cmd);

		_log("Finishing sync",3);
		$t2 = microtime(true);
		Config::setVal("sync_last", time());
		_log("Sync process finished in time ".round($t2-$t1, 3));
	}

	static function process() {
		self::processNew();
	}

	static function checkPeers() {
		global $_config, $db;
		$total_peers = Peer::getCount(false);
		_log("Total peers: ".$total_peers, 3);
		$peered = [];
		// if we have no peers, get the seed list from the official site
		if ($total_peers == 0) {

            $dir = ROOT."/cli";
            $cmd = "php $dir/util.php init-peers";
            Nodeutil::runSingleProcess($cmd);
            $db->setConfig('node_score', 0);
            return false;
		}
        return true;
	}

	static function getMorePeers() {
		Daemon::runAtInterval("gmp", 30, function() {
			$dir = ROOT."/cli";
			$cmd = "php $dir/util.php get-more-peers";
			Nodeutil::runSingleProcess($cmd);
		});
	}

	static function refreshPeers() {
		Daemon::runAtInterval("refreshPeers", 45, function() {
			$dir = ROOT."/cli";
			$cmd = "php $dir/util.php refresh-peers";
			Nodeutil::runSingleProcess($cmd);
		});
	}

	static function processOld() {
		global $db, $_config;
		ini_set('memory_limit', '2G');
		$current = Block::current();

		Peer::deleteDeadPeers();
		Peer::blacklistInactivePeers();
		Peer::resetResponseTimes();
		NodeSync::recheckLastBlocks();

		$sql="select max(height) from peers";
		$max_height = $db->single($sql);
		_log("Max peers height = ".$max_height. " current=".$current['height']);

		$t = time();
		$t1 = microtime(true);
//		_log("Starting sync",3);
//		Config::setSync(1);

		// update the last time sync ran, to set the execution of the next run
		$db->run("UPDATE config SET val=:time WHERE cfg='sync_last'", [":time" => $t]);

		$total_peers = Peer::getCount(false);
		_log("Total peers: ".$total_peers, 3);

		$peered = [];
		// if we have no peers, get the seed list from the official site
		if ($total_peers == 0) {
			$i = 0;
			$failed_peers = 0;
			_log('No peers found. Attempting to get peers from the initial list');

			$peers = Peer::getInitialPeers();

			_log("Checking peers: ".print_r($peers, 1), 3);
			foreach ($peers as $peer) {
				// Peer with all until max_peers
				// This will ask them to send a peering request to our peer.php where we add their peer to the db.
				$peer = trim(san_host($peer));

				if(!Peer::validate($peer)) {
					continue;
				}

				_log("Process peer ".$peer, 4);

				if($peer === $_config['hostname']) {
					continue;
				}

				// store the hostname as md5 hash, for easier checking
				$pid = md5($peer);
				// do not peer if we are already peered
				if ($peered[$pid] == 1) {
					continue;
				}
				$peered[$pid] = 1;

				if ($_config['passive_peering'] == true) {
					// does not peer, just add it to DB in passive mode
					$res=Peer::insert(md5($peer), $peer);
				} else {
					// forces the other node to peer with us.
					$res = peer_post($peer."/peer.php?q=peer", ["hostname" => $_config['hostname'], "repeer" => 1], 30, $err);
				}
				if ($res !== false) {
					$i++;
					_log("Peering OK - $peer");
				} else {
					$failed_peers++;
					_log("Peering FAIL - $peer Error: $err");
					if($failed_peers > 10) {
						break;
					}
				}
				if ($i > $_config['max_peers']) {
					break;
				}
			}
			// count the total peers we have
			$total_peers = Peer::getCountAll();
			if ($total_peers == 0) {
				// something went wrong, could not add any peers -> exit
				_log("There are no active peers");
				$db->setConfig('node_score', 0);
				_log("There are no active peers!\n");
				return;
			}
		}

		Daemon::runAtInterval("gmp", 30, function() {
			$dir = ROOT."/cli";
			$cmd = "php $dir/util.php get-more-peers";
			Nodeutil::runSingleProcess($cmd);
		});

		$peers = Peer::getPeersForSync();
		$peerData = [];
		$peerResponseTimes = [];
		foreach($peers as $peer) {
			$hostname = $peer['hostname'];
			if(empty($peer['block_id']) || empty($peer['height'])) {
		//		_log("PeerSync: skip live peer $hostname block_id=".$peer['block_id']." height=".$peer['height']." version=".$peer['version']);
				continue;
			}
			$peerData[$hostname]=[
				"peer"=>$peer,
				"id"=>$peer["block_id"],
				"height"=>$peer["height"]
			];
			if($peer['response_cnt']==0) {
				$responseTime = PHP_INT_MAX;
			} else {
				$responseTime = $peer['response_time'] / $peer['response_cnt'];
			}
			$peerResponseTimes[$hostname] = $responseTime;
		//	_log("PeerSync: add live peer data $hostname block_id=".$peer["block_id"]." height=".$peer["height"]);
		}
		$live_peers_count = count($peerData);
		//Then get all other peers
		$peers = Peer::getActive($live_peers_count * 2);
		_log("PeerSync: get active peers ".count($peers), 5);
		$peerInfo = Peer::getInfo();
		foreach($peers as $peer) {
			$hostname = $peer['hostname'];
			if(isset($peerData[$hostname])) {
				continue;
			}
//			_log("PeerSync: Contacting peer $hostname", 5);
			$url = $hostname."/peer.php?q=";
			$res = peer_post($url."currentBlock", [], 5, $err, $peerInfo);
			if ($res === false) {
				//		_log("Peer $hostname unresponsive url={$url}currentBlock response=$res");
				// if the peer is unresponsive, mark it as failed and blacklist it for a while
				Peer::blacklist($peer['id'],"Unresponsive");
				continue;
			}
			$data = $res['block'];
			$info = $res['info'];
			if(version_compare($info['version'], MIN_VERSION) < 0) {
				_log("PeerSync: Peer $hostname blacklisted beacuse of version ".$info['version']);
				Peer::blacklist($peer['id'], "Invalid version ".$_POST['version']);
				continue;
			}

			// peer was responsive, mark it as good
			if ($peer['fails'] > 0) {
				Peer::clearFails($peer['id']);
			}
			Peer::updateInfo($peer['id'], $info);
			$peerData[$hostname]=[
				"peer"=>$peer,
				"id"=>$data['id'],
				"height"=>$data["height"]
			];
			if($peer['response_cnt']==0) {
				$responseTime = PHP_INT_MAX;
			} else {
				$responseTime = $peer['response_time'] / $peer['response_cnt'];
			}
			$peerResponseTimes[$hostname] = $responseTime;
			_log("PeerSync: add other peer data id=".$peer['id']." $hostname block_id=".$data["id"]." height=".$data["height"], 5);
		}

		$peers_count = count($peerData);
		_log("PeerSync: total peers_count=$peers_count", 5);

		$blocksMap = [];
		$peersMap = [];

		//check all, but if ping is older contact peer
		foreach ($peerData as $hostname => $data) {

//			_log("PeerSync: check blacklist $hostname height=".$data['height']." id=".$data['id'],5);

			$peer = $data['peer'];
			$height = $data['height'];
			$id = $data['id'];


			if ($current['height'] > 1 && $data['height'] >1 && $data['height'] < $current['height'] - 100) {
				_log("PeerSync: blacklist peer $hostname because is 100 blocks behind, our height=".$current['height']." peer_height=".$data['height'],2);
				Peer::blacklistStuck($peer['id'],"100 blocks behind");
				continue;
			} else {
				if ($peer['stuckfail'] > 0) {
					Peer::clearStuck($peer['id']);
				}
			}

			// set the largest height block
			$blocksMap[$height][$id][$hostname]=$hostname;
			$peersMap[$height][]=$hostname;
		}

		uksort($blocksMap, function($k1, $k2) {
			return $k1 - $k2;
		});

//		_log("Block map = ".json_encode($blocksMap, JSON_PRETTY_PRINT), 5);

		$forked = false;
		$not_forked_heights = [];
		_log("Checking blocks map for forks", 5);
		foreach($blocksMap as $height => $blocks) {
			_log("Blocks map: height=$height blocks=".count($blocks) ." id=".array_keys($blocks)[0], 3);
			if(count($blocks)>1) {
//				_log("Start checking blocks time and difficulty", 5);
				$forkedBlocksMap = [];
				$forkedBlocksPeers = [];
				foreach ($blocks as $block_id => $peers) {
//					_log("Checking block $block_id count=" . count($peers), 5);
					foreach ($peers as $peer) {
						$url = $peer . "/peer.php?q=";
						$peer_blocks = peer_post($url . "getBlocks", ["height" => $height -1], 30, $err, $peerInfo );
						if (!$peer_blocks) {
							continue;
						}
						$peer_prev_block = array_shift($peer_blocks);
						$peer_block = array_shift($peer_blocks);
						if (!$peer_prev_block || !$peer_block) {
							continue;
						}
						if($peer_block['id']!=$block_id) {
							continue;
						}
						$elapsed = $peer_block['date'] - $peer_prev_block['date'];
						$peer_block['elapsed'] = $elapsed;
						$difficulty = $peer_block['difficulty'];
						_log("Forked block $block_id at height $height from peer $peer elapsed=$elapsed diff=$difficulty", 5);
						$forkedBlocksMap[$block_id] = $peer_block;
						$forkedBlocksPeers[$block_id]=$peer;
						break;
					}
				}

				if(count($forkedBlocksMap) === 0) {
					unset($blocksMap[$height]);
				} else {
					if(count(array_keys($forkedBlocksMap))>1) {
						$forked = true;
						uasort($forkedBlocksMap, function ($b1, $b2) {
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
					}
					_log("Forked blocks peers " . json_encode($forkedBlocksPeers, JSON_PRETTY_PRINT), 5);
					$winForkedBlock = array_shift($forkedBlocksMap);
					_log("Forked block winner at height $height is block " . $winForkedBlock['id']);
					$winPeers = $blocksMap[$height][$winForkedBlock['id']];
					$blocksMap[$height] = [$winForkedBlock['id'] => $winPeers];
					foreach($forkedBlocksPeers as $block_id => $hostname) {
						$winner = $block_id == $winForkedBlock['id'];
						_log("Check forked peer $hostname block=$block_id winner=$winner", 5);
						if(!$winner) {
							$peer = Peer::findByHostname($hostname);
							if($peer) {
								Peer::blacklist($peer['id'], "Forked block $height", 5);
							}
						}
					}
				}
			}
			if(!$forked) {
				$not_forked_heights[] = $height;
			}
		}

		if($forked) {
//			_log("Corrected block map = ".json_encode($blocksMap, JSON_PRETTY_PRINT), 5);
		}

		$peersforSync = [];
		$peerSyncHeight = null;
		$peerSyncBlockId = null;

		_log("blocksMap:".print_r($blocksMap, 1));
//		return;

		foreach($blocksMap as $height => $blocks) {
			$current = Block::current();
			$block_id =  array_keys($blocks)[0];
			_log("Corrected block map: height=$height blocks=".count($blocks)." block_id=".$block_id, 3);
			_log("Check height $height block_id = $block_id", 5);
			if($height > $current['height']) {
				_log("Not top block - need sync", 5);
				$peersforSync = array_keys($blocks[$block_id]);
				$peerSyncHeight = $height;
				$peerSyncBlockId = $block_id;
				_log("peersforSync:".print_r($peersforSync,1));
				break;
			} else if ($height == $current['height']) {
				_log("Check top block id=".$current['id'], 5);
				if($block_id == $current['id']) {
					_log("Our top block is ok", 5);
				} else {
					_log("We have wrong top block $height - pop it", 5);
					Block::pop();
					break;
//					Config::setSync(0);
//					return;
				}
			} else {
				_log("Check not top block", 5);
				$block = Block::get($height);
				_log("Check block at height=$height our=".$block['id'], 5);
				if($block_id == $block['id']) {
					_log("Our block is ok", 5);
				} else {
					_log("We have wrong block $height - pop up to it", 5);
					$no = $current['height'] - $height + 1;
					Block::pop($no);
					break;
//					Config::setSync(0);
//					return;
				}
			}
		}

		$largest_height = max($not_forked_heights);

//		$largest_height = array_keys($blocksMap)[count($blocksMap)-1];

		$largest_height_block = array_keys($blocksMap[$largest_height])[0];

		$peerStats['largest_height']=$largest_height;
		$peerStats['current_height']=$current['height'];
		_log( "Longest chain height: $largest_height",5);


//		$peers = array_keys($blocksMap[$largest_height][$largest_height_block]);

		usort($peersforSync, function($p1, $p2) use ($peerResponseTimes) {
			$t1 = isset($peerResponseTimes[$p1]) ? $peerResponseTimes[$p1] : PHP_INT_MAX;
			$t2 = isset($peerResponseTimes[$p2]) ? $peerResponseTimes[$p2] : PHP_INT_MAX;
			return $t2 - $t1;
		});

//		Config::setSync(0);

//		if(count($peers)<=1) {
//			_log("Can not sync - peers <=1 ");
//			$hostname = $peers[0];
//			$peer = Peer::findByHostname($hostname);
//			if($peer) {
//				Peer::blacklist($peer['id'], "Single peer at height $largest_height");
//				_log("Blacklist peer with 1 block");
//			}
//		} else {
		$current = Block::current();
		$nodeSync = new NodeSync($peersforSync);
		if($largest_height > $current['height']) {
			_log("Start syncing to height $peerSyncHeight", 5);
			$nodeSync->start($peerSyncHeight, $peerSyncBlockId);
		} else {
			_log("Our blockchain is synced", 5);
		}
		$nodeSync->calculateNodeScore();
//		}


		Mempool::deleteOldMempool();

		//rebroadcasting local transactions
		if ($_config['sync_rebroadcast_locals'] == true && $_config['disable_repropagation'] == false) {
			$r = Mempool::getForRebroadcast($current['height']);
			_log("Rebroadcasting local transactions - ".count($r), 1);
			foreach ($r as $x) {
				Propagate::transactionToAll($x['id']);
			}
		}

		//rebroadcasting transactions
		if ($_config['disable_repropagation'] == false) {
			$forgotten = $current['height'] - $_config['sync_rebroadcast_height'];
			$r=Mempool::getForgotten($forgotten);

			_log("Rebroadcasting external transactions - ".count($r),1);

			foreach ($r as $x) {
				Propagate::transactionToAll($x['id']);
			}
		}


		Nodeutil::cleanTmpFiles();

		Minepool::deleteOldEntries();

//		Masternode::emptyList();

		Cache::clearOldFiles();

        $cmd='find '.ROOT.'/tmp -name "*.lock" -mmin +1 -exec rm -rf {} +';
        shell_exec($cmd);

		_log("Finishing sync",3);

		$t2 = microtime(true);

		_log("Sync process finished in time ".round($t2-$t1, 3));

	}

}
