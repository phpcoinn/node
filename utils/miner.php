<?php
if(php_sapi_name() !== 'cli') exit;
const DEFAULT_CHAIN_ID = "01";
const MINER_VERSION = "1.5";
if(Phar::running()) {
	require_once 'vendor/autoload.php';
} else {
	require_once dirname(__DIR__).'/vendor/autoload.php';
}

$node = @$argv[1];
$address = @$argv[2];
$cpu = @$argv[3];
$block_cnt = @$argv[4];

foreach ($argv as $item){
    if(strpos($item, "--threads")!==false) {
        $arr = explode("=", $item);
        $threads = $arr[1];
    }
}


if(file_exists(getcwd()."/miner.conf")) {
	$minerConf = parse_ini_file(getcwd()."/miner.conf");
	$node = $minerConf['node'];
	$address = $minerConf['address'];
	$block_cnt = @$minerConf['block_cnt'];
	$cpu = @$minerConf['cpu'];
    $threads = @$minerConf['threads'];
}

if(empty($threads)) {
    $threads=1;
}

$cpu = is_null($cpu) ? 50 : $cpu;
if($cpu > 100) $cpu = 100;

echo "PHPCoin Miner Version ".MINER_VERSION.PHP_EOL;
echo "Mining server:  ".$node.PHP_EOL;
echo "Mining address: ".$address.PHP_EOL;
echo "CPU:            ".$cpu.PHP_EOL;
echo "Threads:        ".$threads.PHP_EOL;


if(empty($node) && empty($address)) {
	die("Usage: miner <node> <address> <cpu>".PHP_EOL);
}

if(empty($node)) {
	die("Node not defined".PHP_EOL);
}
if(empty($address)) {
	die("Address not defined".PHP_EOL);
}

$res = url_get($node . "/api.php?q=getPublicKey&address=".$address);
if(empty($res)) {
	die("No response from node".PHP_EOL);
}
$res = json_decode($res, true);
if(empty($res)) {
	die("Invalid response from node".PHP_EOL);
}
if(!($res['status']=="ok" && !empty($res['data']))) {
	die("Invalid response from node: ".json_encode($res).PHP_EOL);
}

echo "Network:        ".$res['network'].PHP_EOL;

$_config['enable_logging'] = true;
$_config['log_verbosity']=0;
$_config['log_file']="/dev/null";
$_config['chain_id'] = trim(file_exists(dirname(__DIR__)."/chain_id"));

define("ROOT", __DIR__);

function startMiner($address,$node, $forked) {
    global $cpu;
    $miner = new Miner($address, $node, $forked);
    $miner->block_cnt = empty($block_cnt) ? 0 : $block_cnt;
    $miner->cpu = $cpu;
    $miner->start();
}

if($threads == 1) {
    startMiner($address,$node, false);
} else {
    $forker = new Forker();
    for($i=1; $i<=$threads; $i++) {
        $forker->fork(function() use ($address,$node) {
            startMiner($address,$node, true);
        });
    }
    $forker->exec();
}
