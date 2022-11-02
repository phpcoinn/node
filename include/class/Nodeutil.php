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
			if(in_array($x, ['apps.hash'])) {
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
		$sync = Config::isSync();
		if ($sync) {
			_log("Sync running. Wait for it to finish");
			return;
		}
		$db->fkCheck(false);
		$tables = ["accounts", "transactions", "mempool", "masternode","blocks","smart_contracts","smart_contract_state"];
		foreach ($tables as $table) {
			$db->truncate($table);
		}
		$db->fkCheck(true);

		_log("The database has been cleared");
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
		$sync = Config::isSync();
		if ($sync) {
			$_SESSION['msg'] = [['icon' => 'warning', 'text' => 'Sync running. Wait for it to finish']];
			_log("Sync running. Wait for it to finish", 3);
			return;
		}
		$no = intval($no);
		Block::pop($no);
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
		if(isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
			$ip = san_ip($_SERVER['HTTP_CF_CONNECTING_IP']);
		} elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = san_ip($_SERVER['HTTP_X_FORWARDED_FOR']);
		} else {
			$ip = san_ip($_SERVER['REMOTE_ADDR']);
		}
		$ip = Peer::validateIp($ip);
		if(!$ip) {
			_log("Peer Request: invalid ip = $ip SERVER=".json_encode($_SERVER));
		}
		return $ip;
	}

	static function downloadApps(&$error=null) {
		global $_config;

		if(!FEATURE_APPS) {
			_log("Apps feature disabled");
			return true;
		}

		if(!defined("APPS_REPO_SERVER")) {
			if($_config['testnet'] ) {
				define("APPS_REPO_SERVER", "https://repo.testnet.phpcoin.net:8001");
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

		$res = peer_post(APPS_REPO_SERVER."/peer.php?q=getApps", [], 30, $err);
		_log("Contancting repo server response=".json_encode($res),3);
		if($res === false) {
			$error = "No response from repo server err=$err";
			_log($error,2);
			return false;
		} else {
			$hash = $res['hash'];
			$appsHashCalc = Nodeutil::calcAppsHash();
			if($appsHashCalc == $hash) {
				_log("Apps are up to date");
				return true;
			}
			$signature = $res['signature'];
			$verify = Account::checkSignature($hash, $signature, APPS_REPO_SERVER_PUBLIC_KEY);
			_log("Verify repo response hash=$hash signature=$signature verify=$verify",3);
			if(!$verify) {
				$error = "Not verified signature from repo server";
				_log($error,2);
				return false;
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
							$error = "Apps lock file exists - can not update";
							_log($error);
							return false;
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
							chmod($appsHashFile, 0777);
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

		return true;
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

	static function measure() {
		if(isset($GLOBALS['start_time'])) {
			$GLOBALS['end_time']=microtime(true);
			$time = $GLOBALS['end_time'] - $GLOBALS['start_time'];
			if($time > 1) {
				_log("Time: url=".$_SERVER['REQUEST_URI']." time=$time HTTP_USER_AGENT=".$_SERVER['HTTP_USER_AGENT']);
				$prev_time = $GLOBALS['start_time'];
				foreach($GLOBALS['measure'] as $section => $t) {
					$diff = $t - $prev_time;
					_log("Time: url=".$_SERVER['REQUEST_URI']." section=$section time=$t diff=$diff");
					$prev_time = $t;
				}
			}
		}
	}

	static function addMeasurePoint($name = null) {
		if($name == null) {
			$bt =  debug_backtrace();
			$name = $bt[0]['file'].":".$bt[0]['line'];
		}
		$GLOBALS['measure'][$name]=microtime(true);
	}

	static function runSingleProcess($cmd) {
		$res = shell_exec("ps uax | grep '$cmd' | grep -v grep");
		if(!$res) {
			$exec_cmd = "$cmd > /dev/null 2>&1  &";
			system($exec_cmd);
		}
	}

	static function getTableRowsCount() {
		global $db;
		$db_name = $db->single('select database()');
		$sql = "select * from information_schema.TABLES where TABLE_SCHEMA = :dbname";
		$rows = $db->run($sql, [":dbname"=>$db_name]);
		$rowCounts = [];
		foreach($rows as $row) {
			$rowCounts[$row['TABLE_NAME']]=$row['TABLE_ROWS'];
		}
		return $rowCounts;
	}

	static function getNodeDevInfo() {
		global $_config;
		$data = [];
		$data['system']['version']=shell_exec("lsb_release -a");
		$data['system']['kernel']=shell_exec("uname -a");
		$data['serverData']=self::getServerData();
		$daemons = Daemon::availableDaemons();
		foreach($daemons as $daemon) {
			$status = Daemon::getDaemonStatus($daemon);
			$data['daemons'][$daemon]=$status;
		}
		$data['php']['version']=phpversion();
		$data['php']['extensions']=get_loaded_extensions();
		$data['db']=self::getDbData();
		$config = $_config;
		foreach($config as $key=>$val) {
			if(strpos($key, "private")!==false){
				unset($config[$key]);
			}
			if(strpos($key, "password")!==false){
				unset($config[$key]);
			}
		}
		$data['config']=$config;
		$logData = self::getLogData();
		$data['log']=explode(PHP_EOL, $logData);
		$data['nodeInfo']=self::getNodeInfo();
		$data['peer']=Peer::getInfo();
		$tmp_folder = ROOT."/tmp";
		$appsHashFile = Nodeutil::getAppsHashFile();
		$appsArchive = ROOT . "/tmp/apps.tar.gz";
		$cache_folder = Cache::$path;
		$folders = [
			"root"=>ROOT,
			'tmp_folder'=>$tmp_folder,
			'hash_file'=>$appsHashFile,
			'apps_archive'=>$appsArchive,
			'cache_folder'=>$cache_folder,
			'dapps_folder'=>Dapps::getDappsDir(),
			'log_file'=>ROOT ."/tmp/phpcoin.log",
			"db_migrate_lock"=>ROOT . "/tmp/db-migrate"
		];

		foreach ($folders as $name => $folder) {
			$data['folders'][$name]['path']=$folder;
			$data['folders'][$name]['exists']=file_exists($folder);
			$data['folders'][$name]['owner']=shell_exec("stat -c '%U' $folder");
			$data['folders'][$name]['perms']=shell_exec("stat -c '%a' $folder");
		}

		$propagate_file = ROOT . "/tmp/propagate_info.txt";
		$data['propagate_info']=json_decode(file_get_contents($propagate_file), true);
		$git_status=shell_exec("cd ".ROOT." && git status");
		$data['git_version']=shell_exec("git --version");
		$data['git_status']=explode(PHP_EOL, $git_status);
		return $data;
	}

	static function getNodeDebug() {
		$logData = self::getLogData(1000);
		$log=explode(PHP_EOL, $logData);
		return $log;
	}

	static function getServerData() {
		$serverData = [];
		$serverData['hostname']=gethostname();
		$minerStatFile = NodeMiner::getStatFile();
		if(file_exists($minerStatFile)) {
			$minerStat = file_get_contents($minerStatFile);
			$minerStat = json_decode($minerStat, true);
		}
		// Linux CPU
		$load = sys_getloadavg();
		$cpuload = $load[0];
		// Linux MEM
		$free = shell_exec('free');
		$free = (string)trim($free);
		$free_arr = explode("\n", $free);
		$mem = explode(" ", $free_arr[1]);
		$mem = array_filter($mem, function($value) { return ($value !== null && $value !== false && $value !== ''); }); // removes nulls from array
		$mem = array_merge($mem); // puts arrays back to [0],[1],[2] after
		$memtotal = round($mem[1] / 1000000,2);
		$memused = round($mem[2] / 1000000,2);
		$memfree = round($mem[3] / 1000000,2);
		$memshared = round($mem[4] / 1000000,2);
		$memcached = round($mem[5] / 1000000,2);
		$memavailable = round($mem[6] / 1000000,2);
		// Linux Connections
		$connections = `netstat -ntu | grep :80 | grep ESTABLISHED | grep -v LISTEN | awk '{print $5}' | cut -d: -f1 | sort | uniq -c | sort -rn | grep -v 127.0.0.1 | wc -l`;
		$totalconnections = `netstat -ntu | grep :80 | grep -v LISTEN | awk '{print $5}' | cut -d: -f1 | sort | uniq -c | sort -rn | grep -v 127.0.0.1 | wc -l`;

		$connections=trim($connections);
		$totalconnections=trim($totalconnections);

		$memusage = round(($memavailable/$memtotal)*100);
		$phpload = round(memory_get_usage() / 1000000,2);
		$diskfree = round(disk_free_space(".") / 1000000000);
		$disktotal = round(disk_total_space(".") / 1000000000);
		$diskused = round($disktotal - $diskfree);
		$diskusage = round($diskused/$disktotal*100);

		$serverData['stat']['memusage']=$memusage;
		$serverData['stat']['cpuload']=$cpuload;
		$serverData['stat']['diskusage']=$diskusage;
		$serverData['stat']['connections']=$connections;
		$serverData['stat']['totalconnections']=$totalconnections;
		$serverData['stat']['memtotal']=$memtotal;
		$serverData['stat']['memused']=$memused;
		$serverData['stat']['memavailable']=$memavailable;
		$serverData['stat']['diskfree']=$diskfree;
		$serverData['stat']['diskused']=$diskused;
		$serverData['stat']['disktotal']=$disktotal;
		$serverData['stat']['phpload']=$phpload;
		return $serverData;
	}

	static function getDbData()  {
		global $_config, $db;
		$dbData['connection']=$_config['db_connect'];
		$dbData['driver'] = substr($_config['db_connect'], 0, strpos($_config['db_connect'], ":"));
		$db_name=substr($_config['db_connect'], strrpos($_config['db_connect'], "dbname=")+7);
		$dbData['db_name']=$db_name;
		if($dbData['driver'] === "mysql") {
			$dbData['server'] = shell_exec("mysql --version");
		} else if ($dbData['driver'] === "sqlite") {
			$version = $db->single("select sqlite_version();");
			$dbData['server'] = $version;
		}
		$rowCounts = Nodeutil::getTableRowsCount();
		foreach ($rowCounts as $table => $cnt) {
			$dbData['tables'][$table]=$cnt;
		}
		$dbData['dbversion']=$_config['dbversion'];
		return $dbData;
	}

	static function getLogData($lines=100) {
		global $_config;
		$log_file = $_config['log_file'];
		if(substr($log_file, 0, 1)!= "/") {
			$log_file = ROOT . "/" .$log_file;
		}
		$cmd = "tail -n $lines $log_file";
		$logData = shell_exec($cmd);
		return $logData;
	}

	static function getNodeInfo() {
		global $db, $_config;
		$dbVersion = $db->single("SELECT val FROM config WHERE cfg='dbversion'");
		$hostname = $db->single("SELECT val FROM config WHERE cfg='hostname'");
		$accounts = $db->single("SELECT COUNT(1) FROM accounts");
		$tr = $db->single("SELECT COUNT(1) FROM transactions");
		$masternodes = $db->single("SELECT COUNT(1) FROM masternode");
		$mempool = Mempool::getSize();
		$peers = Peer::getCount();
		$current = Block::current();
		$generator = isset($_config['generator_public_key']) && $_config['generator'] ? Account::getAddress($_config['generator_public_key']) : null;
		$miner = isset($_config['miner_public_key']) && $_config['miner'] ? Account::getAddress($_config['miner_public_key']) : null;
		$masternode = isset($_config['masternode_public_key']) && $_config['masternode'] ? Account::getAddress($_config['masternode_public_key']) : null;

		$avgBlockTime10 = Blockchain::getAvgBlockTime(10);
		$avgBlockTime100 = Blockchain::getAvgBlockTime(100);

		$hashRate10 = round(Blockchain::getHashRate(10),2);
		$hashRate100 = round(Blockchain::getHashRate(100),2);
		$circulation = Account::getCirculation();

		return [
			'hostname'     => $hostname,
			'version'      => VERSION,
			'network'      => NETWORK,
			'chain_id'     => Block::getChainId(Block::getHeight()),
			'dbversion'    => $dbVersion,
			'accounts'     => $accounts,
			'transactions' => $tr,
			'mempool'      => $mempool,
			'masternodes'  => $masternodes,
			'peers'        => $peers,
			'height'       => $current['height'],
			'block'        => $current['id'],
			'time'         => time(),
			'generator'    => $generator,
			'miner'        => $miner,
			'masternode'   => $masternode,
			'totalSupply'  => Blockchain::getTotalSupply(),
			'currentSupply'  => $circulation,
			'avgBlockTime10'  => $avgBlockTime10,
			'avgBlockTime100'  => $avgBlockTime100,
			'hashRate10'=>$hashRate10,
			'hashRate100'=>$hashRate100,
			'lastBlockTime'=>$current['date']
		];
	}

	static function isRepoServer() {
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

}
