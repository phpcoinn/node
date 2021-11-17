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

function readGeneratorStat() {
	global $_config;
	$generator_stat_file = ROOT . '/tmp/generator-stat.json';
	if(file_exists($generator_stat_file)) {
		$generator_stat = json_decode(file_get_contents($generator_stat_file), true);
	} else {
		$generator_stat = [
			'address'=>Account::getAddress($_config['generator_public_key']),
			'started'=>time(),
			'submits' => 0,
			'accepted' => 0,
			'rejected' => 0,
			'reject-reasons'=>[]
		];
	}
	return $generator_stat;
}

function saveGeneratorStat($generator_stat) {
	$generator_stat_file = ROOT . '/tmp/generator-stat.json';
	file_put_contents($generator_stat_file, json_encode($generator_stat));
}

if ($q == "info") {
    $res = Blockchain::getMineInfo();
    api_echo($res);
    exit;
} elseif ($q == "stat") {
	$generator_stat = readGeneratorStat();
	api_echo($generator_stat);
	exit;
} elseif ($q == "submitHash") {

	$generator_stat = readGeneratorStat();

	$l="submitHash ip=$ip";

	$generator_stat['submits']++;

	if (empty($_config['generator'])) {
		$l.=" generator-disabled ";
		_log($l);
		$generator_stat['rejected']++;
		@$generator_stat['reject-reasons']['generator-disabled']++;
		saveGeneratorStat($generator_stat);
		api_err("generator-disabled");
	}

	$nodeScore = $_config['node_score'];
	if($nodeScore != 100) {
		$l.=" node-not-ok nodeScore=$nodeScore ";
		_log($l);
		$generator_stat['rejected']++;
		@$generator_stat['reject-reasons']['node-not-ok']++;
		saveGeneratorStat($generator_stat);
		api_err("node-not-ok");
	}

	if (empty($_config['generator_public_key']) && empty($_config['generator_private_key'])) {
		$l.=" generator-not-configured ";
		_log($l);
		$generator_stat['rejected']++;
		@$generator_stat['reject-reasons']['generator-not-configured']++;
		saveGeneratorStat($generator_stat);
		api_err("generator-not-configured");
	}

	$generator = Account::getAddress($_config['generator_public_key']);

	if ($_config['sync'] == 1) {
		$l.=" sync ";
		_log($l);
		$generator_stat['rejected']++;
		@$generator_stat['reject-reasons']['sync']++;
		saveGeneratorStat($generator_stat);
		api_err("sync");
	}

	$peers = Peer::getCount();
	_log("Getting peers count = " . $peers, 5);
	if ($peers < 3) {
		$l.=" no-live-peers ";
		_log($l);
		$generator_stat['rejected']++;
		@$generator_stat['reject-reasons']['no-live-peers']++;
		saveGeneratorStat($generator_stat);
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

	$l.=" height=$height address=$address elapsed=$elapsed";

	if($elapsed == 0 ) {
		$l.=" REQUEST=".json_encode($_REQUEST);
	}

	_log("Submitted new hash from miner $ip height=$height", 4);

	$blockchainHeight = Block::getHeight();
	if ($blockchainHeight != $height - 1) {
		$l.=" blockchainHeight=$blockchainHeight rejected - not top block";
		_log($l);
		$generator_stat['rejected']++;
		@$generator_stat['reject-reasons']['rejected - not top block']++;
		saveGeneratorStat($generator_stat);
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
		$l.=" rejected - no public key";
		_log($l);
		$generator_stat['rejected']++;
		@$generator_stat['reject-reasons']['rejected - no public key']++;
		saveGeneratorStat($generator_stat);
		api_err("rejected - no public key");
	}

	if ($date <= $prev_block['date']) {
		$l.=" rejected - date date=$date prev_block_date=".$prev_block['date'];
		_log($l);
		$generator_stat['rejected']++;
		@$generator_stat['reject-reasons']['rejected - date']++;
		saveGeneratorStat($generator_stat);
		api_err("rejected - date");
	}

	$lastBlock = Block::current();
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

	$signature = $block->sign($_config['generator_private_key']);
	$result = $block->mine();

	$l.=" mine=$result";

	if ($result) {
		$res = $block->add();
		$l.=" add=$res";
		if ($res) {
			$current = Block::current();
			$dir = ROOT . "/cli";
			$cmd = "php " . XDEBUG_CLI . " $dir/propagate.php block {$current['id']}  > /dev/null 2>&1  &";
			_log("Call propagate " . $cmd, 5);
			shell_exec($cmd);
			_log("Accepted block from miner $ip address=$address block_height=$height elapsed=$elapsed block_id=" . $current['id'], 3);
			$l.=" ACCEPTED";
			_log($l);
			$generator_stat['accepted']++;
			saveGeneratorStat($generator_stat);
			api_echo("accepted");
		} else {
			$l.=" REJECTED";
			_log($l);
			$generator_stat['rejected']++;
			@$generator_stat['reject-reasons']['rejected - add']++;
			saveGeneratorStat($generator_stat);
			api_err("rejected - add");
		}

	} else {
		$l.=" REJECTED";
		_log($l);
		$generator_stat['rejected']++;
		@$generator_stat['reject-reasons']['rejected - mine']++;
		saveGeneratorStat($generator_stat);
		api_err("rejected - mine");
	}
} else {
    api_err("invalid command");
}
