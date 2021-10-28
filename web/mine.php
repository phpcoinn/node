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
require_once dirname(__DIR__).'/include/init.inc.php';
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
} elseif ($q == "submitHash") {
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

	if ($_config['sync'] == 1) {
		api_err("sync");
	}

	$peers = Peer::getCount();
	_log("Getting peers count = " . $peers, 5);
	if ($peers < 3) {
		api_err("no-live-peers");
	}

	$nonce = san($_POST['nonce']);
	$version = Block::versionCode();
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
	$prev_block = Block::get($height - 1);
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

	$lastBlock = Block::_current();
	$block_date = $lastBlock['date'];
	$new_block_date = $block_date + $elapsed;
	$rewardInfo = Block::reward($height);
	$minerReward = num($rewardInfo['miner']);
	$reward_tx = Transaction::getRewardTransaction($address, $new_block_date, $_config['generator_public_key'], $_config['generator_private_key'], $minerReward);
	$data[$reward_tx['id']] = $reward_tx;

	$generatorReward = num($rewardInfo['generator']);
	$reward_tx = Transaction::getRewardTransaction($generator, $new_block_date, $_config['generator_public_key'], $_config['generator_private_key'], $generatorReward);
	$data[$reward_tx['id']] = $reward_tx;

	ksort($data);
	$prev_block_id = $lastBlock['id'];

	$block = new Block($generator, $address, $height, $date, $nonce, $data, $difficulty, $version, $argon, $prev_block['id']);
	$block->publicKey = $_config['generator_public_key'];

	$signature = $block->_sign($_config['generator_private_key']);
	$result = $block->_mine();

	if ($result) {
		$res = $block->_add();

		if ($res) {
			$current = Block::_current();
			$dir = ROOT . "/cli";
			$cmd = "php " . XDEBUG_CLI . " $dir/propagate.php block {$current['id']}  > /dev/null 2>&1  &";
			_log("Call propagate " . $cmd, 5);
			shell_exec($cmd);
			_log("Accepted block from miner $ip block_height=$height block_id=" . $current['id'], 3);
			api_echo("accepted");
		} else {
			api_err("rejected - add");
		}

	} else {
		api_err("rejected - mine");
	}
} else {
    api_err("invalid command");
}
