<?php

if(php_sapi_name() !== 'cli') exit;
if(Phar::running()) {
	require_once 'vendor/autoload.php';
} else {
	require_once dirname(__DIR__).'/vendor/autoload.php';
}
$wallet = new Wallet($argv);
