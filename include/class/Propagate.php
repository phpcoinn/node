<?php

class Propagate
{

	static function blockToAll($id) {
		_log("Propagate: block to all id=$id", 4);
		$id=escapeshellcmd(san($id));
		$dir = ROOT."/cli";
		$cmd= "php $dir/propagate.php block '$id' all";
		Nodeutil::runSingleProcess($cmd);
	}

	static function blockToPeer($hostname, $ip, $id) {
		_log("Propagate: block to peer $hostname id=$id",4);
		$host = escapeshellcmd(base58_encode($hostname));
		$ip = Peer::validateIp($ip);
		$ip = escapeshellcmd($ip);
		$id=escapeshellcmd(san($id));
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php block '$id' '$host' '$ip'";
		_log("Propagate cmd: $cmd");
		Nodeutil::runSingleProcess($cmd);
	}

	static function masternode() {
		_log("Propagate: masternode",4);
		$dir = ROOT."/cli";
		$cmd = "php $dir/propagate.php masternode local";
		Nodeutil::runSingleProcess($cmd);
	}

	static function masternodeToPeer($peer) {
		_log("Propagate: masternode to peer $peer", 4);
		$peer = base64_encode($peer);
		$dir = ROOT."/cli";
		$cmd = "php $dir/propagate.php masternode $peer";
		Nodeutil::runSingleProcess($cmd);
	}

	static function transactionToAll($id) {
		_log("Propagate: transaction $id to all", 4);
		$dir = ROOT."/cli";
		$cmd ="php $dir/propagate.php transaction '$id'";
		Nodeutil::runSingleProcess($cmd);
	}

	static function transactionToPeer($id, $hostname) {
		$hostnameb64 = base64_encode($hostname);
		$dir = ROOT."/cli";
		$cmd = "php $dir/propagate.php transactionpeer $id $hostnameb64";
		_log("Propagate: transaction $id to peer $hostname cmd=$cmd", 4);
		Nodeutil::runSingleProcess($cmd);
	}

	static function appsToPeer($hostname, $hash) {
		if(!Nodeutil::isRepoServer()) {
			_log("Not repo server");
			return;
		}
		$hostnameb64 = base64_encode($hostname);
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php appspeer $hash $hostnameb64";
		_log("Propagate: transaction apps to peer $hostname cmd=$cmd", 4);
		Nodeutil::runSingleProcess($cmd);
	}

	static function appsToAll($appsHashCalc) {
		if(!Nodeutil::isRepoServer()) {
			_log("Not repo server");
			return;
		}
		_log("AppsHash: Propagating apps",3);
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php apps $appsHashCalc";
		Nodeutil::runSingleProcess($cmd);
	}

	static function messageToPeer($hostname, $msg) {
		$peer = base64_encode($hostname);
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php message $peer $msg";
		Nodeutil::runSingleProcess($cmd);
	}

	static function dappsLocal() {
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php dapps local";
		Nodeutil::runSingleProcess($cmd);
	}

	static function dappsToPeer($hostname) {
		$dir = ROOT . "/cli";
		$peer = base64_encode($hostname);
		$cmd = "php $dir/propagate.php dapps $peer";
		Nodeutil::runSingleProcess($cmd);
	}

	static function dappsUpdateToPeer($hostname, $dapps_id) {
		$peer = base64_encode($hostname);
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php dapps-update $peer $dapps_id";
		Nodeutil::runSingleProcess($cmd);
	}
}
