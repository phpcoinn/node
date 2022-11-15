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
global $db, $_config;

require_once dirname(__DIR__).'/include/init.inc.php';

$type = san($argv[1]);
$id = san($argv[2]);
$peer = san(trim($argv[3]));

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
	Cache::set("block_export_$id", $data);
	$peers_limit = $_config['peers_limit'];
	if(empty($peers_limit)) {
		$peers_limit = 30;
	}
    $r = Peer::getPeersForSync($peers_limit);
    foreach ($r as $x) {
        if($x['hostname']==$_config['hostname']) continue;
		Propagate::blockToPeer($x['ip'], $id);
    }
    exit;
}


// broadcast a block to a single peer (usually a forked process from above)
if ($type == "block") {
	$id = san($argv[2]);
	$ip = trim($argv[3]);
	_log("Propagate block $id", 5);
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
	$ip = base58_decode($ip);
	$hostname = Peer::getPeerUrl($ip);
    // send the block as POST to the peer
    _log("Block sent to $hostname:\n".print_r($data,1), 5);
    $response = peer_post($hostname."/peer.php?q=submitBlock", $data, 30, $err);
    _log("Propagating block to $hostname - [result: ".json_encode($response)."] $data[height] - $data[id]",3);
    if ($response == "block-ok") {
	    _log("Block $id accepted. Exiting", 5);
        echo "Block $id accepted. Exiting.\n";
        exit;
    } elseif ($response['request'] == "microsync") {
        // the peer requested us to send more blocks, as it's behind
        echo "Microsync request\n";
        _log("Microsync request",1);
        $height = intval($response['height']);
        $bl = san($response['block']);
        $current = Block::current();
        // maximum microsync is 10 blocks, for more, the peer should sync
        if ($current['height'] - $height > 10) {
            _log("Height Differece too high", 1);
            exit;
        }
        $last_block = Block::get($height);
        // if their last block does not match our blockchain/fork, ignore the request
        if ($last_block['id'] != $bl) {
            _log("Last block does not match", 1);
            exit;
        }
        echo "Sending the requested blocks\n";
	    _log("Sending the requested blocks",2);
        //start sending the requested block
        for ($i = $height + 1; $i <= $current['height']; $i++) {
            $data = Block::export("", $i);
            $response = peer_post($hostname."/peer.php?q=submitBlock", $data);
            if ($response != "block-ok") {
                echo "Block $i not accepted. Exiting.\n";
                _log("Block $i not accepted. Exiting", 5);
                exit;
            }
            _log("Block\t$i\t accepted", 3);
        }
    } elseif ($response == "reverse-microsync") {
        // the peer informe us that we should run a microsync
        echo "Running microsync\n";
        _log("Running microsync",1);
        $ip = Peer::validateIp($ip);
        _log("Filtered ip=".$ip,3);
        if ($ip === false) {
            _log("Invalid IP");
            die("Invalid IP");
        }
        // fork a microsync in a new process
	    $dir = ROOT . "/cli";
        _log("caliing propagate: php $dir/microsync.php '$ip'  > /dev/null 2>&1  &",3);
        system("php $dir/microsync.php '$ip'  > /dev/null 2>&1  &");
    } else {
    	_log("Block not accepted ".$response." err=".$err, 5);
        echo "Block not accepted!\n";
    }
}
// broadcast a transaction to some peers
if ($type == "transaction") {
	_log("Propagate transaction",3);
    // get the transaction data
    $data = Transaction::export($id);

    if (!$data) {
	    _log("Invalid transaction id");
        echo "Invalid transaction id\n";
        exit;
    }
	Cache::set("tx_$id", $data);

	$peers_limit = $_config['peers_limit'];
	if(empty($peers_limit)) {
		$peers_limit = 30;
	}
	$r = Peer::getPeersForSync($peers_limit);
    _log("Transaction propagate peers: ".count($r),3);
    if(count($r)==0) {
    	_log("Transaction not propagated - no peers");
    }
	$dir = ROOT . "/cli";
    foreach ($r as $x) {
		Propagate::transactionToPeer($id, $x['ip']);
    }
}

if ($type == "transactionpeer") {
	$id = $argv[2];
	$ip = $argv[3];
	$ip = base64_decode($ip);
	_log("Transaction $id propagate to peer $ip",3);
	$hostname = Peer::getPeerUrl($ip);
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
			Propagate::appsToPeer($peer['ip'], $hash);
		}
	}
}


if($type == "appspeer") {
	if(!Nodeutil::isRepoServer()) {
		_log("Not repo server");
		exit;
	}
	$hash = $argv[2];
	$ip = $argv[3];
	$ip = base64_decode($ip);
	$url = Peer::getPeerUrl($ip)."/peer.php?q=updateApps";
	$res = peer_post($url, ["hash"=>$hash]);
	_log("PropagateApps: propagate to peer $url res=".json_encode($res), 5);
}


if($type == "masternode") {
	$id = san($argv[2]);
	Masternode::propagate($id);
}

if($type == "message") {
	$ip = $argv[2];
	$data = $argv[3];
	$ip = base64_decode($ip);
	$url = Peer::getPeerUrl($ip)."/peer.php?q=propagateMsg";
	$res = peer_post($url, ["data"=>$data], 30, $err);
	_log("Propagate message: propagate to peer $url res=".json_encode($res). " err=".json_encode($err), 5);
}

if($type == "dapps") {
	$id = san($argv[2]);
	Dapps::propagate($id);
}

if($type == "dappsupdate") {
	$ip = $argv[2];
	$id = $argv[3];
	Dapps::propagateDappsUpdate($ip, $id);
}
