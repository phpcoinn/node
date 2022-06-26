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

	static function process() {
		global $db, $_config;
		ini_set('memory_limit', '2G');
		$current = Block::current();
		$t = time();
		$t1 = microtime(true);
		_log("Starting sync",3);

		// update the last time sync ran, to set the execution of the next run
		$db->run("UPDATE config SET val=:time WHERE cfg='sync_last'", [":time" => $t]);
		$block_peers = [];
		$longest_size = 0;
		$longest = 0;
		$blocks = [];
		$blocks_count = [];
		$most_common = "";
		$most_common_size = 0;
		$most_common_height = 0;
		$total_active_peers = 0;
		$largest_most_common = "";
		$largest_most_common_size = 0;
		$largest_most_common_height = 0;

		// delete the dead peers
		Peer::deleteDeadPeers();

		$total_peers = Peer::getCount(false);
		_log("Total peers: ".$total_peers, 3);

		$peered = [];
		// if we have no peers, get the seed list from the official site
		if ($total_peers == 0) {
			$i = 0;
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
					$res=Peer::insert(md5($peer), $peer, 0);
				} else {
					// forces the other node to peer with us.
					$res = peer_post($peer."/peer.php?q=peer", ["hostname" => $_config['hostname'], "repeer" => 1], 30, $err);
				}
				if ($res !== false) {
					$i++;
					_log("Peering OK - $peer");
				} else {
					_log("Peering FAIL - $peer Error: $err");
				}
				if ($i > $_config['max_peers']) {
					break;
				}
			}
			// count the total peers we have
			$total_peers = Peer::getCountAll();
			if ($total_peers == 0) {
				// something went wrong, could not add any peers -> exit
				@rmdir(SYNC_LOCK_PATH);
				_log("There are no active peers");
				$db->setConfig('node_score', 0);
				_log("There are no active peers!\n");
				return;
			}
		}

		$i = 0;

		$peered = [];
		$peer_cnt = 0;

		$min = intval(date("i"));
		$run_get_more_peers = $min % 5 == 0;

		_log("Sync: check run_get_more_peers=$run_get_more_peers", 5);

		if($run_get_more_peers) {
			$dir = ROOT."/cli";
			$cmd = "$dir/util.php get-more-peers";
			$res = shell_exec("ps uax | grep '$cmd' | grep -v grep");
			if(!$res) {
				$exec_cmd = "php $cmd > /dev/null 2>&1  &";
				system($exec_cmd);
			}
		}

		NodeSync::recheckLastBlocks();

		$peers = Peer::getPeersForSync();
		$peerData = [];
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
		//	_log("PeerSync: add live peer data $hostname block_id=".$peer["block_id"]." height=".$peer["height"]);
		}
		$live_peers_count = count($peerData);
		//Then get all other peers
		$peers = Peer::getActive(100, true);
		//_log("PeerSync: syncing other peers ".count($peers));
		foreach($peers as $peer) {
			$hostname = $peer['hostname'];
			if(isset($peerData[$hostname])) {
				continue;
			}
			//	_log("PeerSync: Contacting peer $hostname");
			$url = $hostname."/peer.php?q=";
			$res = peer_post($url."currentBlock", [], 5);
			if ($res === false) {
				//		_log("Peer $hostname unresponsive url={$url}currentBlock response=$res");
				// if the peer is unresponsive, mark it as failed and blacklist it for a while
				Peer::blacklist($peer['id'],"Unresponsive");
				continue;
			}
			$data = $res['block'];
			$info = $res['info'];

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
		//	_log("PeerSync: add other peer data id=".$peer['id']." $hostname block_id=".$data["id"]." height=".$data["height"]);
		}

		$peers_count = count($peerData);

		$t1= microtime(true);
		$peerStats = [];

		//check all, but if ping is older contact peer
		foreach ($peerData as $hostname => $data) {

//			_log("PeerSync: check blacklist $hostname height=".$data['height']." id=".$data['id'],5);

			$peer = $data['peer'];
			if($current['height'] >= $data['height']) {
				$block = Block::get($data['height']);
				if(!empty($data['id']) && !empty($block['id']) && $block['id'] != $data['id']) {
//					_log("PeerSync: blacklist peer $hostname because of invalid block at height".$data['height']." my=".$block['id']." peer=".$data['id']);
//					Peer::blacklist($peer['id'], "Invalid block ".$data['height']);
					continue;
				}
			}


			if ($current['height'] > 1 && $data['height'] >1 && $data['height'] < $current['height'] - 100) {
				_log("PeerSync: blacklist peer $hostname because is 100 blocks behind, our height=".$current['height']." peer_height=".$data['height'],2);
				Peer::blacklistStuck($peer['id'],"100 blocks behind");
				continue;
			} else {
				if ($peer['stuckfail'] > 0) {
					Peer::clearStuck($peer['id']);
				}
			}

			$total_active_peers++;
			// add the hostname and block relationship to an array
			$block_peers[$data['id']][] = $hostname;
			// count the number of peers with this block id
			$blocks_count[$data['id']]++;
			// keep block data for this block id
			$blocks[$data['id']] = $data;
			// set the most common block on all peers
			if ($blocks_count[$data['id']] > $most_common_size) {
				$most_common = $data['id'];
				$most_common_size = $blocks_count[$data['id']];
				$most_common_height = $data['height'];
			}
			if ($blocks_count[$data['id']] > $largest_most_common_size && $data['height'] > $current['height']) {
				$largest_most_common = $data['id'];
				$largest_most_common_size = $blocks_count[$data['id']];
				$largest_most_common_height = $data['height'];
			}
			// set the largest height block
			if ($data['height'] > $largest_height) {
				$largest_height = $data['height'];
				$largest_height_block = $data['id'];
			} elseif ($data['height'] == $largest_height && $data['id'] != $largest_height_block) {
				// if there are multiple blocks on the largest height, choose one with the smallest (hardest) difficulty
				if ($data['difficulty'] == $blocks[$largest_height_block]['difficulty']) {
					// if they have the same difficulty, choose if it's most common
					if ($most_common == $data['id']) {
						$largest_height = $data['height'];
						$largest_height_block = $data['id'];
					} else {
						// if the blocks have the same number of transactions, choose the one with the highest derived integer from the first 12 hex characters
						$no1 = hexdec(substr(coin2hex($largest_height_block), 0, 12));
						$no2 = hexdec(substr(coin2hex($data['id']), 0, 12));
						if (gmp_cmp($no1, $no2) == 1) {
							$largest_height = $data['height'];
							$largest_height_block = $data['id'];
						}
					}
				} elseif ($data['difficulty'] > $blocks[$largest_height_block]['difficulty']) {
					// choose higher (hardest) difficulty
					$largest_height = $data['height'];
					$largest_height_block = $data['id'];
				}
			}

		}

		$largest_size=$blocks_count[$largest_height_block];

		$peerStats['most_common']=$most_common;
		$peerStats['most_common_size']=$most_common_size;
		$peerStats['most_common_height']=$most_common_height;
		$peerStats['largest_height']=$largest_height;
		$peerStats['largest_size']=$largest_size;
		$peerStats['largest_most_common']=$largest_most_common;
		$peerStats['largest_most_common_size']=$largest_most_common_size;
		$peerStats['largest_most_common_height']=$largest_most_common_height;
		$peerStats['total_active_peers']=$total_active_peers;
		$peerStats['current_height']=$current['height'];

		_log("Most common: $most_common", 5);
		_log( "Most common block size: $most_common_size",5);
		_log( "Most common height: $most_common_height",5);
		_log( "Longest chain height: $largest_height",5);
		_log( "Longest chain size: $largest_size",5);
		_log( "Larger Most common: $largest_most_common",5);
		_log( "Larger Most common block size: $largest_most_common_size",5);
		_log( "Larger Most common height: $largest_most_common_height",5);
		_log( "Total size: $total_active_peers",5);

		_log( "Current block: $current[height]",5);

		if($largest_height-$most_common_height>100&&$largest_size==1&&$current['id']==$largest_height_block){
			_log("Current node is alone on the chain and over 100 blocks ahead. Poping 200 blocks.");
			Block::pop(200);
			_log("Exiting sync, next will sync from 200 blocks ago.");

			Config::setSync(0);
			return;
		}

		// if there's a single node with over 100 blocks ahead on a single peer, use the most common block
		if($largest_height-$most_common_height>100 && $largest_size==1){
			if($current['id']==$most_common && $largest_most_common_size>3){
				_log("Longest chain is way ahead, using largest most common block");
				$largest_height=$largest_most_common_height;
				$largest_size=$largest_most_common_size;
				$largest_height_block=$largest_most_common;
			} else {
				_log("Longest chain is way ahead, using most common block");
				$largest_height=$most_common_height;
				$largest_size=$most_common_size;
				$largest_height_block=$most_common;
			}
		}

		$peers = $block_peers[$largest_height_block];
		if(is_array($peers)) {
			$peers_count = count($peers);
			shuffle($peers);
		}

		$nodeSync = new NodeSync($peers);
		$nodeSync->start($largest_height, $most_common);

		Mempool::deleteOldMempool();

		//rebroadcasting local transactions
		if ($_config['sync_rebroadcast_locals'] == true && $_config['disable_repropagation'] == false) {
			$r = Mempool::getForRebroadcast($current['height']);
			_log("Rebroadcasting local transactions - ".count($r), 1);
			foreach ($r as $x) {
				$x['id'] = escapeshellarg(san($x['id'])); // i know it's redundant due to san(), but some people are too scared of any exec
				$dir = __DIR__;
				system("php $dir/propagate.php transaction $x[id]  > /dev/null 2>&1  &");
				Mempool::updateMempool($x['id'], $current['height']);
			}
		}

		//rebroadcasting transactions
		if ($_config['disable_repropagation'] == false) {
			$forgotten = $current['height'] - $_config['sync_rebroadcast_height'];
			$r=Mempool::getForgotten($forgotten);

			_log("Rebroadcasting external transactions - ".count($r),1);

			foreach ($r as $x) {
				$x['id'] = escapeshellarg(san($x['id'])); // i know it's redundant due to san(), but some people are too scared of any exec
				$dir = __DIR__;
				system("php $dir/propagate.php transaction $x[id]  > /dev/null 2>&1  &");
				Mempool::updateMempool($x['id'], $current['height']);
			}
		}

		//add new peers if there aren't enough active
		if ($total_peers < $_config['max_peers'] * 0.7) {
			$res = $_config['max_peers'] - $total_peers;
			Peer::reserve($res);
		}

		//random peer check
		$r = Peer::getReserved(intval($_config['max_test_peers']));
		foreach ($r as $x) {
			$url = $x['hostname']."/peer.php?q=";
			$data = peer_post($url."ping", []);
			if ($data === false) {
				_log("blakclist peer ".$x['hostname']." because it is not answering", 4);
				Peer::blacklist($x['id'],"Not answer");
				_log("Random reserve peer test $x[hostname] -> FAILED");
			} else {
				_log("Random reserve peer test $x[hostname] -> OK",3);
				Peer::clearFails($x['id']);
			}
		}

		Nodeutil::cleanTmpFiles();

		Minepool::deleteOldEntries();

		_log("Finishing sync",3);

		$t2 = microtime(true);

		_log("Sync process finished in time ".round($t2-$t1, 3));

	}

}
