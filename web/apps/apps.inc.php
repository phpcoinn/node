<?php

require_once dirname(dirname(__DIR__)).'/include/init.inc.php';
define("APPS_VERSION","1.0.30");
function relativePath($from, $to, $ps = DIRECTORY_SEPARATOR)
{
	$arFrom = explode($ps, rtrim($from, $ps));
	$arTo = explode($ps, rtrim($to, $ps));
	while(count($arFrom) && count($arTo) && ($arFrom[0] == $arTo[0]))
	{
		array_shift($arFrom);
		array_shift($arTo);
	}
	return str_pad("", count($arFrom) * 3, '..'.$ps).implode($ps, $arTo);
}

function isRepoServer() {
	global $_config;
	$repoServer = false;
	if($_config['repository'] && $_config['repository_private_key']) {
		$private_key = coin2pem($_config['repository_private_key'], true);
		$pkey = openssl_pkey_get_private($private_key);
		$k = openssl_pkey_get_details($pkey);
		$public_key = pem2coin($k['key']);
		if ($public_key == APPS_REPO_SERVER_PUBLIC_KEY) {
			$repoServer = true;
		}
	}
	return $repoServer;
}



_log("Checking apps integrity", 4);
$appsHashFile = Nodeutil::getAppsHashFile();
$appsChanged = false;
$appsHashCalc = calcAppsHash();
if(!file_exists($appsHashFile)) {
	_log("Not exists hash file", 4);
	if(!file_put_contents($appsHashFile, $appsHashCalc)) {
		die("tmp folder not writable to server!");
	}
	$appsChanged = true;
	_log("Created hash file",4);
} else {
	_log("Exists hash file",4);
	$appsHash = file_get_contents($appsHashFile);
	$appsHashTime = filemtime($appsHashFile);
	$now = time();
	$elapsed = $now - $appsHashTime;
	if($elapsed > 60) {
		_log("File is older than check period",4);
		if($appsHashCalc != $appsHash) {
			_log("Writing new hash",3);
			file_put_contents($appsHashFile, $appsHashCalc);
			$appsChanged = true;
		}
	}
}

$appsHash = file_get_contents($appsHashFile);

$nodeScore = $_config['node_score'];

$dev = false;
$adminView = (strpos($_SERVER['REQUEST_URI'], "/apps/admin")===0);

//check and show git version
$gitRev = shell_exec("cd . ".ROOT." && git rev-parse HEAD");

if(!$dev) {
	$peers = Peer::getActive();
	_log("get random peers: ".json_encode($peers),3);

	foreach ($peers as $peer) {
		_log("contacting peer ".$peer['hostname'],3);
		$peerAppsHash = peer_post($peer['hostname']."/peer.php?q=getAppsHash", null, 1);
		_log("get apphahs from peer ".$peer['hostname']." hash=".$peerAppsHash,3);
		if($peerAppsHash) {
			break;
		}
	}
	if($peerAppsHash){
		_log("Using peer apphash: ".$peerAppsHash,3);
	} else {
		_log("Can not get apphash from peers",2);
	}
}

$force_repo_check = false;
_log("Checking apps hash appsHash=$appsHash peerAppsHash=$peerAppsHash",3);
if(!$peerAppsHash || $peerAppsHash != $appsHash || $force_repo_check) {
	_log("Checking apps from repo server",3);
	$repoServer = isRepoServer();

	if(!$repoServer) {
		_log("Contancting repo server",3);
		$res = peer_post(APPS_REPO_SERVER . "/peer.php?q=getApps", null, 1);
		_log("Response from repo server ".json_encode($res),3);
		if ($res === false) {
			if (!$adminView) {
				_log("Unable to check apps integrity - repo server has no response",1);
				if(!isset($_config['allow_insecure_apps'])) {
					_log("exit",3);
					die("Unable to check apps integrity - repo server has no response");
				}
			}
		} else {
			$hash = $res['hash'];
			$signature = $res['signature'];
			$verify = Account::checkSignature($hash, $signature, APPS_REPO_SERVER_PUBLIC_KEY);
			_log("Verify repsonse hash=$hash signature=$signature verify=$verify",3);
			if (!$verify) {
				if (!$adminView) {
					die("Unable to check apps integrity - invalid repository signature");
				}
			} else {
				if ($appsHash != $hash) {
					if (!$adminView) {
						die("Apps integrity not valid appsHash=$appsHash hash=$hash");
					}
				} else {
					_log("Apps hash OK",3);
				}
			}
		}
	} else {
		_log("This is repo server - do nothing",3);
	}
}

if(isRepoServer()) {
	_log("Checking repo server update",3);
	if($appsHash != $appsHashCalc || $appsChanged) {
		buildAppsArchive();
		$dir = ROOT . "/cli";
		_log("Propagating apps",3);
		system("php $dir/propagate.php apps $appsHashCalc > /dev/null 2>&1  &");
	} else {
		_log("Apps not changed",3);
	}
}


