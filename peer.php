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
require_once __DIR__.'/include/init.inc.php';
header('Content-Type: application/json');

//$block = new Block();
$q = $_GET['q'];
// the data is sent as json, in $_POST['data']
if (!empty($_POST['data'])) {
    $data = json_decode(trim($_POST['data']), true);
}
global $_config;
if ($_POST['coin'] != COIN) {
    api_err("Invalid coin ".print_r($_REQUEST, 1));
}
$ip = Nodeutil::getRemoteAddr();
$requestId = $_POST['requestId'];
_log("Peer request from IP = $ip requestId=$requestId",4);

$ip = Peer::validateIp($ip);
_log("Filtered IP = $ip",4);

if(($ip === false || strlen($ip)==0)) {
	api_err("Invalid peer IP address",1);
}

// peer with the current node
if ($q == "peer") {
	_log("Received peer requst: ". json_encode($data),3);

	if(!Peer::validate($data['hostname'])) {
		api_err("invalid-hostname");
	}

    // sanitize the hostname
    $hostname = filter_var($data['hostname'], FILTER_SANITIZE_URL);
    $hostname = san_host($hostname);
	_log("Received peer request from $hostname",3);
    if($hostname === $_config['hostname']) {
	    api_err("self-peer");
    }

    // if it's already peered, only repeer on request
    $res = Peer::getSingle($hostname, $ip);
    if ($res == 1) {
    	_log("$hostname is already in peer db",3);
        if ($data['repeer'] == 1) {
            $res = peer_post($hostname."/peer.php?q=peer", ["hostname" => $_config['hostname']]);
            if ($res !== false) {
                api_echo("re-peer-ok");
            } else {
                api_err("re-peer failed - $res");
            }
        }
        api_echo("peer-ok-already");
    } else {
	    _log("$hostname is new peer",2);
    }
    // if we have enough peers, add it to DB as reserve
    $res = Peer::getCount(true);
    $reserve = 1;
    if ($res < $_config['max_peers']) {
        $reserve = 0;
    }
	_log("Inserting $hostname in peer db",3);
    $res = Peer::insert($ip, $hostname, $reserve);
	_log("Inserted $hostname = $res",3);
    // re-peer to make sure the peer is valid
	if ($data['repeer'] == 1) {
		_log("Repeer to $hostname",3);
		$res = peer_post($hostname . "/peer.php?q=peer", ["hostname" => $_config['hostname']]);
		_log("peer response " . print_r($res,1),3);
		if ($res !== false) {
			_log("Repeer OK",3);
			api_echo("re-peer-ok");
		} else {
			_log("Repeer FAILED - DELETING",3);
			if($ip) {
				Peer::deleteByIp($ip);
				api_err("re-peer failed - $res");
			} else {
				api_err("invalid peer ip");
			}
		}
	} else {
		api_echo("peer-ok");
	}
} elseif ($q == "ping") {
    // confirm peer is active
    api_echo("pong");
} elseif ($q == "submitTransaction") {
	global $db;
	_log("receive a new transaction from a peer");
	_log("data: ".json_encode($data));

	$tx = Transaction::getFromArray($data);
    // receive a new transaction from a peer
//    $current = $block->current();


    // no transactions accepted if the sync is running
    if ($_config['sync'] == 1) {
        api_err("sync");
    }

    // validate transaction data
    if (!$tx->_check()) {
        api_err("Invalid transaction");
    }
    $hash = $tx->id;
    // make sure it's not already in mempool
    $res = $db->single("SELECT COUNT(1) FROM mempool WHERE id=:id", [":id" => $hash]);
    if ($res != 0) {
        api_err("The transaction is already in mempool");
    }
    // make sure the peer is not flooding us with transactions
    $res = $db->single("SELECT COUNT(1) FROM mempool WHERE src=:src", [":src" => $tx->src]);
    if ($res > 25) {
        api_err("Too many transactions from this address in mempool. Please rebroadcast later.");
    }
    $res = $db->single("SELECT COUNT(1) FROM mempool WHERE peer=:peer", [":peer" => $ip]);
    if ($res > $_config['peer_max_mempool']) {
        api_err("Too many transactions broadcasted from this peer");
    }


    // make sure the transaction is not already on the blockchain
    $res = $db->single("SELECT COUNT(1) FROM transactions WHERE id=:id", [":id" => $hash]);
    if ($res != 0) {
        api_err("The transaction is already in a block");
    }
    // make sure the sender has enough balance
    $balance = $db->single("SELECT balance FROM accounts WHERE id=:id", [":id" => $tx->src]);
    if ($balance < $tx->val + $tx->fee) {
        api_err("Not enough funds");
    }

    // make sure the sender has enough pending balance
    $memspent = $db->single("SELECT SUM(val+fee) FROM mempool WHERE src=:src", [":src" => $tx->src]);
    if ($balance - $memspent < $tx->val + $tx->fee) {
        api_err("Not enough funds (mempool)");
    }
  
    // add to mempool
	$tx->_add_mempool($ip);

    // rebroadcast the transaction to some peers unless the transaction is smaller than the average size of transactions in mempool - protect against garbage data flooding
    $res = $db->row("SELECT COUNT(1) as c, sum(val) as v FROM  mempool ", [":src" => $tx->src]);
    if ($res['c'] < $_config['max_mempool_rebroadcast'] && $res['v'] / $res['c'] < $tx->val) {
        $dir = ROOT."/cli";
        system( "php $dir/propagate.php transaction '{$tx->id}'  > /dev/null 2>&1  &");
    }
    api_echo("transaction-ok");
} elseif ($q == "submitBlock") {
    // receive a  new block from a peer
	_log("Receive new block from a peer $ip : id=".$data['id']." height=".$data['height'],1);
    // if sync, refuse all
    if ($_config['sync'] == 1) {
        _log('['.$ip."] Block rejected due to sync");
        api_err("sync");
    }
    $data['id'] = san($data['id']);
    $current = Block::_current();
    // block already in the blockchain
    if ($current['id'] == $data['id']) {
        _log("block-ok",3);
        api_echo("block-ok");
    }
    if ($data['date'] > time() + 30) {
        _log("block in the future");
        api_err("block in the future");
    }

    if ($current['height'] == $data['height'] && $current['id'] != $data['id']) {
        // different forks, same height
        $accept_new = false;
        _log("DIFFERENT FORKS SAME HEOGHT", 3);
        _log("data ".json_encode($data),3);
        _log("current ".json_encode($current),3);

	        //wins block with lowest elapsed time - highest difficulty
		    $difficulty1 = $current['difficulty'];
		    $difficulty2 = $data['difficulty'];
		    if($difficulty1 > $difficulty2) {
			    $accept_new = true;
		    }

        if ($accept_new) {
            // if the new block is accepted, run a microsync to sync it
            _log('['.$ip."] Starting microsync - $data[height]",1);
            $ip=escapeshellarg($ip);
            $dir = ROOT."/cli";
            system(  "php $dir/sync.php microsync '$ip'  > /dev/null 2>&1  &");
            api_echo("microsync");
        } else {
            _log('['.$ip."] suggesting reverse-microsync - $data[height]",1);
            api_echo("reverse-microsync"); // if it's not, suggest to the peer to get the block from us
        }
    }
    // if it's not the next block
    if ($current['height'] != $data['height'] - 1) {
    	_log("block submitted is lower than our current height, send them our current block",1);
        // if the height of the block submitted is lower than our current height, send them our current block
        if ($data['height'] < $current['height']) {
            $pr = Peer::getByIp($ip);
            if (!$pr) {
                api_err("block-too-old");
            }
            $peer_host = escapeshellcmd(base58_encode($pr['hostname']));
            $pr['ip'] = escapeshellcmd(san_ip($pr['ip']));
	        $dir = ROOT."/cli";
            system( "php $dir/propagate.php block current '$peer_host' '$pr[ip]'   > /dev/null 2>&1  &");
            _log('['.$ip."] block too old, sending our current block - $data[height]",3);

            api_err("block-too-old");
        }
        // if the block difference is bigger than 150, nothing should be done. They should sync
        if ($data['height'] - $current['height'] > 150) {
            _log('['.$ip."] block-out-of-sync - $data[height]",2);
            api_err("block-out-of-sync");
        }
        // request them to send us a microsync with the latest blocks
        _log('['.$ip."] requesting microsync - $current[height] - $data[height]",2);
        api_echo(["request" => "microsync", "height" => $current['height'], "block" => $current['id']]);
    }
    // check block data
	$block = Block::getFromArray($data);
    if (!$block->_check()) {
        _log('['.$ip."] invalid block - $data[height]",1);
        api_err("invalid-block");
    }
    $b = $data;
    // add the block to the blockchain
	$block = Block::getFromArray($b);
	$block->prevBlockId = $current['id'];
	$res = $block->_add();
//    $res = $block->add(
//        $b['height'],
//        $b['public_key'],
//        $b['miner'],
//        $b['nonce'],
//        $b['data'],
//        $b['date'],
//        $b['signature'],
//        $b['difficulty'],
//        $b['argon'],
//	    $current['id']
//    );

    if (!$res) {
        _log('['.$ip."] invalid block data - $data[height]",1);
        api_err("invalid-block-data");
    }

    $last_block = Block::export("", $data['height']);
	$bl = Block::getFromArray($last_block);
    $res = $bl->_verifyBlock();

	if (!$res) {
		_log("Can not verify added block",1);
		api_err("invalid-block-data");
	}

    _log('['.$ip."] block ok, repropagating - $data[height]",1);

    // send it to all our peers
    $data['id']=escapeshellcmd(san($data['id']));
	$dir = ROOT."/cli";
    system("php $dir/propagate.php block '$data[id]' all all linear > /dev/null 2>&1  &");
    api_echo("block-ok");
} // return the current block, used in syncing
elseif ($q == "currentBlock") {
    $current = Block::_current();
    api_echo(["block"=>$current, "info"=>Peer::getInfo()]);
} // return a specific block, used in syncing
elseif ($q == "getBlock") {
    $height = intval($data['height']);
    $export = Block::export("", $height);
    if (!$export) {
        api_err("invalid-block");
    }
    api_echo($export);
} elseif ($q == "getBlocks") {
// returns X block starting at height,  used in syncing

    $height = intval($data['height']);

    $r = $db->run(
        "SELECT id,height FROM blocks WHERE height>=:height ORDER by height ASC LIMIT 100",
        [":height" => $height]
    );
    foreach ($r as $x) {
        $blocks[$x['height']] = Block::export($x['id']);
    }
    api_echo($blocks);
} elseif ($q == "getPeerBlocks") {
// returns X block starting at height,  used in syncing

    $height = intval($data['height']);
    $count = intval($data['count']);

    if(empty($count)) {
    	$count = 100;
    }

    if(empty($height)) {
	    $r = $db->run(
		    "SELECT id,height FROM blocks ORDER by height DESC LIMIT $count"
	    );
    } else {
	    $r = $db->run(
		    "SELECT id,height FROM blocks WHERE height < :height ORDER by height DESC LIMIT $count",
		    [":height" => $height]
	    );
    }

    foreach ($r as $x) {
        $blocks[$x['height']] = Block::export($x['id']);
    }
	$current = Block::_current();
	api_echo(["block"=>$current,"blocks"=>$blocks, "info"=>Peer::getInfo()]);
} // returns a full list of unblacklisted peers in a random order
elseif ($q == "getPeers") {
//	_log("Executing getPeers");
	$peers = Peer::getPeers();
//    _log("Response".print_r($peers,1));
	api_echo($peers);
} else if ($q == "getAppsHash") {
	$appsHashFile = Nodeutil::getAppsHashFile();
	$appsHash = file_get_contents($appsHashFile);
	api_echo($appsHash);
} elseif ($q == "getApps") {
	if ($_config['repository']) {
		_log("Received request getApps");
		$appsHashFile = Nodeutil::getAppsHashFile();
		$buildArchive = false;
		if (!file_exists($appsHashFile)) {
			$buildArchive = true;
			$appsHashCalc = calcAppsHash();
		} else {
			$appsHash = file_get_contents($appsHashFile);
			_log("Read apps hash from file = ".$appsHash);
			$appsHashTime = filemtime($appsHashFile);
			$now = time();
			$elapsed = $now - $appsHashTime;
			_log("Elapsed chaek time $elapsed");
			if ($elapsed > 60) {
				$appsHashCalc = calcAppsHash();
				_log("Calculated apps hash = ".$appsHashCalc);
				if ($appsHashCalc != $appsHash) {
					$buildArchive = true;
				}
			} else {
				$appsHashCalc = $appsHash;
			}
		}
		if ($buildArchive) {
			_log("build archive");
			file_put_contents($appsHashFile, $appsHashCalc);
			buildAppsArchive();
			$dir = ROOT . "/cli";
			_log("Propagating apps");
			system("php $dir/propagate.php apps $appsHashCalc > /dev/null 2>&1  &");
		} else {
			_log("No need to build archive");
		}
		$signature = ec_sign($appsHashCalc, $_config['repository_private_key']);
		api_echo(["hash" => $appsHashCalc, "signature" => $signature]);
	}
} else if ($q=="updateApps") {
	$hash = $data['hash'];
	$appsHashFile = Nodeutil::getAppsHashFile();
	$appsHash = file_get_contents($appsHashFile);
	_log("received update apps hash=$hash localHash=$appsHash",3);
	if($appsHash == $hash) {
		_log("No need to update apps",3);
	} else {
		$res = peer_post(APPS_REPO_SERVER."/peer.php?q=getApps");
		_log("Contancting repo server response=".json_encode($res),3);
		if($res === false) {
			_log("No response from repo server",2);
		} else {
			Nodeutil::downloadApps();
		}
	}
} else {
    api_err("Invalid request");
}
