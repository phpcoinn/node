<?php
// UTC timezone by default
date_default_timezone_set("UTC");
require_once dirname(__DIR__).'/vendor/autoload.php';

 error_reporting(E_ALL & ~E_NOTICE);
//error_reporting(0);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
// not accessible directly
if (php_sapi_name() !== 'cli' && substr_count($_SERVER['PHP_SELF'], "/") > 1
	&& substr($_SERVER['PHP_SELF'], 0, 5) != "/apps") {
    die("This application should only be run in the main directory /");
}

define("ROOT", dirname(__DIR__));

$config_file = ROOT.'/config/config.inc.php';

require_once $config_file;
require_once __DIR__.'/db.inc.php';
global $_config;

@include_once ROOT.'/apps/apps.functions.php';

if ($_config['db_pass'] == "ENTER-DB-PASS") {
    die("Please update your config file and set your db password");
}
// initial DB connection
$db = new DB($_config['db_connect'], $_config['db_user'], $_config['db_pass'], $_config['enable_logging']);
if (!$db) {
    die("Could not connect to the DB backend.");
}

// checks for php version and extensions
if (!extension_loaded("openssl") && !defined("OPENSSL_KEYTYPE_EC")) {
    api_err("Openssl php extension missing");
}
if (!extension_loaded("gmp")) {
    api_err("gmp php extension missing");
}
if (!extension_loaded('PDO')) {
    api_err("pdo php extension missing");
}
if (!extension_loaded("bcmath")) {
    api_err("bcmath php extension missing");
}
if (!extension_loaded("curl")) {
    api_err("curl php extension missing");
}
if (!defined("PASSWORD_ARGON2I")) {
    api_err("The php version is not compiled with argon2i support");
}

if (floatval(phpversion()) < 7.2) {
    api_err("The minimum php version required is 7.2");
}

// Getting extra configs from the database
$query = $db->run("SELECT cfg, val FROM config");
if(is_array($query)) {
	foreach ($query as $res) {
	    $_config[$res['cfg']] = trim($res['val']);
	}
}

//check db update
_log("checking schema update", 4);
require_once __DIR__.'/schema.inc.php';

// nothing is allowed while in maintenance
if ($_config['maintenance'] == 1) {
    api_err("under-maintenance");
}

$db_update_file = dirname(__DIR__)."/tmp/db-update";

// update the db schema, on every git pull or initial install
if (file_exists($db_update_file)) {
    //checking if the server has at least 2GB of ram
    $ram=file_get_contents("/proc/meminfo");
    $ramz=explode("MemTotal:",$ram);
    $ramb=explode("kB",$ramz[1]);
    $ram=intval(trim($ramb[0]));
    if($ram<1700000) {
        die("The node requires at least 2 GB of RAM");
    }
    $res = unlink($db_update_file);
    if ($res) {
        echo "Updating db schema! Please refresh!\n";
        require_once __DIR__.'/schema.inc.php';
        exit;
    }
    echo "Could not access the tmp/db-update file. Please give full permissions to this file\n";
}

// current hostname
$hostname = (!empty($_SERVER['HTTPS']) ? 'https' : 'http')."://".san_host($_SERVER['HTTP_HOST']);
// set the hostname to the current one
if ($hostname != $_config['hostname'] && $_SERVER['HTTP_HOST'] != "localhost" && $_SERVER['HTTP_HOST'] != "127.0.0.1" && $_SERVER['hostname'] != '::1' && php_sapi_name() !== 'cli' && ($_config['allow_hostname_change'] != false || empty($_config['hostname']))) {
    $db->run("UPDATE config SET val=:hostname WHERE cfg='hostname' LIMIT 1", [":hostname" => $hostname]);
    $_config['hostname'] = $hostname;
}
if (empty($_config['hostname']) || $_config['hostname'] == "http://" || $_config['hostname'] == "https://") {
    api_err("Invalid hostname");
}

// run sync
$t = time();
if ($t - $_config['sync_last'] > $_config['sync_interval'] && php_sapi_name() !== 'cli') {
	_log("Running sync ".($t - $_config['sync_last'])." / ".$_config['sync_interval'], 4);
	$dir = ROOT."/cli";
    _log("php $dir/sync.php  > /dev/null 2>&1  &", 4);
    system("php $dir/sync.php  > /dev/null 2>&1  &");
} else {
	_log("No time for sync ".($t - $_config['sync_last'])." / ".$_config['sync_interval'], 4);
}



//run miner
if(!defined("MINER_RUN")) {
	define("MINER_LOCK_PATH", ROOT . '/tmp/miner-lock');
	if ($_config['miner'] == true && isset($_config['miner_public_key']) && isset($_config['miner_private_key'])) {
		_log("Miner enabled", 4);
		_log("minerFile=".MINER_LOCK_PATH." exists=" . file_exists(MINER_LOCK_PATH), 4);
		if (!file_exists(MINER_LOCK_PATH)) {
			_log("File not exists - Staring miner", 0);

			$res = shell_exec("ps uax | grep miner.php | grep -v grep");
			_log("Res len=".strlen($res)." var=".json_encode($res)." empty=".empty($res));
			if(empty($res)) {
				$dir = ROOT."/cli";
				system("php $dir/miner.php > /dev/null 2>&1  &");
				$peers = Peer::getCount(true);
				if(!empty($peers)) {
					_log( "php $dir/miner.php > /dev/null 2>&1  &", 0);
				}
			} else {
				_log("Miner process already running",0);
			}
		} else {
			_log("Miner already started. File exists", 4);
		}
	}
}


