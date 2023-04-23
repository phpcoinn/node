<?php
if(php_sapi_name() !== 'cli') exit;
const DEFAULT_CHAIN_ID = "01";
if(Phar::running()) {
	require_once 'vendor/autoload.php';
} else {
	require_once dirname(__DIR__).'/vendor/autoload.php';
}

$node = @$argv[1];
$address = @$argv[2];
$cpu = @$argv[3];
$block_cnt = @$argv[4];

if(file_exists(getcwd()."/miner.conf")) {
	$minerConf = parse_ini_file(getcwd()."/miner.conf");
	$node = $minerConf['node'];
	$address = $minerConf['address'];
	$block_cnt = @$minerConf['block_cnt'];
	$cpu = @$minerConf['cpu'];
}

echo "PHPCoin Miner Version ".VERSION.".(".BUILD_VERSION.")".PHP_EOL;

if(empty($node) && empty($address)) {
	die("Usage: miner <node> <address> <cpu>");
}

if(empty($node)) {
	die("Node not defined");
}
if(empty($address)) {
	die("Address not defined");
}

$res = url_get($node . "/api.php?q=getPublicKey&address=".$address);
if(empty($res)) {
	die("No response from node");
}
$res = json_decode($res, true);
if(empty($res)) {
	die("Invalid response from node");
}
if(!($res['status']=="ok" && !empty($res['data']))) {
	die("Invalid response from node: ".json_encode($res));
}

$_config['enable_logging'] = true;
$_config['log_verbosity']=3;
$_config['log_file']="/dev/null";
$_config['chain_id'] = trim(file_exists(dirname(__DIR__)."/chain_id"));

define("ROOT", __DIR__);

$miner = new Miner($address, $node);
$miner->block_cnt = empty($block_cnt) ? 0 : $block_cnt;
$miner->cpu = empty($cpu) ? 0 : $cpu;
$miner->start();
