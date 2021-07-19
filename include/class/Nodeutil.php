<?php


class Nodeutil
{

	static function cleanTmpFiles() {
		_log("Cleaning tmp files",3);
		$tmpDir = dirname(dirname(__DIR__)) . "/tmp/";
		$f = scandir($tmpDir);
		$time = time();
		foreach ($f as $x) {
			if (strlen($x) < 5 && substr($x, 0, 1) == ".") {
				continue;
			}
			$pid_time = filemtime($tmpDir . $x);
			if ($time - $pid_time > 7200) {
				@unlink($tmpDir . $x);
			}
		}
	}


	static function getAppsHash() {
		$appsHashFile = Nodeutil::getAppsHashFile();
		$appsHash = file_get_contents($appsHashFile);
		$appsHash = trim($appsHash);
		return $appsHash;
	}

	static function clean() {
		global $db;
		$lockFile = Nodeutil::getSanityFile();
		if (file_exists($lockFile)) {
			_log("Sanity running. Wait for it to finish");
			return;
		}
		@touch($lockFile);
		$db->fkCheck(false);
		$tables = ["accounts", "transactions", "mempool", "masternode","blocks"];
		foreach ($tables as $table) {
			$db->truncate($table);
		}
		$db->fkCheck(true);

		_log("The database has been cleared");
		@unlink($lockFile);
	}

	static function relativePath($from, $to, $ps = DIRECTORY_SEPARATOR)
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

	static function checkBlocksWithPeer($peer) {
		$block = new Block();
		$current = $block->current();
		$top = $current['height'];
		$peerTopBlock = peer_post($peer."/peer.php?q=currentBlock");
		$peerTop = $peerTopBlock["block"]['height'];
		$top = min($top, $peerTop);
		_log("max blocks $top");
		$bottom = 1;
		$blockMatch = false;
		$invalid_block = null;
		while(!$blockMatch) {
			$check = intval(($top + $bottom) /2);
			_log("checking block $check");
			$myBlock = $block->get($check);
			$b = peer_post($peer . "/peer.php?q=getBlock", ["height" => $check]);
			if (!$b) {
				_log("Not good peer to check. No response for block $check");
				exit;
			}
			$myBlockId = $myBlock['id'];
			$peerBlockId = $b['id'];
			_log("Checking block $check: myBlockId=$myBlockId peerBlockId=$peerBlockId");
			$blockMatch = (abs($top - $bottom)==1);
			if ($myBlockId == $peerBlockId) {
				$bottom = $check;
				_log("Block matches - continue upwards - check $check top=$top bottom=$bottom");
			} else {
				$top = $check;
				$invalid_block = $check;
				_log("Block not matches - back downwards - check $check top=$top bottom=$bottom");
			}
		}
		return $invalid_block;
	}

	static function deleteLatestBlocks($no) {
		$sanityFile = Nodeutil::getSanityFile();
		if (file_exists($sanityFile)) {
			_log("Sanity running. Wait for it to finish", 3);
			return;
		}
		touch($sanityFile);
		$no = intval($no);
		$block = new Block();
		$block->pop($no);
		unlink($sanityFile);
	}

	static function deleteFromHeight($height) {
		$no = Block::getHeight() - $height;
		self::deleteLatestBlocks($no);
	}

	static function calculateAccountsHash() {
		global $db;
		if($db->isSqlite()) {
			$res=$db->run("SELECT *, printf('%.".COIN_DECIMALS."f', balance) as balance FROM accounts ORDER by id ASC");
		} else {
			$res=$db->run("SELECT * FROM accounts ORDER by id ASC");
		}
		$block=new Block();
		$current=$block->current();
		return [
			'height'=>$current['height'],
			'hash'=>md5(json_encode($res))
		];
	}
	
	static function calculateBlocksHash($height) {
		global $db;
		if(empty($height)) {
			$height = Block::getHeight();
		}
		$rows = $db->run("select id from blocks where height >= :height and height <=:top order by height asc",
			[":height"=>BLOCKCHAIN_CHECKPOINT, ":top"=>$height]);
		return [
			'height'=>$height,
			'hash'=>md5(json_encode($rows))
		];
	}

	static function printRewardScheme() {
		echo str_pad("block", 10);
		echo str_pad('total', 10);
		echo str_pad('miner', 10);
		echo str_pad('mn', 10);
		echo str_pad('pos', 10);
		echo str_pad('days', 10);
		echo str_pad('time', 24);
		echo str_pad('supply', 10);
		echo PHP_EOL;

		$prev_reward = 0;
		$total_supply = 0;
		for($i=1;$i<=PHP_INT_MAX;$i++) {
			$reward = Block::reward($i);
			$elapsed = $i * BLOCK_TIME;
			$time = GENESIS_TIME + $elapsed;
			$total_supply += $reward['total'];
			$days = $elapsed / 60 / 60 / 24;
			if($reward['key'] != $prev_reward) {
				echo str_pad($i, 10);
				echo str_pad($reward['total'], 10);
				echo str_pad($reward['miner'], 10);
				echo str_pad($reward['masternode'], 10);
				echo str_pad($reward['pos'], 10);
				echo str_pad(round($days,2), 10);
				echo str_pad(date("Y-m-d H:i:s",$time), 24);
				echo str_pad($total_supply, 10);
				echo PHP_EOL;
			}
			if($reward['total']==0) {
				break;
			}
			$prev_reward = $reward['key'];
		}
	}

	static function getSanityFile() {
		$file = ROOT."/tmp/sanity-lock";
		return $file;
	}

	static function getAppsHashFile() {
		$appsHashFile = ROOT . "/tmp/apps.hash";
		return $appsHashFile;
	}

	static function validatePublicKey($public_key) {
		$pem_public_key = coin2pem($public_key);
		$pkey = openssl_pkey_get_public($pem_public_key);
		return ($pkey !== false);
	}

	static function getRemoteAddr() {
		_log("call getRemoteAddr",4);
		$ip = san_ip($_SERVER['REMOTE_ADDR']);
		$ip = Peer::validateIp($ip);
		_log("call getRemoteAddr ip1=$ip",4);
		if(!$ip) {
			$ip = san_ip($_SERVER['HTTP_X_FORWARDED_FOR']);
			$ip = Peer::validateIp($ip);
			_log("call getRemoteAddr ip2=$ip",4);
		} else {
			_log("ip1 is ok",4);
		}
		_log("return getRemoteAddr ip=$ip",4);
		return $ip;
	}
}
