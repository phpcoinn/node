<?php

if(php_sapi_name() !== 'cli') exit;
const DEFAULT_CHAIN_ID = "00";
if(Phar::running()) {
	require_once 'vendor/autoload.php';
} else {
	require_once dirname(__DIR__).'/vendor/autoload.php';
}
$wallet = new Wallet($argv);
