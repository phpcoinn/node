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
set_time_limit(360);
global $db, $_config;

define("SKIP_MASTERNODE_THREAD", true);

require_once dirname(__DIR__).'/include/init.inc.php';

$type = san($argv[1]);
$id = san($argv[2]);
$debug = false;
$linear = false;
// if debug mode, all data is printed to console, no background processes
if (trim($argv[5]) == 'debug') {
    $debug = true;
}
if (trim($argv[5]) == 'linear') {
    $linear = true;
}
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
    $data = json_encode($data);
    // cache it to reduce the load
	$file = ROOT."/tmp/$id";
    $res = file_put_contents($file, $data);
    if ($res === false) {
	    _log("Could not write the cache file");
        die("Could not write the cache file");
    }
    $r = Peer::getPeersForPropagate($linear);
    foreach ($r as $x) {
        if($x['hostname']==$_config['hostname']) continue;
        // encode the hostname in base58 and sanitize the IP to avoid any second order shell injections
        $host = escapeshellcmd(base58_encode($x['hostname']));
        $ip = Peer::validateIp($x['ip']);
        $ip = escapeshellcmd($ip);
        // fork a new process to send the blocks async
        $type=escapeshellcmd(san($type));
        $id=escapeshellcmd(san($id));
       

        $dir = ROOT . "/cli";
        if ($debug) {
            $cmd = "php $dir/propagate.php '$type' '$id' '$host' '$ip' debug";
        } elseif ($linear) {
	        $cmd = "php $dir/propagate.php '$type' '$id' '$host' '$ip' linear";
        } else {
	        $cmd = "php $dir/propagate.php '$type' '$id' '$host' '$ip'  > /dev/null 2>&1  &";
        }
        _log("Propagate cmd: $cmd",3);
        system( $cmd);
    }
    exit;
}


// broadcast a block to a single peer (usually a forked process from above)
if ($type == "block") {
	_log("Propagate block $id", 5);
    // current block or read cache
    if ($id == "current") {
        $current = Block::current();
        $data = Block::export($current['id']);
        if (!$data) {
	        _log("Invalid Block data $id", 5);
            echo "Invalid Block data";
            exit;
        }
    } else {
    	$file = ROOT."/tmp/$id";
        $data = file_get_contents($file);
        if (empty($data)) {
	        _log("Invalid Block data $id", 5);
            echo "Invalid Block data";
            exit;
        }
        $data = json_decode($data, true);
    }
    $hostname = base58_decode($peer);
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
            echo "Height Differece too high\n";
            _log("Height Differece too high");
            exit;
        }
        $last_block = Block::get($height);
        // if their last block does not match our blockchain/fork, ignore the request
        if ($last_block['id'] != $bl) {
            echo "Last block does not match\n";
            _log("Last block does not match");
            exit;
        }
        echo "Sending the requested blocks\n";
	    _log("Sending the requested blocks",1);
        //start sending the requested block
        for ($i = $height + 1; $i <= $current['height']; $i++) {
            $data = Block::export("", $i);
            $response = peer_post($hostname."/peer.php?q=submitBlock", $data);
            if ($response != "block-ok") {
                echo "Block $i not accepted. Exiting.\n";
                _log("Block $i not accepted. Exiting");
                exit;
            }
            _log("Block\t$i\t accepted");
            echo "Block\t$i\t accepted\n";
        }
    } elseif ($response == "reverse-microsync") {
        // the peer informe us that we should run a microsync
        echo "Running microsync\n";
        _log("Running microsync",1);
        $ip = trim($argv[4]);
        $ip = Peer::validateIp($ip);
        _log("Filtered ip=".$ip,3);
        if ($ip === false) {
            _log("Invalid IP");
            die("Invalid IP");
        }
        // fork a microsync in a new process
	    $dir = ROOT . "/cli";
        _log("caliing propagate: php $dir/sync.php microsync '$ip'  > /dev/null 2>&1  &",3);
        system("php $dir/sync.php microsync '$ip'  > /dev/null 2>&1  &");
    } else {
    	_log("Block not accepted ".$response." err=".$err);
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
    // if the transaction was first sent locally, we will send it to all our peers, otherwise to just a few
    if ($data['peer'] == "local") {
        $r = Peer::getPeers();
    } else {
        $r = Peer::getActive(intval($_config['transaction_propagation_peers']));
    }
    _log("Transaction propagate peers: ".print_r($r, 1),3);
    if(count($r)==0) {
    	_log("Transaction not propagated - no peers");
    }
    foreach ($r as $x) {
    	$url = $x['hostname']."/peer.php?q=submitTransaction";
    	_log("Propagating to peer: ".$url,2);
        $res = peer_post($url, $data, 30, $err);
        if (!$res) {
	        _log("Transaction not accepted: $err");
            echo "Transaction not accepted: $err\n";
        } else {
	        _log("Transaction accepted",2);
            echo "Transaction accepted\n";
        }
    }
}

if($type == "apps") {
	_log("Propagating apps change",3);
	$peers = Peer::getAll();
	if(count($peers)==0) {
		_log("No peers to propagate");
	} else {
		foreach ($peers as $peer) {
			$url = $peer['hostname']."/peer.php?q=updateApps";
			_log("Propagating to peer: ".$url,3);
			$res = peer_post($url, ["hash"=>$argv[2]]);
		}
	}
}


if($type == "masternode") {
	Masternode::propagate($id);
}
