<?php

if(!defined("ROOT")) {
	define("ROOT",dirname(__DIR__));

}

require_once ROOT . "/include/coinspec.inc.php";
require_once ROOT . "/include/common.functions.php";

/**
 * Function call all common functions for app work: dapps_get, dapps_post, dapps_get_session
 */
function dapps_init() {
	dapps_get();
	dapps_post();
	dapps_get_session();
}

/**
 * Reads data from query string and populate it in $_GET variable
 * @return array
 */
function dapps_get() {
	$get_data = $_SERVER['GET_DATA'];
	$_GET = json_decode(base64_decode($get_data), true);
	return $_GET;
}

/**
 * Reads data from POST request and populate $_POST variable
 * @return array
 */
function dapps_post() {
	$post_data = $_SERVER['POST_DATA'];
	$_POST = json_decode(base64_decode($post_data), true);
	return $_POST;
}

/**
 * Reads session from request and populate $_SESSION variable
 * @return string id of session
 */
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

/**
 * Redirect application to different url
 * @param null $location - location to redirect to. If empty it will redirect to current url
 */
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

/**
 * Destroys session
 */
function dapps_session_destroy() {
	$session_id = $_SERVER['SESSION_ID'];
	$sessions_dir = ROOT."/tmp/dapps/sessions";
	$file = "$sessions_dir/sess_$session_id";
	$_SESSION=null;
	unlink($file);
}

/**
 * Retrieves address of currently running dapp
 * @return mixed
 */
function dapps_get_id() {
	return $_SERVER['DAPPS_ID'];
}

/**
 * Retreives base url of currently running dapp
 * @param string $url - url to append to dapps base url. If not specified current url is returned
 * @return string
 */
function dapps_get_url($url = null, $full=false) {
	if($full) {
		$url = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST']."/dapps.php?url=".dapps_get_id().$url;
	} else {
		if(empty($url)) {
			$url = $_SERVER['PHP_SELF_BASE'];
		} else {
			$url = dapps_get_id() . $url;
		}
		$url = "/dapps.php?url=" . $url;
	}
	return $url;
}

function dapps_get_full_url() {
	return $_SERVER['DAPPS_FULL_URL'];
}

/**
 * Perform request to other dapp
 * @param $dapps_id - address of other dapp to call
 * @param $url - url on other app to call
 * @param $remote - contact only remote owner of dapps
 */
function dapps_request($dapps_id, $url, $remote=false) {
	$request_code = uniqid();
	$_SESSION[$dapps_id.'_request_code']=$request_code;
	$action = [
		"type"=>"dapps_request",
		"request_code"=>"$request_code",
		"dapps_id"=>$dapps_id,
		"url"=>$url,
		"remote"=>$remote
	];
	echo "action:" . json_encode($action);
	exit;
}

/**
 * Function check if running dapp is local, i.e. if it is called on node who is its owner
 * @return bool
 */
function dapps_is_local() {
	return !empty($_SERVER['DAPPS_LOCAL']);
}

/**
 * Reads dapp configuration file. Only works for local dapp.
 * Config file is stored on node in file: config/dapps.config.inc.php
 * @return array
 */
function dapps_config() {
	$config_file = ROOT . "/config/dapps.config.inc.php";
	global $dapps_config;
	require_once $config_file;
	return $dapps_config;
}

/**
 * Instructs dapp to execute some code in node environment. Only works for local calls
 * @param $code - code to execute
 */
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

function dapps_exec_fn($name, ...$params) {
	if(!dapps_is_local()) {
		exit;
	}
	$action = [
		"type"=>"dapps_exec_fn",
		"fn_name"=>$name,
		"params"=>$params
	];
	echo "action:" . json_encode($action);
	exit;
}

/**
 * Retrieves random peer from network
 * @return string - url of random peer
 * @throws Exception
 */
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

/**
 * Calls API function on network node
 * @param null $api - API string to call
 * @param null $node - URL of node to call. If empty node will be random
 * @return mixed - response from API
 * @throws Exception
 */
function dapps_api($api=null, $node=null, &$error = null) {
	if(empty($node)) {
		$node = dapps_get_random_peer();
	}
	$url = $node. "/api.php?q=".$api;
	$res = file_get_contents($url);
	$res = json_decode($res, true);
	if($res !== false && $res['status']=="ok") {
		$data = $res['data'];
		return $data;
	} else {
		$error = $res;
		return false;
	}

}

function dapps_json_response($data) {
	$action = [
		"type"=>"dapps_json_response",
		"data"=>$data
	];
	echo "action:" . json_encode($action);
	exit;
}

function dapps_response($content_type, $data) {
	$action = [
		"type"=>"dapps_response",
		"content_type"=>$content_type,
		"data"=>$data
	];
	echo "action:" . json_encode($action);
	exit;
}
