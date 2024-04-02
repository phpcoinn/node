<?php

define("ROOT", dirname(__DIR__));
require_once ROOT . "/include/common.functions.php";
require_once ROOT . "/include/class/sc/SmartContractBase.php";
require_once ROOT . "/include/class/sc/SmartContractWrapper.php";
require_once ROOT . "/include/class/sc/SmartContractMap.php";

const CHAIN_PREFIX = "38";
const TX_TYPE_SC_CREATE = 5;
const TX_TYPE_SC_EXEC = 6;
const TX_TYPE_SC_SEND = 7;
const UPDATE_15_EXTENDED_SC_HASH_V2=1117000;

if(!function_exists('_log')) {
    function _log($log)
    {
        $log_file = ROOT . "/tmp/sc/smart_contract.log";
        $date = function_exists('date') ? @date("r") : "";
        $log = $date. " " .  getmypid() . " - " .  $log . PHP_EOL;
        file_put_contents($log_file, $log, FILE_APPEND);
    }
}

if (defined('PHP_MAJOR_VERSION') && PHP_MAJOR_VERSION >= 8) {
    $disable_functions=get_sc_disable_functions();
    foreach (explode(",",$disable_functions) as $fn) {
        eval("if (!function_exists('$fn')) { function $fn() {}; };");
    }
}


