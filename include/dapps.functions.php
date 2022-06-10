<?php

if(!defined("ROOT")) {
	define("ROOT",dirname(__DIR__));

}

function dapps_init() {
	dapps_get();
	dapps_post();
	dapps_get_session();
}

function dapps_get() {
	$get_data = $_SERVER['GET_DATA'];
	$_GET = json_decode(base64_decode($get_data), true);
	return $_GET;
}

function dapps_post() {
	$post_data = $_SERVER['POST_DATA'];
	$_POST = json_decode(base64_decode($post_data), true);
	return $_POST;
}

function dapps_get_session() {

	$session_id = $_SERVER['SESSION_ID'];
	$sessions_dir = ROOT."/tmp/dapps/sessions";
	$file = "$sessions_dir/sess_$session_id";
	$contents = file_get_contents($file);
	session_start();
	session_decode($contents);

	register_shutdown_function(function () use ($file) {
		$session_data = session_encode();
		_log("Session data = $session_data store to file $file");
		file_put_contents($file, $session_data);
	});

	return $session_id;
}

if(!function_exists('_log')) {
	function _log($log)
	{
		$log_file = ROOT . "/tmp/dapps/dapps.log";
		$log = $log . PHP_EOL;
		file_put_contents($log_file, $log, FILE_APPEND);
	}
}

function dapps_redirect($location) {
	die("location: $location");
}

function dapps_session_destroy() {
	$session_id = $_SERVER['SESSION_ID'];
	$sessions_dir = ROOT."/tmp/dapps/sessions";
	$file = "$sessions_dir/sess_$session_id";
	$_SESSION=null;
	unlink($file);
}

function dapps_get_id() {
	return $_SERVER['DAPPS_ID'];
}

function dapps_get_url() {
	return "/dapps.php?url=" . $_SERVER['PHP_SELF_BASE'];
}

