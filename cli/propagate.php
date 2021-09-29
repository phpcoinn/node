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
require_once dirname(__DIR__).'/include/init.inc.php';
$block = new Block();

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

_log("Calling propagate.php",4);
// broadcasting a block to all peers
if ((empty($peer) || $peer == 'all') && $type == "block") {
    $whr = "";
    if ($id == "current") {
        $current = $block->current();
        $id = $current['id'];
    }
    $data = $block->export($id);
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
        _log("Propagate cmd: $cmd",4);
        system( $cmd);
    }
    exit;
}


// broadcast a block to a single peer (usually a forked process from above)
if ($type == "block") {
    // current block or read cache
    if ($id == "current") {
        $current = $block->current();
        $data = $block->export($current['id']);
        if (!$data) {
            echo "Invalid Block data";
            exit;
        }
    } else {
    	$file = ROOT."/tmp/$id";
        $data = file_get_contents($file);
        if (empty($data)) {
            echo "Invalid Block data";
            exit;
        }
        $data = json_decode($data, true);
    }
    $hostname = base58_decode($peer);
    // send the block as POST to the peer
    _log("Block sent to $hostname:\n".print_r($data,1), 4);
    $response = peer_post($hostname."/peer.php?q=submitBlock", $data, 60, $debug);
    _log("Propagating block to $hostname - [result: ".json_encode($response)."] $data[height] - $data[id]",1);
    if ($response == "block-ok") {
        echo "Block $id accepted. Exiting.\n";
        exit;
    } elseif ($response['request'] == "microsync") {
        // the peer requested us to send more blocks, as it's behind
        echo "Microsync request\n";
        _log("Microsync request");
        $height = intval($response['height']);
        $bl = san($response['block']);
        $current = $block->current();
        // maximum microsync is 10 blocks, for more, the peer should sync
        if ($current['height'] - $height > 10) {
            echo "Height Differece too high\n";
            _log("Height Differece too high");
            exit;
        }
        $last_block = $block->get($height);
        // if their last block does not match our blockchain/fork, ignore the request
        if ($last_block['id'] != $bl) {
            echo "Last block does not match\n";
            _log("Last block does not match");
            exit;
        }
        echo "Sending the requested blocks\n";
	    _log("Sending the requested blocks");
        //start sending the requested block
        for ($i = $height + 1; $i <= $current['height']; $i++) {
            $data = $block->export("", $i);
            $response = peer_post($hostname."/peer.php?q=submitBlock", $data, 60, $debug);
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
        _log("Running microsync",3);
        _log("ip arg = ".$argv[4],3);
        $ip = trim($argv[4]);
        _log("tremmed ip = ".$ip,3);
        $ip = Peer::validateIp($ip);
        _log("Filtered ip=".$ip,3);
        if ($ip === false) {
            _log("Invalid IP",2);
            die("Invalid IP");
        }
        // fork a microsync in a new process
	    $dir = ROOT . "/cli";
        _log("caliing propagate: php $dir/sync.php microsync '$ip'  > /dev/null 2>&1  &",3);
        system("php $dir/sync.php microsync '$ip'  > /dev/null 2>&1  &");
    } else {
    	_log("Block not accepted ".$response,1);
        echo "Block not accepted!\n";
    }
}
// broadcast a transaction to some peers
if ($type == "transaction") {
	_log("Propagate transaction");
    $trx = new Transaction();
    // get the transaction data
    $data = $trx->export($id);

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
    _log("Transaction propagate peers: ".print_r($r, 1));
    if(count($r)==0) {
    	_log("Transaction not propagated - no peers");
    }
    foreach ($r as $x) {
    	$url = $x['hostname']."/peer.php?q=submitTransaction";
    	_log("Propagating to peer: ".$url);
        $res = peer_post($url, $data);
        if (!$res) {
	        _log("Transaction not accepted");
            echo "Transaction not accepted\n";
        } else {
	        _log("Transaction accepted");
            echo "Transaction accepted\n";
        }
    }
}

if($type == "apps") {
	_log("Propagating apps change");
	$peers = Peer::getActive();
	if(count($peers)==0) {
		_log("No peers to propagate");
	} else {
		foreach ($peers as $peer) {
			$url = $peer['hostname']."/peer.php?q=updateApps";
			_log("Propagating to peer: ".$url);
			$res = peer_post($url, ["hash"=>$argv[2]]);
		}
	}
}
