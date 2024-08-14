<?php

require_once dirname(__DIR__) . "/apps.inc.php";
require_once ROOT . '/web/apps/explorer/include/functions.php';

session_start();

if(isset($_GET['auth_data'])) {
	$auth_data = json_decode(base64_decode($_GET['auth_data']), true);
	if($auth_data['request_code']==$_SESSION['request_code']) {
        $_SESSION['logged']=true;
        $_SESSION['account']=$auth_data['account'];
	}
    header("location: /apps/wallet2/index.php");
    exit;
}

$app_name = "Wallet2";
$request_code = uniqid();
$_SESSION['request_code']=$request_code;
$redirect_url = "/apps/wallet2/auth.php";
$gateway_auth_url = "/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/gateway/auth.php?app=$app_name&request_code=$request_code&redirect=$redirect_url";
header("location: $gateway_auth_url");
