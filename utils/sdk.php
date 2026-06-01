<?php

if(php_sapi_name() !== 'cli') exit;
@define("CHAIN_ID", trim(@file_get_contents(dirname(__DIR__)."/chain_id")) ?: "00");
@define("ROOT", dirname(__DIR__));
require_once dirname(__DIR__).'/vendor/autoload.php';
