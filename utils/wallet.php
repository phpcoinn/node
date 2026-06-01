<?php

if(php_sapi_name() !== 'cli') exit;
$chain_id = trim(file_get_contents(dirname(__DIR__)."/chain_id")) ?? "00";
define("DEFAULT_CHAIN_ID", $chain_id);
if(Phar::running()) {
	require_once 'vendor/autoload.php';
} else {
	require_once dirname(__DIR__).'/vendor/autoload.php';
}
$wallet = new Wallet($argv);
