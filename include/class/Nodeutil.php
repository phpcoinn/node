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
		$lockFile = Nodeutil::getSyncFile();
		if (file_exists($lockFile)) {
			_log("Sync running. Wait for it to finish");
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
		$current = Block::_current();
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
			$myBlock = Block::get($check);
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
		$syncFile = Nodeutil::getSyncFile();
		if (file_exists($syncFile)) {
			_log("Sync running. Wait for it to finish", 3);
			return;
		}
		touch($syncFile);
		$no = intval($no);
		Block::pop($no);
		unlink($syncFile);
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
		$current=Block::_current();
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

	static function getSyncFile() {
		$file = ROOT."/tmp/sync-lock";
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
		$ip = san_ip($_SERVER['REMOTE_ADDR']);
		$ip = Peer::validateIp($ip);
		if(!$ip) {
			$ip = san_ip($_SERVER['HTTP_X_FORWARDED_FOR']);
			$ip = Peer::validateIp($ip);
		}
		return $ip;
	}

	static function downloadApps() {

		if(!defined("APPS_REPO_SERVER")) {
			define("APPS_REPO_SERVER", "https://repo.testnet.phpcoin.net");
		}
		if(!defined("APPS_REPO_SERVER_PUBLIC_KEY")) {
			define("APPS_REPO_SERVER_PUBLIC_KEY", "PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCwUKtSuRJEs8RrRrkZbND1WxVNomPtvowAo5hzQr6xe2TUyHYLnzu2ubVMfBAYM4cBZJLckvxWenHB2nULzmU8VHz");
		}

		$res = peer_post(APPS_REPO_SERVER."/peer.php?q=getApps");
		_log("Contancting repo server response=".json_encode($res),3);
		if($res === false) {
			_log("No response from repo server",2);
		} else {
			$hash = $res['hash'];
			$signature = $res['signature'];
			$verify = Account::checkSignature($hash, $signature, APPS_REPO_SERVER_PUBLIC_KEY);
			_log("Verify repo response hash=$hash signature=$signature verify=$verify",3);
			if(!$verify) {
				_log("Not verified signature from repo server",2);
			} else {
				$link = APPS_REPO_SERVER."/tmp/apps.tar.gz";
				_log("Downloading archive file from link $link",3);
				$arrContextOptions=array(
					"ssl"=>array(
						"verify_peer"=>!DEVELOPMENT,
						"verify_peer_name"=>!DEVELOPMENT,
					),
				);
				$res = file_put_contents(ROOT . "/tmp/apps.tar.gz", fopen($link, "r", false,  stream_context_create($arrContextOptions)));
				if($res === false) {
					_log("Error downloading apps from repo server",2);
				} else {
					$size = filesize(ROOT . "/tmp/apps.tar.gz");
					if(!$size) {
						_log("Downloaded empty file from repo server",1);
					} else {
						self::extractAppsArchive();
						_log("Extracted archive",3);
						$calHash = calcAppsHash();
						_log("Calculated new hash: ".$calHash,3);
						if($hash != $calHash) {
							_log("Error extracting apps transfered",2);
						} else {
							$appsHashFile = Nodeutil::getAppsHashFile();
							file_put_contents($appsHashFile, $calHash);
							_log("Stored new hash",3);
						}
					}
				}
			}
		}
	}

	static function sync($current, $largest_height, $peers, $most_common) {



	}

	static function getConfig() {
		global $db;
		$config_file = ROOT.'/config/config.inc.php';
		require_once $config_file;
		$query = $db->run("SELECT cfg, val FROM config");
		if(is_array($query)) {
			foreach ($query as $res) {
				$_config[$res['cfg']] = trim($res['val']);
			}
		}
		return $_config;
	}

	static function miningEnabled() {
		global $_config;
		if(isset($_config['generator']) && $_config['generator']
			&& !empty($_config['generator_public_key']) && !empty($_config['generator_private_key'])) {
			return true;
		} else {
			return false;
		}

	}

	static function walletEnabled() {
		global $_config;
		if(isset($_config['wallet']) && $_config['wallet']
			&& !empty($_config['wallet_public_key']) && !empty($_config['wallet_private_key']) &&
			APPS_WALLET_SERVER_PUBLIC_KEY == $_config['wallet_public_key']) {
			return true;
		} else {
			return false;
		}

	}

	static function verifyBlocks() {
		$height = Block::getHeight();

		for($i=1;$i<=$height;$i++) {
			$block = Block::export("",$i);
			$res = Block::getFromArray($block)->_verifyBlock();
			echo "Verify block $i / $height res=$res".PHP_EOL;
			if(!$res) {
				return;
			}
		}
	}

	static function exportChain() {
		$height = Block::getHeight();
		$list = [];
		$file = getcwd() . "/blockchain.json";
		echo "Exporting blockchain to file: " . $file.PHP_EOL;
		for($i=1;$i<=$height;$i++) {
			$block = Block::export("",$i);
			$list[]=$block;
			if($i % 100 == 0) {
				echo "Exporting block $i".PHP_EOL;
			}
		}
		file_put_contents($file, json_encode($list));
		echo "Export finished".PHP_EOL;
	}

	static function extractAppsArchive() {
		$cmd = "cd ".ROOT." && rm -rf apps";
		shell_exec($cmd);
		$cmd = "cd ".ROOT." && tar -xzf tmp/apps.tar.gz -C . --owner=0 --group=0 --mode=744 --mtime='2020-01-01 00:00:00 UTC'";
		_log("Extracting archive : $cmd");
		shell_exec($cmd);
		$cmd = "cd ".ROOT." && find apps -type f -exec touch {} +";
		shell_exec($cmd);
		$cmd = "cd ".ROOT." && find apps -type d -exec touch {} +";
		shell_exec($cmd);
		if (php_sapi_name() == 'cli') {
			$cmd = "cd ".ROOT." && chown -R www-data:www-data apps";
			shell_exec($cmd);
		}
		opcache_reset();
	}


}
