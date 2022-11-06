<?php

require_once dirname(dirname(__DIR__)).'/include/init.inc.php';
define("APPS_VERSION","1.0.52");
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

global $_config;
$nodeScore = round($_config['node_score'],2);

if(!FEATURE_APPS) {
	_log("Apps: feature disabled", 5);
	return;
}

_log("Checking apps integrity", 4);
$appsHashFile = Nodeutil::getAppsHashFile();
$appsChanged = false;
if(!file_exists($appsHashFile)) {
	_log("APPS: Not exists hash file",4);
	$appsHashCalc = calcAppsHash();
	if(!file_put_contents($appsHashFile, $appsHashCalc)) {
		die("tmp folder not writable to server!");
	}
	$appsChanged = true;
	_log("APPS: Created hash file",4);
} else {
	_log("APPS: Exists hash file",4);
	$appsHash = file_get_contents($appsHashFile);
	$appsHashTime = filemtime($appsHashFile);
	$now = time();
	$elapsed = $now - $appsHashTime;
	if($elapsed > 60 * 5) {
		_log("APPS: File is older than check period",4);
		$appsHashCalc = calcAppsHash();
		_log("APPS: Writing new hash",3);
		file_put_contents($appsHashFile, $appsHashCalc);
		$appsChanged = true;
	}
}

$appsHash = file_get_contents($appsHashFile);

$dev = false;
$adminView = (strpos($_SERVER['REQUEST_URI'], "/apps/admin")===0);

//check and show git version
$cmd = "cd ".ROOT." && git log -n 1 --pretty=format:\"%H\"";
$gitRev = shell_exec($cmd);
_log("READ GIT: ".$cmd, 5);

$allow_insecure_apps = isset($_config['allow_insecure_apps']) && isset($_config['allow_insecure_apps']);

if(!$allow_insecure_apps && $appsChanged) {
	$peers = Peer::getActive();
	//_log("get random peers: ".json_encode($peers),3);
	$peerAppsHash = false;
	foreach ($peers as $peer) {
		_log("APPS: contacting peer ".$peer['hostname'], 3);
		$peerAppsHash = peer_post($peer['hostname']."/peer.php?q=getAppsHash", null);
		_log("APPS: get apphahs from peer ".$peer['hostname']." hash=".$peerAppsHash, 3);
		if($peerAppsHash) {
			break;
		}
	}
	if($peerAppsHash){
		_log("APPS: Using peer apphash: ".$peerAppsHash, 3);
	} else {
		_log("APPS: Can not get apphash from peers", 2);
	}

	$force_repo_check = false;
	_log("APPS: Checking apps hash appsHash=$appsHash peerAppsHash=$peerAppsHash allow_insecure_apps=$allow_insecure_apps", 4);
	if((!$peerAppsHash || $peerAppsHash != $appsHash || $force_repo_check)) {
		_log("APPS: Checking apps from repo server" , 4);
		$repoServer = isRepoServer();

		if(!$repoServer) {
			_log("APPS: Contancting repo server" , 4);
			$res = peer_post(APPS_REPO_SERVER . "/peer.php?q=getApps", null);
			_log("APPS: Response from repo server ".json_encode($res), 5);
			if ($res === false) {
				if (!$adminView) {
					_log("APPS: Unable to check apps integrity - repo server has no response", 2);
					die("Unable to check apps integrity - repo server has no response");
				}
			} else {
				$hash = $res['hash'];
				$signature = $res['signature'];
				$verify = Account::checkSignature($hash, $signature, APPS_REPO_SERVER_PUBLIC_KEY);
				_log("APPS: Verify repsonse hash=$hash signature=$signature verify=$verify", 4);
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
						_log("APPS: Apps hash OK", 5);
					}
				}
			}
		} else {
			_log("APPS: This is repo server - do nothing", 5);
		}
	}
}

if(isRepoServer()) {
	$appsHashCalc = calcAppsHash();
	$appsHash = file_get_contents($appsHashFile);
	_log("Repo: Checking repo server update appsHash=$appsHash appsHashCalc=$appsHashCalc",5);
	if($appsHash != $appsHashCalc) {
		$res = buildAppsArchive();
		if($res) {
			_log("Repo: Propagating apps",5);
			Propagate::appsToAll($appsHashCalc);
		}
	} else {
		_log("Repo: Apps not changed",5);
	}
}


