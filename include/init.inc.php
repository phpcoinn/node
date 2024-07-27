<?php
$GLOBALS['start_time']=microtime(true);

const DEFAULT_CHAIN_ID = "00";

$blocked_agents = ["DataForSeoBot","BLEXBot","SemrushBot","YandexBot","AhrefsBot"];
if (php_sapi_name() !== 'cli') {
	if(isset($_SERVER['HTTP_USER_AGENT'])) {
		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		foreach($blocked_agents as $agent) {
			if(strpos($user_agent, $agent)!== false) {
				header('HTTP/1.0 403 Forbidden');
				exit;
			}
		}
	}
}

// UTC timezone by default
date_default_timezone_set("UTC");
require_once dirname(__DIR__).'/vendor/autoload.php';
define("ROOT", dirname(__DIR__));

 error_reporting(E_ALL & ~E_NOTICE);
//error_reporting(0);
$version = explode('.', PHP_VERSION);
if($version[0] > 7) {
	ini_set('display_errors', 0);
} else {
	ini_set('display_startup_errors', 1);
}
// not accessible directly
if (php_sapi_name() !== 'cli' && substr_count($_SERVER['PHP_SELF'], "/") > 1
	&& substr($_SERVER['PHP_SELF'], 0, 5) != "/apps"
	&& substr($_SERVER['PHP_SELF'], 0, 6) != "/dapps") {
    die("This application should only be run in the main directory /");
}

if(PHP_VERSION_ID < 80000 && false) {
    die("Invalid php version! Please upgrade your node");
}

$config_file = ROOT.'/config/config.inc.php';

require_once $config_file;
require_once __DIR__.'/db.inc.php';
global $_config;

if(false && strlen($_SERVER['HTTP_USER_AGENT'])>0) {
	$log_agent_file = ROOT . "/tmp/agent.log";
	$date = date("[Y-m-d H:i:s]");
	$s = $date . "\t" . $_SERVER['REQUEST_URI'] . "\t" . $_SERVER['HTTP_USER_AGENT'].PHP_EOL;
	@file_put_contents($log_agent_file, $s, FILE_APPEND);
	//check: cut -f 3 tmp/agent.log | uniq | sort | grep bot
}

@include_once ROOT.'/web/apps/apps.functions.php';

if ($_config['db_pass'] == "ENTER-DB-PASS") {
    die("Please update your config file and set your db password");
}
// initial DB connection
$db = new DB($_config['db_connect'], $_config['db_user'], $_config['db_pass'], $_config['enable_logging']);
if (!$db) {
    die("Could not connect to the DB backend.");
}
if($db->isSqlite()) {
    $db->exec('PRAGMA journal_mode=WAL;');
    $db->exec("PRAGMA busy_timeout=5000");
} else {
//    $db->exec('set SESSION innodb_lock_wait_timeout=5');
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
if(empty(shell_exec("git --version"))) {
    api_err("git must be installed");
}


$version = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
if(!$db->isSqlite() && floatval(substr($version, 0, 3)) < 8 && strpos($version, "MariaDB")===false) {
    api_err("The minimum mysql version required is 8, current version is $version");
}

if (floatval(phpversion()) < 7.2) {
    api_err("The minimum php version required is 7.2");
}

// Getting extra configs from the database
$_config = load_db_config();

//check db update
_log("checking schema update", 5);
require_once __DIR__.'/schema.inc.php';


// nothing is allowed while in maintenance
if (isset($_config['maintenance']) && $_config['maintenance'] == 1) {
    api_err("under-maintenance");
}

// current hostname
$hostname = (!empty($_SERVER['HTTPS']) ? 'https' : 'http')."://".san_host(@$_SERVER['HTTP_HOST']);
// set the hostname to the current one
if ($hostname != @$_config['hostname'] && @$_SERVER['HTTP_HOST'] != "localhost" && @$_SERVER['HTTP_HOST'] != "127.0.0.1" && @$_SERVER['hostname'] != '::1' && php_sapi_name() !== 'cli' && ($_config['allow_hostname_change'] != false || empty(@$_config['hostname']))) {
    $db->run("UPDATE config SET val=:hostname WHERE cfg='hostname' LIMIT 1", [":hostname" => $hostname]);
    $_config['hostname'] = $hostname;
}
if (empty($_config['hostname']) || $_config['hostname'] == "http://" || $_config['hostname'] == "https://") {
    api_err("Invalid hostname");
}

global $checkpoints;
require_once __DIR__ . "/checkpoints.php";
$block = Block::get(1);
if($block) {
    if($block['id']!=$checkpoints[1]) {
        api_err("Invalid chain. Please check config files");
    }
}

$chain_id = trim(file_get_contents(dirname(__DIR__)."/chain_id"));
if($chain_id!= CHAIN_ID) {
    api_err("Invalid chain. Please check chain_id file");
}


if(!defined("CRON")) {
    Nodeutil::runAtInterval("check-cron", 60, function () {
        Util::checkCron();
    });
}
