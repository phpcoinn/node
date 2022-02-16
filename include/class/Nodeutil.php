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

	/**
	 * @api {php util.php} clean Clean
	 * @apiName clean
	 * @apiGroup UTIL
	 * @apiDescription Cleans the entire database
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php clean
	 */
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
		$current = Block::current();
		$top = $current['height'];
		$peerTopBlock = peer_post($peer."/peer.php?q=currentBlock");
		$peerTop = $peerTopBlock["block"]['height'];
		$top = min($top, $peerTop);
		_log("max blocks $top",3);
		$bottom = 1;
		$blockMatch = false;
		$invalid_block = null;
		while(!$blockMatch) {
			$check = intval(($top + $bottom) /2);
			_log("checking block $check",3);
			$myBlock = Block::get($check);
			$b = peer_post($peer . "/peer.php?q=getBlock", ["height" => $check]);
			if (!$b) {
				_log("Not good peer to check. No response for block $check");
				exit;
			}
			$myBlockId = $myBlock['id'];
			$peerBlockId = $b['id'];
			_log("Checking block $check: myBlockId=$myBlockId peerBlockId=$peerBlockId",3);
			$blockMatch = (abs($top - $bottom)==1);
			if ($myBlockId == $peerBlockId) {
				$bottom = $check;
				_log("Block matches - continue upwards - check $check top=$top bottom=$bottom",3);
			} else {
				$top = $check;
				$invalid_block = $check;
				_log("Block not matches - back downwards - check $check top=$top bottom=$bottom",3);
			}
		}
		return $invalid_block;
	}

	static function deleteLatestBlocks($no) {
		$syncFile = Nodeutil::getSyncFile();
		if (file_exists($syncFile)) {
			$_SESSION['msg'] = [['icon' => 'warning', 'text' => 'Sync running. Wait for it to finish']];
			_log("Sync running. Wait for it to finish", 3);
			return;
		}
		touch($syncFile);
		$no = intval($no);
		Block::pop($no);
		unlink($syncFile);
	}

	static function deleteFromHeight($height) {
		if($height < 0) {
			$no = abs($height);
		} else {
			$no = Block::getHeight() - $height;
		}
		self::deleteLatestBlocks($no);
	}

	static function calculateAccountsHash() {
		global $db;
		if($db->isSqlite()) {
			$res=$db->run("SELECT id,public_key,block,printf('%.".COIN_DECIMALS."f', abs(round(balance,8))) as balance, alias FROM accounts ORDER by id collate NOCASE");
		} else {
			$res=$db->run("SELECT * FROM accounts ORDER by id");
		}
		$current=Block::current();
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

	static function getSyncFile() {
		$file = ROOT."/tmp/sync-lock";
		return $file;
	}

	static function getAppsHashFile() {
		$appsHashFile = ROOT . "/tmp/apps.hash";
		return $appsHashFile;
	}

	static function getAppsLockFile() {
		$file = ROOT . "/tmp/apps-lock";
		return $file;
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
		global $_config;
		if(!defined("APPS_REPO_SERVER")) {
			if($_config['testnet'] ) {
				define("APPS_REPO_SERVER", "https://repo.testnet.phpcoin.net");
			} else {
				define("APPS_REPO_SERVER", "https://repo.phpcoin.net");
			}
		}
		if(!defined("APPS_REPO_SERVER_PUBLIC_KEY")) {
			if($_config['testnet'] ) {
				define("APPS_REPO_SERVER_PUBLIC_KEY", "PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCwUKtSuRJEs8RrRrkZbND1WxVNomPtvowAo5hzQr6xe2TUyHYLnzu2ubVMfBAYM4cBZJLckvxWenHB2nULzmU8VHz");
			} else {
				define("APPS_REPO_SERVER_PUBLIC_KEY", "PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCyHWjnG15LHdWRRbNEmAPiYcyCqFZm1VKi8QziKYbMtrXUw8rqhrS3EEoyJxXASNZid9CsB1dg64u5sYgnUsrZg7C");
			}
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
				$link = APPS_REPO_SERVER."/apps.php";
				_log("Downloading archive file from link $link",3);
				$arrContextOptions=array(
					"ssl"=>array(
						"verify_peer"=>!DEVELOPMENT,
						"verify_peer_name"=>!DEVELOPMENT,
					),
				);
				$res = file_put_contents(ROOT . "/tmp/apps.tar.gz", fopen($link, "r", false,  stream_context_create($arrContextOptions)));
				if($res === false) {
					_log("Error downloading apps from repo server");
				} else {
					$size = filesize(ROOT . "/tmp/apps.tar.gz");
					if(!$size) {
						_log("Downloaded empty file from repo server");
					} else {
						if(file_exists(self::getAppsLockFile())) {
							_log("Apps lock file exists - can not update");
							return;
						}
						$lock = fopen(self::getAppsLockFile(), "w");
						fclose($lock);
						_log("backup existing apps", 4);
						$cmd = "cd ".ROOT."/web && rm -rf apps_tmp";
						shell_exec($cmd);
						$cmd = "cd ".ROOT."/web && cp -rf apps apps_tmp";
						shell_exec($cmd);
						self::extractAppsArchive();
						_log("Extracted archive",4);
						$calHash = self::calcAppsHash();
						_log("Calculated new hash: ".$calHash,4);
						if($hash != $calHash) {
							_log("Error extracting apps transfered",4);
							_log("restore existing apps", 4);
							$cmd = "cd ".ROOT."/web && rm -rf apps";
							shell_exec($cmd);
							$cmd = "cd ".ROOT."/web && mv apps_tmp apps";
							shell_exec($cmd);
							$cmd = "cd ".ROOT."/web && chown -R www-data:www-data apps";
							shell_exec($cmd);
						} else {
							$appsHashFile = Nodeutil::getAppsHashFile();
							file_put_contents($appsHashFile, $calHash);
							_log("Stored new hash",4);
							_log("delete backup", 4);
							$cmd = "cd ".ROOT."/web && rm -rf apps_tmp";
							shell_exec($cmd);
						}
						@unlink(self::getAppsLockFile());
					}
				}
			}
		}
	}

	static function calcAppsHash() {
		_log("Executing calcAppsHash", 3);
		$cmd = "cd ".ROOT."/web && tar -cf - apps --owner=0 --group=0 --sort=name --mode=744 --mtime='2020-01-01 00:00:00 UTC' | sha256sum";
		$res = shell_exec($cmd);
		$arr = explode(" ", $res);
		$appsHash = trim($arr[0]);
		return $appsHash;
	}

	static function sync($current, $largest_height, $peers, $most_common) {



	}

	static function getConfig() {
		global $db, $_config;
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

	static function extractAppsArchive() {
		$cmd = "cd ".ROOT."/web && rm -rf apps";
		shell_exec($cmd);
		$cmd = "cd ".ROOT." && tar -xzf tmp/apps.tar.gz -C . --owner=0 --group=0 --mode=744 --mtime='2020-01-01 00:00:00 UTC'";
		_log("Extracting archive : $cmd", 3);
		shell_exec($cmd);
		$cmd = "cd ".ROOT."/web && find apps -type f -exec touch {} +";
		shell_exec($cmd);
		$cmd = "cd ".ROOT."/web && find apps -type d -exec touch {} +";
		shell_exec($cmd);
		if (php_sapi_name() == 'cli') {
			$cmd = "cd ".ROOT."/web && chown -R www-data:www-data apps";
			shell_exec($cmd);
		}
		opcache_reset();
	}


}
