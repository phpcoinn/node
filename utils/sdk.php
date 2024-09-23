<?php

if(php_sapi_name() !== 'cli') exit;
@define("DEFAULT_CHAIN_ID", file_get_contents(dirname(__DIR__)."/chain_id"));
@define("ROOT", dirname(__DIR__));
require_once dirname(__DIR__).'/vendor/autoload.php';