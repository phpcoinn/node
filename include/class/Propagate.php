<?php

class Propagate
{

	public const PROPAGATE_BY_FORKING = true;

	static function blockToAll($id) {
		_log("Propagate: block to all id=$id", 4);
		$id=escapeshellcmd(san($id));
		$dir = ROOT."/cli";
		$cmd= "php $dir/propagate.php block '$id' all";
		Nodeutil::runSingleProcess($cmd);
	}

	static function blockToPeer($hostname, $ip, $id) {
		_log("Propagate: block to peer $hostname id=$id",4);
		$host = escapeshellcmd(base58_encode($hostname));
		$ip = Peer::validateIp($ip);
		$ip = escapeshellcmd($ip);
		$id=escapeshellcmd(san($id));
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php block '$id' '$host' '$ip'";
		_log("Propagate: cmd: $cmd",5);
		Nodeutil::runSingleProcess($cmd);
	}

	static function masternode() {
		_log("Propagate: masternode",4);
		$dir = ROOT."/cli";
        if(DEVELOPMENT) {
		    $cmd = "(sleep 3 && php $dir/propagate.php masternode local)";
        } else {
            $cmd = "php $dir/propagate.php masternode local";
        }
		Nodeutil::runSingleProcess($cmd);
	}

	static function masternodeToPeer($peer) {
		_log("Propagate: masternode to peer $peer", 4);
		$peer = base64_encode($peer);
		$dir = ROOT."/cli";
		$cmd = "php $dir/propagate.php masternode $peer";
		Nodeutil::runSingleProcess($cmd);
	}

	static function transactionToAll($id) {
		_log("Propagate: transaction $id to all", 4);
		$dir = ROOT."/cli";
		$cmd ="php $dir/propagate.php transaction $id";
		Nodeutil::runSingleProcess($cmd);
	}

	static function transactionToPeer($id, $hostname) {
		$hostnameb64 = base64_encode($hostname);
		$dir = ROOT."/cli";
		$cmd = "php $dir/propagate.php transactionpeer $id $hostnameb64";
		_log("Propagate: transaction $id to peer $hostname cmd=$cmd", 4);
		Nodeutil::runSingleProcess($cmd);
	}

	static function appsToPeer($hostname, $hash) {
		if(!Nodeutil::isRepoServer()) {
			_log("Not repo server");
			return;
		}
		$hostnameb64 = base64_encode($hostname);
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php appspeer $hash $hostnameb64";
		_log("Propagate: transaction apps to peer $hostname cmd=$cmd", 4);
		Nodeutil::runSingleProcess($cmd);
	}

	static function appsToAll($appsHashCalc) {
		if(!Nodeutil::isRepoServer()) {
			_log("Not repo server");
			return;
		}
		_log("AppsHash: Propagating apps",3);
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php apps $appsHashCalc";
		Nodeutil::runSingleProcess($cmd);
	}

	static function messageToPeer($hostname, $msg) {
		$peer = base64_encode($hostname);
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php message $peer $msg";
		Nodeutil::runSingleProcess($cmd);
	}

	static function message($envelope) {
		$dir = ROOT . "/cli";
        $msg = base64_encode(json_encode($envelope));
		$cmd = "php $dir/propagate.php message $msg";
        _log("PM: call propagate command $cmd");
		Nodeutil::runProcess($cmd);
	}

	static function dappsLocal() {
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php dapps local";
		Nodeutil::runSingleProcess($cmd);
	}

	static function dappsToPeer($hostname) {
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php dapps $hostname";
		Nodeutil::runSingleProcess($cmd);
	}

	static function dappsUpdateToPeer($hostname, $dapps_id) {
		$peer = $hostname;
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php dapps-update $peer $dapps_id";
		Nodeutil::runSingleProcess($cmd);
	}

	static function processBlockPropagateResponse($hostname, $ip, $id, $response, $err) {
		//_log("processBlockPropagateResponse response=".json_encode($response));
		if ($response == "block-ok") {
			_log("Block $id accepted", 5);
		} elseif (is_array($response) && $response['request'] == "microsync") {
			// the peer requested us to send more blocks, as it's behind
			if(defined("FORKED_PROCESS")) {
				global $_config, $db;
				$db = new DB($_config['db_connect'], $_config['db_user'], $_config['db_pass'], $_config['enable_logging']);
			}
			$height = intval($response['height']);
			$bl = san($response['block']);
			$current = Block::current();
			_log("Microsync: Microsync request current_height=".$current['height']. " requested_height=".$height,1);
			// maximum microsync is 10 blocks, for more, the peer should sync
			if ($current['height'] - $height > 10) {
				_log("Microsync: Height Differece too high", 1);
				return;
			}
			$last_block = Block::get($height);
			// if their last block does not match our blockchain/fork, ignore the request
			if ($last_block['id'] != $bl) {
				_log("Microsync: Last block does not match", 1);
				return;
			}
			$peerInfo = Peer::getInfo();
			for ($i = $height + 1; $i <= $current['height']; $i++) {
				$data = Block::export("", $i);
				$data['microsync']=true;
				_log("Microsync: Sending  block height=$i to $hostname",2);
				$response = peer_post($hostname."/peer.php?q=submitBlock", $data, 30, $err, $peerInfo);
				if ($response != "block-ok") {
					_log("Microsync: Block $i not accepted. res=$response err=$err Exiting", 5);
					return;
				}
				_log("Microsync: Block\t$i\t accepted", 3);
			}
		} elseif ($response == "reverse-microsync") {
			// the peer informe us that we should run a microsync
			_log("Microsync: reverse",1);
			$ip2 = Peer::validateIp($ip);
			if ($ip2 === false) {
				_log("Microsync: Invalid IP $ip");
			}
			// fork a microsync in a new process
			$dir = ROOT . "/cli";
			_log("Microsync: caliing php $dir/microsync.php '$ip2'  > /dev/null 2>&1  &",3);
			system("php $dir/microsync.php '$ip2'  > /dev/null 2>&1  &");
		} else {
			_log("Microsync: Block not accepted response=".$response." err=".$err, 5);
		}
	}

    static function propagateEventAddedBlock($block) {
        $data['height']=$block->height;
        $data['id']=$block->id;
        _log("SOCKET: propagateEventAddedBlock data=".json_encode($data), 5);
        self::propagateSocketEvent("blockAdded", $data);
    }

    static function propagateSocketEvent($event, $data) {
//        $dir = ROOT . "/cli";
//        $data = base64_encode(json_encode($data));
//        $cmd = "php $dir/propagate.php socket $event $data";
//        Nodeutil::runSingleProcess($cmd);
    }

    static function propagateSocketEvent2($event, $data) {
        $dir = ROOT . "/cli";
        $data['ts']=microtime(true);
        $data = base64_encode(json_encode($data));
        $cmd = "php $dir/propagate.php socket $event $data";
        Nodeutil::runSingleProcess($cmd);
    }

    static function eventPropagate($dst, $requestId) {
        global $_config;
        $data['src']=$_config['hostname'];
        $data['dst']=$dst;
        $data['requestId']=$requestId;
        self::propagateSocketEvent("propagateBlock", $data);
    }

    static function eventPropagateComplete($requestId) {
        $data['requestId']=$requestId;
        self::propagateSocketEvent("propagateComplete", $data);
    }

    static function eventPostReceived($requestId) {
        $data['requestId']=$requestId;
        self::propagateSocketEvent("postReceived", $data);
    }
}
