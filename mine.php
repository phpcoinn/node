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
    $res = Blockchain::getMineInfo();
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
    if($peers === 0 && !DEVELOPMENT) {
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
	$miner = $_POST['miner'];

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

	if ($date <= $prev_block['date']) {
		api_err("rejected - date");
	}

    $result = $block->mine($public_key, $miner, $nonce, $argon, $difficulty, $id, $height, $date);

    if ($result) {

        $current = $block->current();
        $height = $current['height'] += 1;
        $txn = new Transaction();

        $difficulty = $block->difficulty();
        $acc = new Account();
        $generator = Account::getAddress($public_key);

        $data=json_decode($_POST['data'], true);
           
        // sign the block
        $signature = san($_POST['signature']);

        // add the block to the blockchain
        $res = $block->add(
            $height,
            $public_key,
            $miner,
            $nonce,
            $data,
            $date,
            $signature,
            $difficulty,
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
} elseif ($q == "submitHash") {
	//TODO: not working ok - must set generator to node who mined !!!
	if (empty($_config['generator'])) {
		api_err("generator-disabled");
	}

	$nodeScore = $_config['node_score'];
	if($nodeScore != 100) {
		api_err("node-not-ok");
	}

	if (empty($_config['generator_public_key']) && empty($_config['generator_private_key'])) {
		api_err("generator-not-configured");
	}

	$generator = Account::getAddress($_config['generator_public_key']);
//	$generator_public_key = Account::publicKey($generator);
//	if (empty($generator_public_key)) {
//		api_err("rejected - no public key for generator");
//	}

	if ($_config['sanity_sync'] == 1) {
		api_err("sanity-sync");
	}

	$peers = Peer::getCount();
	_log("Getting peers count = " . $peers);
	if ($peers < 3) {
		api_err("no-live-peers");
	}

	$nonce = san($_POST['nonce']);
	$version = VERSION_CODE;
	$address = san($_POST['address']);
	$elapsed = intval($_POST['elapsed']);
	$difficulty = san($_POST['difficulty']);
	$height = san($_POST['height']);
	$argon = $_POST['argon'];
	$data=json_decode($_POST['data'], true);

	_log("Submitted new hash from miner $ip height=$height", 4);

	$blockchainHeight = Block::getHeight();
	if ($blockchainHeight != $height - 1) {
		api_err("rejected - not top block height=$height blockchainHeight=$blockchainHeight");
	}

	$now = time();
	$prev_block = $block->get($height - 1);
	$date = $prev_block['date'] + $elapsed;
	if (abs($date - $now) > 1 && false) {
		api_err("rejected - date not match date=$date now=$now");
	}

	$public_key = Account::publicKey($address);
	if (empty($public_key)) {
		api_err("rejected - no public key");
	}

	if ($date <= $prev_block['date']) {
		api_err("rejected - date");
	}

	$tx = new Transaction();
	$lastBlock = $block->current();
	$block_date = $lastBlock['date'];
	$new_block_date = $block_date + $elapsed;
	$rewardInfo = Block::reward($height);
	$minerReward = num($rewardInfo['miner']);
	$reward_tx = $tx->getRewardTransaction($address, $new_block_date, $_config['generator_public_key'], $_config['generator_private_key'], $minerReward);
	$data[$reward_tx['id']] = $reward_tx;

	$generatorReward = num($rewardInfo['generator']);
	$reward_tx = $tx->getRewardTransaction($generator, $new_block_date, $_config['generator_public_key'], $_config['generator_private_key'], $generatorReward);
	$data[$reward_tx['id']] = $reward_tx;

	ksort($data);
	$prev_block_id = $lastBlock['id'];
	$signature = $block->sign($generator, $address, $height, $new_block_date, $nonce, $data, $_config['generator_private_key'], $difficulty, $argon, $prev_block_id);

	$result = $block->mine($public_key, $address, $nonce, $argon, $difficulty, $signature, $height, $date);

	if ($result) {

		$res = $block->add(
			$height,
			$_config['generator_public_key'],
			$address,
			$nonce,
			$data,
			$date,
			$signature,
			$difficulty,
			$argon,
			$prev_block['id']
		);

		if ($res) {
			$current = $block->current();
			$current['id'] = escapeshellarg(san($current['id']));
			$dir = ROOT . "/cli";
			$cmd = "php " . XDEBUG_CLI . " $dir/propagate.php block {$current['id']}  > /dev/null 2>&1  &";
			_log("Call propagate " . $cmd);
			shell_exec($cmd);
			_log("Accepted block from miner $ip block_height=$height block_id=" . $current['id'], 3);
			api_echo("accepted");
		} else {
			api_err("rejected - add");
		}

	} else {
		api_err("rejected - mine");
	}
} else if ($q=="checkAddress") {
	if (!isset($_POST['address'])) {
		api_err("address-not-specified");
	}
	$address = $_POST['address'];
	Account::publicKey($address);
} else {
    api_err("invalid command");
}
