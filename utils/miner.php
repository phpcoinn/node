<?php
if(php_sapi_name() !== 'cli') exit;
if(Phar::running()) {
	require_once 'vendor/autoload.php';
} else {
	require_once dirname(__DIR__).'/vendor/autoload.php';
}

$node = @$argv[1];
$public_key = @$argv[2];
$private_key = @$argv[3];
$block_cnt = @$argv[4];

if(file_exists(getcwd()."/miner.conf")) {
	$minerConf = parse_ini_file(getcwd()."/miner.conf");
	$node = $minerConf['node'];
	$public_key = $minerConf['public_key'];
	$private_key = $minerConf['private_key'];
	$block_cnt = $minerConf['block_cnt'];
}

if(empty($node)) {
	die("Node not defined");
}
if(empty($public_key)) {
	die("Miner public key not defined");
}
if(empty($private_key)) {
	die("Miner private key not defined");
}

$_config['enable_logging'] = true;
$_config['log_verbosity']=3;
$_config['log_file']="/dev/null";

$miner = new Miner($public_key, $private_key, $node);
$miner->block_cnt = empty($block_cnt) ? 0 : $block_cnt;
$miner->start();
