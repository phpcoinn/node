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

    $request_code=uniqid();
	$_SESSION['request_code']=$request_code;
    $redirect = urlencode("/dapps.php?url=".MAIN_DAPPS_ID."/wallet");

	header("location: /dapps.php?url=".MAIN_DAPPS_ID."/gateway/auth.php?login-link&public_key=$public_key&signature=$login_key&nonce=$login_code&request_code=$request_code&redirect=$redirect&app=LoginLink");
    exit;
}
