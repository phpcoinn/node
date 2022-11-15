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

	static function blockToPeer($ip, $id) {
		_log("Propagate: block to peer $ip id=$id",4);
		$ip = Peer::validateIp($ip);
		$ip = escapeshellcmd($ip);
		$id=escapeshellcmd(san($id));
		$dir = ROOT . "/cli";
		$ip = base58_encode($ip);
		$cmd = "php $dir/propagate.php block '$id' '$ip'";
		_log("Propagate cmd: $cmd",5);
		Nodeutil::runSingleProcess($cmd);
	}

	static function masternode() {
		_log("Propagate: masternode",4);
		$dir = ROOT."/cli";
		$cmd = "php $dir/propagate.php masternode local";
		Nodeutil::runSingleProcess($cmd);
	}

	static function masternodeToPeer($ip) {
		_log("Propagate: masternode to peer $ip", 4);
		$ip = base64_encode($ip);
		$dir = ROOT."/cli";
		$cmd = "php $dir/propagate.php masternode $ip";
		Nodeutil::runSingleProcess($cmd);
	}

	static function transactionToAll($id) {
		_log("Propagate: transaction $id to all", 4);
		$dir = ROOT."/cli";
		$cmd ="php $dir/propagate.php transaction '$id'";
		Nodeutil::runSingleProcess($cmd);
	}

	static function transactionToPeer($id, $ip) {
		$ipb64 = base64_encode($ip);
		$dir = ROOT."/cli";
		$cmd = "php $dir/propagate.php transactionpeer $id $ipb64";
		_log("Propagate: transaction $id to peer $ipb64 cmd=$cmd", 4);
		Nodeutil::runSingleProcess($cmd);
	}

	static function appsToPeer($ip, $hash) {
		if(!Nodeutil::isRepoServer()) {
			_log("Not repo server");
			return;
		}
		$ip = base64_encode($ip);
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php appspeer $hash $ip";
		_log("Propagate: transaction apps to peer $ip cmd=$cmd", 4);
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

	static function messageToPeer($ip, $msg) {
		$ip = base64_encode($ip);
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php message $ip $msg";
		Nodeutil::runSingleProcess($cmd);
	}

	static function dappsLocal() {
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php dapps local";
		Nodeutil::runSingleProcess($cmd);
	}

	static function dappsToPeer($ip) {
		$dir = ROOT . "/cli";
		$ip_encoded = base64_encode($ip);
		$cmd = "php $dir/propagate.php dapps $ip_encoded";
		_log("dappsToPeer ip=$ip  cmd=$cmd", 5);
		Nodeutil::runSingleProcess($cmd);
	}

	static function dappsUpdateToPeer($ip, $dapps_id) {
		$ip = base64_encode($ip);
		$dir = ROOT . "/cli";
		$cmd = "php $dir/propagate.php dapps-update $ip $dapps_id";
		Nodeutil::runSingleProcess($cmd);
	}
}
