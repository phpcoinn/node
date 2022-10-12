<?php

global $_config;
if($_config['testnet']) {
	define("APPS_REPO_SERVER","https://repo.testnet.phpcoin.net:8001");
	define("APPS_REPO_SERVER_PUBLIC_KEY","PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCzXpeKxd772hFQEBFykSqUa9AXFeoi1fFfhJiGzReXWRuAQWf23dD1gMpFZDCw1gajgDLa5mofLHbgrhNQ1saDd8s");
	define("APPS_WALLET_SERVER_NAME","wallet.testnet.phpcoin.net:8001");
	define("APPS_WALLET_SERVER_PUBLIC_KEY","PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCxxo8UaTrKLceuCRRC4YopodMLvtPp31Bq1JJBmva3StkHMPa2WhgXhPyPLG9GiwEW3PwXDyroZGfNLE4ioqRtwyp");
} else {
	define("APPS_REPO_SERVER","https://repo.phpcoin.net");
	define("APPS_REPO_SERVER_PUBLIC_KEY","PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCyHWjnG15LHdWRRbNEmAPiYcyCqFZm1VKi8QziKYbMtrXUw8rqhrS3EEoyJxXASNZid9CsB1dg64u5sYgnUsrZg7C");
	define("APPS_WALLET_SERVER_NAME","wallet.phpcoin.net");
	define("APPS_WALLET_SERVER_PUBLIC_KEY","PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSD1SfcgMjJaHdx6p3BT9Jft9NXyh57c1EnisRgbtLDzXfEwddq6i717adJKkw82HMJDfRD8PZikraaq1HnBdCVxLsY");
}


function calcAppsHash() {
	$cmd = "cd ".ROOT."/web && tar -cf - apps --owner=0 --group=0 --sort=name --mode=744 --mtime='2020-01-01 00:00:00 UTC' | sha256sum";
	$res = shell_exec($cmd);
	$arr = explode(" ", $res);
	$appsHash = trim($arr[0]);
	_log("Executing calcAppsHash appsHash=$appsHash", 5);
	return $appsHash;
}

function buildAppsArchive() {
	$res = shell_exec("ps uax | grep 'tar -czf tmp/apps.tar.gz web/apps' | grep -v grep");
	_log("Repo: check buildAppsArchive res=$res", 5);
	if($res) {
		_log("Repo: buildAppsArchive running", 5);
		return false;
	} else {
		_log("Repo: buildAppsArchive call process", 5);
		$cmd = "cd ".ROOT." && tar -czf tmp/apps.tar.gz web/apps --owner=0 --group=0 --sort=name --mode=744 --mtime='2020-01-01 00:00:00 UTC'";
		shell_exec($cmd);
		return true;
	}
}

function truncate_hash($hash, $digits = 8) {
	if(empty($hash)) {
		return null;
	}
	$thash = substr($hash, 0, $digits) . "..." . substr($hash, -$digits);
	return '<span data-bs-toggle="tooltip" title="'.$hash.'">' . $thash . '</span>';
}

function explorer_address_link2($address, $short= false) {
	$text  = $address;
	if($short) {
		$text  = truncate_hash($address);
	}
	return '<a target="_blank" href="/apps/explorer/address.php?address='.$address.'">'.$text.'</a>';
}

function TransactionTypeLabel($type) {
	return Transaction::typeLabel($type);
}

function AccountgetCountByAddress($id) {
	//TODO: replace with Account::getCountByAddress
	global $db;
	$res = $db->single(
		"SELECT count(*) as cnt FROM transactions 
				WHERE dst=:dst or src=:src",
		[":src" => $id, ":dst" => $id]
	);
	return $res;
}
