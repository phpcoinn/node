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
		$session_data = @session_encode();
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

function dapps_redirect($location = null) {
	if(empty($location)) {
		$location = dapps_get_url();
	}
	$action = [
		"type"=>"redirect",
		"url"=>$location
	];
	echo "action:" . json_encode($action);
	exit;
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

function dapps_request($dapps_id, $url) {
	$request_code = uniqid();
	$_SESSION[$dapps_id.'_request_code']=$request_code;
	$action = [
		"type"=>"dapps_request",
		"request_code"=>"$request_code",
		"dapps_id"=>$dapps_id,
		"url"=>$url
	];
	echo "action:" . json_encode($action);
	exit;
}

function dapps_is_local() {
	return !empty($_SERVER['DAPPS_LOCAL']);
}

function dapps_config() {
	$config_file = ROOT . "/config/dapps.config.inc.php";
	global $dapps_config;
	require_once $config_file;
	return $dapps_config;
}

function dapps_exec($code) {
	if(!dapps_is_local()) {
		exit;
	}
	$action = [
		"type"=>"dapps_exec",
		"code"=>$code,
	];
	echo "action:" . json_encode($action);
	exit;
}

function dapps_get_random_peer() {
	$main_node = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'];
	$res = @file_get_contents($main_node."/peers.php");
	if($res === false) {
		throw new Exception("Can not contact peers node $main_node");
	}
	$res = trim($res);
	if(empty($res)) {
		throw new Exception("Empty response from peers node $main_node");
	}
	$arr = explode(PHP_EOL, trim($res));
	if(count($arr)==0) {
		throw new Exception("No peers");
	}
	$rand = rand(0, count($arr)-1);
	$rand_peer = $arr[$rand];
	if(empty($rand_peer)) {
		throw new Exception("Not found peer");
	}
	return $rand_peer;
}

function dapps_api($api=null, $node=null) {
	if(empty($node)) {
		$node = dapps_get_random_peer();
	}
	$res = file_get_contents($node. "/api.php?q=".$api);
	$res = json_decode($res, true);
	if($res['status']=="ok") {
		$data = $res['data'];
		return $data;
	} else {
		throw new Exception("Error response from API: ".$res['data']);
	}

}
