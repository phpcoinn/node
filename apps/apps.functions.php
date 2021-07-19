<?php

const APPS_REPO_SERVER = "https://repo.testnet.phpcoin.net";
const APPS_REPO_SERVER_PUBLIC_KEY = "PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCwUKtSuRJEs8RrRrkZbND1WxVNomPtvowAo5hzQr6xe2TUyHYLnzu2ubVMfBAYM4cBZJLckvxWenHB2nULzmU8VHz";

function calcAppsHash() {
	_log("Executing calcAppsHash");
	$cmd = "cd ".ROOT." && tar -cf - apps --owner=0 --group=0 --sort=name --mode=744 --mtime='2020-01-01 00:00:00 UTC' | sha256sum";
	$res = shell_exec($cmd);
	$arr = explode(" ", $res);
	$appsHash = trim($arr[0]);
	return $appsHash;
}

function buildAppsArchive() {
	$cmd = "cd ".ROOT." && tar -czf tmp/apps.tar.gz apps --owner=0 --group=0 --sort=name --mode=744 --mtime='2020-01-01 00:00:00 UTC'";
	shell_exec($cmd);
}

function extractAppsArchive() {
	$cmd = "cd ".ROOT." && rm -rf apps";
	shell_exec($cmd);
	$cmd = "cd ".ROOT." && tar -xzf tmp/apps.tar.gz -C . --owner=0 --group=0 --mode=744 --mtime='2020-01-01 00:00:00 UTC'";
	_log("Extracting archive : $cmd");
	shell_exec($cmd);
	$cmd = "cd ".ROOT." && find apps -type f -exec touch {} +";
	shell_exec($cmd);
	$cmd = "cd ".ROOT." && find apps -type d -exec touch {} +";
	shell_exec($cmd);
	opcache_reset();
}
