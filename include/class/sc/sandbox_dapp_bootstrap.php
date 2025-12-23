<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

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