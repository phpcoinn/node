<?php
/*
The MIT License (MIT)
Copyright (c) 2018 AroDev
Copyright (c) 2021 PHPCoin

phpcoin.net

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE
OR OTHER DEALINGS IN THE SOFTWARE.
*/
set_time_limit(30);
global $_config;

require_once dirname(__DIR__).'/include/init.inc.php';

$type = san($argv[1]);
$id = san($argv[2]);
$peer = trim($argv[3]);

_log("Calling propagate.php",5);
// broadcasting a block to all peers
if ((empty($peer) || $peer == 'all') && $type == "block") {
    $whr = "";
    if ($id == "current") {
        $current = Block::current();
        $id = $current['id'];
    }
    $data = Block::export($id);
    $id = san($id);
    if ($data === false || empty($data)) {
    	_log("Could not export block");
        die("Could not export block");
    }
//	$peers_limit = $_config['peers_limit'];
//	if(empty($peers_limit)) {
//		$peers_limit = 30;
//	}
//    $r = Peer::getPeersForSync($peers_limit);
	$r = Peer::getPeersForPropagate();

	if(Propagate::PROPAGATE_BY_FORKING) {
		_log("PropagateFork: start propagate block id=$id peer=$peer", 5);
		_log("PropagateFork: found ".count($r)." peeers", 5);
		global $_config, $db;
		$start = microtime(true);
		$info = Peer::getInfo();
		define("FORKED_PROCESS", getmypid());
        $db = null;
		foreach ($r as $peer) {
			$hostname = $peer['hostname'];
			$ip = $peer['ip'];
			if ($peer['hostname'] == $_config['hostname']) continue;
			$pid = pcntl_fork();
			if ($pid == -1) {
				die('could not fork');
			} else if ($pid == 0) {
				$cpid = getmypid();
				$response = peer_post($hostname . "/peer.php?q=submitBlock", $data, 5, $err, $info);
				_log("PropagateFork: forking child $cpid $hostname end response=".json_encode($response)." err=$err time=".(microtime(true) - $start),5);
				Propagate::processBlockPropagateResponse($hostname, $ip, $id, $response, $err);
				exit();
			}
		}
		while (pcntl_waitpid(0, $status) != -1) ;
		$db = new DB($_config['db_connect'], $_config['db_user'], $_config['db_pass'], $_config['enable_logging']);
		$key = "fork_".FORKED_PROCESS;
		$responses = Cache::get($key, []);
		foreach ($responses as $hostname => $connect_time) {
			Peer::storeResponseTime($hostname, $connect_time);
		}
		Cache::remove($key);
		_log("PropagateFork: Total time = ".(microtime(true)-$start),5);
		_log("PropagateFork: process " . getmypid() . " exit",5);
		exit;
	} else {
		Cache::set("block_export_$id", $data);
	    foreach ($r as $x) {
	        if($x['hostname']==$_config['hostname']) continue;
			Propagate::blockToPeer($x['hostname'], $x['ip'], $id);
	    }
	    exit;
	}


}


// broadcast a block to a single peer (usually a forked process from above)
if ($type == "block") {
	$ip = trim($argv[4]);
	_log("Propagate block $id peer=$peer ip=$ip", 5);
    // current block or read cache
    if ($id == "current") {
		$data = Cache::get("current_export", function () {
			$current = Block::current();
			return Block::export($current['id']);
		});
        if (!$data) {
	        _log("Invalid Block data $id", 5);
            echo "Invalid Block data";
            exit;
        }
    } else {
	    $data = Cache::get("block_export_$id", function() use ($id) {
			return Block::export($id);
	    });
        if (empty($data)) {
	        _log("Invalid Block data $id", 5);
            echo "Invalid Block data";
            exit;
        }
    }
	if(strpos($peer, "http")===0) {
		$hostname = $peer;
	} else {
        $hostname = base58_decode($peer);
	}
    // send the block as POST to the peer
    _log("Block sent to $hostname:\n".print_r($data,1), 5);
    $response = peer_post($hostname."/peer.php?q=submitBlock", $data, 30, $err);
    _log("Propagating block to $hostname - [result: ".json_encode($response)."] $data[height] - $data[id]",3);
	Propagate::processBlockPropagateResponse($hostname, $ip, $id, $response, $err);
}
// broadcast a transaction to some peers
if ($type == "transaction") {
	_log("Propagate transaction",3);
    // get the transaction data
    $data = Transaction::export($id);

	$peers_limit = $_config['peers_limit'];
	if(empty($peers_limit)) {
		$peers_limit = 30;
	}
	$r = Peer::getPeersForPropagate($peers_limit);
	_log("PropagateFork: Transaction propagate peers: ".count($r),3);

	if(Propagate::PROPAGATE_BY_FORKING) {
		global $db;
		$info = Peer::getInfo();
		$start = microtime(true);
		define("FORKED_PROCESS", getmypid());
        $db = null;
		$fork_tx_key = "tx_{$id}_res_".FORKED_PROCESS;
		foreach ($r as $peer) {
			$pid = pcntl_fork();
			if ($pid == -1) {
				die('could not fork');
			} else if ($pid == 0) {
				$hostname = $peer['hostname'];
				$cpid = getmypid();
				$url = $hostname."/peer.php?q=submitTransaction";
				$res = peer_post($url, $data, 5, $err, $info);
				_log("PropagateFork: forking child $cpid $hostname end response=$response time=".(microtime(true) - $start),5);
				$tx_responses = Cache::get($fork_tx_key, []);
				$tx_responses['count']++;
				if (!$res) {
					_log("Transaction $id to $hostname - Transaction not accepted: $err");
					$tx_responses['error']++;
				} else {
					_log("Transaction $id to $hostname - Transaction accepted",2);
					$tx_responses['ok']++;
				}
				Cache::set($fork_tx_key, $tx_responses);
				exit();
			}
		}
		while (pcntl_waitpid(0, $status) != -1) ;
		$db = new DB($_config['db_connect'], $_config['db_user'], $_config['db_pass'], $_config['enable_logging']);
		$key = "fork_".FORKED_PROCESS;
		$responses = Cache::get($key, []);
		foreach ($responses as $hostname => $connect_time) {
			Peer::storeResponseTime($hostname, $connect_time);
		}
		Cache::remove($key);
		$tx_responses = Cache::get($fork_tx_key);
		Cache::remove($fork_tx_key);
		$count = $tx_responses['count'];
		$error = $tx_responses['error'];
		if($error < 1/3 * $count) {
			$current = Block::current();
			Mempool::updateMempool($id, $current['height']);
		}
		_log("PropagateFork: Total time = ".(microtime(true)-$start),5);
		_log("PropagateFork: process " . getmypid() . " exit",5);
		exit;
	} else {
	    if (!$data) {
		    _log("Invalid transaction id");
	        echo "Invalid transaction id\n";
	        exit;
	    }
		Cache::set("tx_$id", $data);
	    if(count($r)==0) {
	        _log("Transaction not propagated - no peers");
	    }
		$dir = ROOT . "/cli";
	    foreach ($r as $x) {
			Propagate::transactionToPeer($id, $x['hostname']);
	    }
	}

}

if ($type == "transactionpeer") {
	$id = $argv[2];
	$hostname = $argv[3];
	$hostname = base64_decode($hostname);
	_log("Transaction $id propagate to peer $hostname",3);
	$url = $hostname."/peer.php?q=submitTransaction";
	$data = Cache::get("tx_$id", function () use ($id) {
		return Transaction::export($id);
	});
	if (!$data) {
		_log("Invalid transaction id");
		echo "Invalid transaction id\n";
		exit;
	}
	_log("PEER POST data=".json_encode($data));
	$res = peer_post($url, $data, 5, $err);
    if (!$res) {
        _log("Transaction $id to $hostname - Transaction not accepted: $err");
        echo "Transaction not accepted: $err\n";
    } else {
        _log("Transaction $id to $hostname - Transaction accepted",2);
        echo "Transaction accepted\n";
    }

}

if($type == "apps") {
	if(!Nodeutil::isRepoServer()) {
		_log("Not repo server");
		exit;
	}
	$hash = $argv[2];
	_log("PropagateApps: Propagating apps change",5);
	$peers = Peer::getAll();
	if(count($peers)==0) {
		_log("PropagateApps: No peers to propagate", 5);
	} else {
		foreach ($peers as $peer) {
			Propagate::appsToPeer($peer['hostname'], $hash);
		}
	}
}


if($type == "appspeer") {
	if(!Nodeutil::isRepoServer()) {
		_log("Not repo server");
		exit;
	}
	$hash = $argv[2];
	$hostname = $argv[3];
	$hostname = base64_decode($hostname);
	$url = $hostname."/peer.php?q=updateApps";
	$res = peer_post($url, ["hash"=>$hash]);
	_log("PropagateApps: propagate to peer $url res=".json_encode($res), 5);
}


if($type == "masternode") {
	Masternode::propagate($id);
}

if($type == "message") {
	$hash = $argv[2];
	$data = $argv[3];
	$hostname = base64_decode($hash);
	$url = $hostname."/peer.php?q=propagateMsg";
	$res = peer_post($url, ["data"=>$data], 30, $err);
	_log("Propagate message: propagate to peer $url res=".json_encode($res). " err=".json_encode($err), 5);
}

if($type == "dapps") {
	$hash = $argv[2];
	Dapps::propagate($hash);
}

if($type == "dappsupdate") {
	$hash = $argv[2];
	$id = $argv[3];
	Dapps::propagateDappsUpdate($hash, $id);
}

