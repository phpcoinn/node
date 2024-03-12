<?php
require_once dirname(__DIR__)."/apps.inc.php";

if(isset($_GET['action']) && $_GET['action']=="login-link") {
	$login_code = $_GET['login_code'];
	$public_key = $_GET['public_key'];
	$login_key = $_GET['login_key'];

	if(empty($login_code) || empty($public_key) || empty($login_key)) {
		$_SESSION['msg']=[['icon'=>'warning', 'text'=>'Invalid data received']];
		header("location: /dapps.php?url=".MAIN_DAPPS_ID."/wallet");
		exit;
	}

    $issuer="";
    if(isset($_GET['issuer'])) {
        $issuer="?issuer=".$_GET['issuer'];
    }

    $request_code=uniqid();
	$_SESSION['request_code']=$request_code;
    if(isset($_GET['redirect'])) {
        $redirect = $_GET['redirect'];
    } else {
        $redirect = urlencode("/dapps.php?url=".MAIN_DAPPS_ID."/wallet".$issuer);
    }

	header("location: /dapps.php?url=".MAIN_DAPPS_ID."/gateway/auth.php?login-link&public_key=$public_key&signature=$login_key&nonce=$login_code&request_code=$request_code&redirect=$redirect&app=LoginLink");
    exit;
}
