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
$block = new Block();
$acc = new Account();
set_time_limit(360);
$q = $_GET['q'];

$ip = Nodeutil::getRemoteAddr();
$ip = filter_var($ip, FILTER_VALIDATE_IP);

_log("Request from IP: ".$ip, 4);
global $_config;
if (!in_array($ip, $_config['allowed_hosts']) && !empty($ip) && !in_array(
    '*',
    $_config['allowed_hosts']
)) {
    api_err("unauthorized");
}

if ($q == "info") {
    // provides the mining info to the miner
    $diff = $block->difficulty();
    $current = $block->current();
    $current_height=$current['height'];
	$txn = new Transaction();
	$block = new Block();
	$data = $txn->mempool($block->max_transactions());
	$reward = Block::reward($current['height']+1);
    $res = [
        "difficulty" => $diff,
        "block"      => $current['id'],
        "height"     => $current['height'],
	    "date"=>$current['date'],
	    "data"=>$data,
	    "time"=>time(),
	    "reward"=>num($reward['miner']),
	    "version"=>VERSION_CODE
    ];
    api_echo($res);
    exit;
} elseif ($q == "submitBlock") {
//	_log("POSTDATA=".print_r($_POST, true));
    // in case the blocks are syncing, reject all
    if ($_config['sanity_sync'] == 1) {
        api_err("sanity-sync");
    }

    $peers = Peer::getCount(true);
    _log("Getting peers count = ".$peers);
    if($peers === 0) {
	    api_err("no-live-peers");
    }

    $nonce = san($_POST['nonce']);
	$version = VERSION_CODE;
    $public_key = san($_POST['public_key']);
	$elapsed = intval($_POST['elapsed']);
	$difficulty = san($_POST['difficulty']);
	$height = san($_POST['height']);
	$id = san($_POST['signature']);
	$argon = $_POST['argon'];

	_log("Submitted new block from miner $ip height=$height",4);

	$blockchainHeight = Block::getHeight();
	if($blockchainHeight != $height - 1) {
		api_err("rejected - not top block height=$height blockchainHeight=$blockchainHeight");
	}

	$now = time();
	$prev_block = $block->get($height-1);
	$date = $prev_block['date']+$elapsed;
	if(abs($date - $now) > 1) {
		api_err("rejected - date not match date=$date now=$now");
	}
    $result = $block->mine($public_key, $nonce, $argon, $difficulty, $id, $height, $date);

    if ($result) {
        if ($date <= $prev_block['date']) {
            api_err("rejected - date");
        }

        $current = $block->current();
        $height = $current['height'] += 1;
        $txn = new Transaction();

        $difficulty = $block->difficulty();
        $acc = new Account();
        $generator = Account::getAddress($public_key);

        $data=json_decode($_POST['data'], true);
           
        // sign the block
        $signature = san($_POST['signature']);

        // reward transaction and signature
        $reward_signature = san($_POST['reward_signature']);

        // add the block to the blockchain
        $res = $block->add(
            $height,
            $public_key,
            $nonce,
            $data,
            $date,
            $signature,
            $difficulty,
            $reward_signature,
            $argon,
	        $prev_block['id']
        );


        if ($res) {
            $current = $block->current();
            $current['id']=escapeshellarg(san($current['id']));
	        $dir = ROOT."/cli";
            $cmd = "php ".XDEBUG_CLI." $dir/propagate.php block {$current['id']}  > /dev/null 2>&1  &";
            _log("Call propagate " . $cmd);
            shell_exec($cmd);
            _log("Accepted block from miner $ip block_height=$height block_id=".$current['id'],3);
            api_echo("accepted");
        } else {
            api_err("rejected - add");
        }
    }
    api_err("rejected");
} else {
    api_err("invalid command");
}
