<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);
if (defined('STDOUT')) {
    stream_set_write_buffer(STDOUT, 0);
}

define("ROOT", dirname(dirname(dirname(__DIR__))));

$_SERVER = [];
$_ENV = [];
$_COOKIE = [];
$_FILES = [];
$_GET = [];
$_POST = [];
$_REQUEST = [];
if (isset($_SESSION)) {
    $_SESSION = [];
}


require_once ROOT . "/include/dapps.functions.php";