<?php

define("ROOT", dirname(__DIR__));
require_once ROOT . "/include/common.functions.php";
require_once ROOT . "/include/class/sc/SmartContractBase.php";
require_once ROOT . "/include/class/sc/SmartContractWrapper.php";
require_once ROOT . "/include/class/sc/SmartContractMap.php";

const CHAIN_PREFIX = "38";

if(!function_exists('_log')) {
    function _log($log)
    {
        $log_file = ROOT . "/tmp/sc/smart_contract.log";
        $log = @date("r") . " " .  getmypid() . " - " .  $log . PHP_EOL;
        file_put_contents($log_file, $log, FILE_APPEND);
    }
}


