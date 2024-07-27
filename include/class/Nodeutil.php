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
	
	static function calculateSmartContractsHash($height=null) {
		global $db;
        if(empty($height)) {
            $height = 0;
        }
        $res=$db->run("SELECT * FROM smart_contract_state where height >= :height order by height, sc_address, variable, var_key, var_value",
            [":height"=>$height]);
		return [
			'height'=>Block::getHeight(),
            'count'=>count($res),
			'hash'=>md5(json_encode($res))
		];
	}

    static function calculateSmartContractsHash1($height) {
        global $db;
            $start_height = $height - 100;
            $end_height  = $height;
            $res=$db->run("SELECT * FROM smart_contract_state where height >= :start_height and height < :end_height 
                                   order by height, sc_address, variable, var_key, var_value",
            [":start_height"=>$start_height, ":end_height"=>$end_height]);
        return [
            'height'=>Block::getHeight(),
            'count'=>count($res),
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

	static function measure($out=false) {
        global $argv;
        if(isset($GLOBALS['start_time'])) {
			$GLOBALS['end_time']=microtime(true);
			$time = $GLOBALS['end_time'] - $GLOBALS['start_time'];
			if($time > 1) {
//				_log("Time: url=".$_SERVER['REQUEST_URI']." time=$time HTTP_USER_AGENT=".$_SERVER['HTTP_USER_AGENT']);
				$prev_time = $GLOBALS['start_time'];
                $prev_section = null;
				foreach($GLOBALS['measure'] as $section => $t) {
					$diff = round($t - $prev_time,3);
                    if($diff>1) {
                        if(php_sapi_name() === 'cli') {
                            $url="CLI:".$argv[0];
                        } else {
                            $url="WEB:".$_SERVER['REQUEST_URI'];
                        }
					    _log("Time: url=$url diff=$diff section=$prev_section > $section");
                    }
                    if($out) {
                        echo "section=$section time=$t diff=$diff <br/>";
                    }
					$prev_time = $t;
                    $prev_section = $section;
				}
			}
		}
	}

	static function addMeasurePoint($name = null) {
		if($name == null) {
			$bt =  debug_backtrace();
			$name = $bt[0]['file'].":".$bt[0]['line'];
            $name = str_replace(ROOT, "", $name);
		}
		$GLOBALS['measure'][$name]=microtime(true);
	}

	static function runSingleProcess($cmd, $check_cmd = null, $user=null) {
		_log("runSingleProcess $cmd", 5);
        $exec_cmd = "$cmd > /dev/null 2>&1  &";
        if(!empty($user)) {
            $exec_cmd="sudo -u $user ".escapeshellcmd($exec_cmd);
        }
        system($exec_cmd);
	}

	static function psAux($cmd, $timeout=null, $psCmd = "ps aux", &$result_code=null){
	  	$t1=microtime(true);
	  	$full_cmd="$psCmd | grep '$cmd' | grep -v grep";
	  	if(!empty($timeout)){
              $full_cmd="timeout --signal=SIGINT $timeout bash -c \"$full_cmd\"";
	  	}
	  	$res = exec($full_cmd, $out, $result_code);
		$t2=microtime(true);
		$elapsed=number_format($t2-$t1,3);
		if($result_code==0) {
		  	return $out;    //found
		} else if ($result_code==1){
		  	return null;    //not found
		} else {
            return false;   //error or timeout
        }
	}

	static function runProcess($cmd) {
        $exec_cmd = "$cmd > /dev/null 2>&1  &";
        system($exec_cmd);
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
        $tasks = Task::availableTasks();
        foreach ($tasks as $task) {
            $status = $task::getTaskStatus();
            $data['tasks'][$task]=$status;
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
		$cron_list = shell_exec("crontab -l");
		$data['cron_list']=explode(PHP_EOL, $cron_list);
		$php_processes = shell_exec("ps aux | grep php");
		$data['php_processes']=explode(PHP_EOL, $php_processes);
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
            $dbData['server'] = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
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

	static function getNodeInfo($basic=false,$nocache=false) {
		global $db, $_config;
		$hostname = $db->single("SELECT val FROM config WHERE cfg='hostname'");
		$current = Block::current();
		$generator = isset($_config['generator_public_key']) && $_config['generator'] ? Account::getAddress($_config['generator_public_key']) : null;
		$miner = isset($_config['miner_public_key']) && $_config['miner'] ? Account::getAddress($_config['miner_public_key']) : null;
		$masternode = isset($_config['masternode_public_key']) && $_config['masternode'] ? Account::getAddress($_config['masternode_public_key']) : null;

        if($basic) {
            return [
                'hostname'     => $hostname,
                'version'      => VERSION,
                'build_version'     => BUILD_VERSION,
                'network'      => NETWORK,
                'chain_id'     => CHAIN_ID,
                'height'       => $current['height'],
                'block'        => $current['id'],
                'time'         => time(),
                'generator'    => $generator,
                'miner'        => $miner,
                'masternode'   => $masternode,
                'lastBlockTime'=>$current['date'],
                'php_version' => PHP_VERSION
            ];
        }

        function getNodeInfoData() {
            global $db;
            $dbVersion = $db->single("SELECT val FROM config WHERE cfg='dbversion'");
            $accounts = $db->single("SELECT COUNT(1) FROM accounts");
            $tr = Transaction::getCount();
            $masternodes = $db->single("SELECT COUNT(1) FROM masternode");
            $avgBlockTime10 = Blockchain::getAvgBlockTime(10);
            $avgBlockTime100 = Blockchain::getAvgBlockTime(100);

            $hashRate10 = round(Blockchain::getHashRate(10),2);
            $hashRate100 = round(Blockchain::getHashRate(100),2);
            $circulation = Account::getCirculation();
            $peers = Peer::getCount();
            $data['dbVersion']=$dbVersion;
            $data['accounts']=$accounts;
            $data['tr']=$tr;
            $data['masternodes']=$masternodes;
            $data['avgBlockTime10']=$avgBlockTime10;
            $data['avgBlockTime100']=$avgBlockTime100;
            $data['hashRate10']=$hashRate10;
            $data['hashRate100']=$hashRate100;
            $data['circulation']=$circulation;
            $data['peers']=$peers;
            return $data;
        }

        if($nocache) {
            $cachedData = getNodeInfoData();
        } else {
            $cachedData = Cache::getTempCache('nodeInfo', 600, function() {
                return getNodeInfoData();
            });
        }

		$mempool = Mempool::getSize();

		return [
			'hostname'     => $hostname,
			'version'      => VERSION,
			'network'      => NETWORK,
			'chain_id'     => CHAIN_ID,
			'dbversion'    => $cachedData['dbVersion'],
			'accounts'     => $cachedData['accounts'],
			'transactions' => $cachedData['tr'],
			'mempool'      => $mempool,
			'masternodes'  => $cachedData['masternodes'],
			'peers'        => $cachedData['peers'],
			'height'       => $current['height'],
			'block'        => $current['id'],
			'time'         => time(),
			'generator'    => $generator,
			'miner'        => $miner,
			'masternode'   => $masternode,
			'totalSupply'  => Blockchain::getTotalSupply(),
			'currentSupply'  => $cachedData['circulation'],
			'avgBlockTime10'  => $cachedData['avgBlockTime10'],
			'avgBlockTime100'  =>  $cachedData['avgBlockTime100'],
			'hashRate10'=>$cachedData['hashRate10'],
			'hashRate100'=>$cachedData['hashRate100'],
			'lastBlockTime'=>$current['date'],
            'php_version' => PHP_VERSION
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

    static function discoverPeers() {
        global $_config;
        $peers = Peer::getAll();
        $added = 0;
        $discovered = 0;
        if($peers) {
            $count=count($peers);
            _log("Found " . $count . " root peers");

            $peers_list = [];
            foreach ($peers as $index=>$peer) {
                $hostname = $peer['hostname'];
                $peers_list[$hostname] = $hostname;
            }

            foreach ($peers as $index=>$peer) {
                $hostname = $peer['hostname'];
                $peers_list[$hostname] = $hostname;
                $url = "$hostname/api.php?q=getPeers";
                $res = @file_get_contents($url);
                $peers2 = json_decode($res, true)['data'];
                if ($peers2) {
                    $count2 = count($peers2);
                    _log("Checking peers from other $hostname: $index / $count - found $count2");
                    foreach ($peers2 as $index2 => $peer2) {
                        $hostname2 = $peer2['hostname'];
                        if (!isset($peers_list[$hostname2]) && $hostname2 !=$_config['hostname']) {
                            $discovered++;
                            $peers_list[$hostname2] = $hostname2;
                            $res = peer_post($hostname2."/peer.php?q=peer", ["hostname" => $_config['hostname'], 'repeer'=>1]);
                            if($res !== false ) {
                                $added ++;
                            }
                            _log("Discovered new peer: $hostname2  - discovered = $discovered added=$added - res=".$res);
                        }
                    }
                }

            }
        }
        _log("Total discovered peers = $discovered - added = $added");
    }

    static function readMiningStat() {
        $mining_stat_file = ROOT . '/tmp/mining-stat.json';
        if(file_exists($mining_stat_file)) {
            $mining_stat = json_decode(file_get_contents($mining_stat_file), true);
        }
        if(empty($mining_stat)) {
            $mining_stat = [];
        }
        return $mining_stat;
    }

    static function saveMiningStat($mining_stat) {
        $mining_stat_file = ROOT . '/tmp/mining-stat.json';
        file_put_contents($mining_stat_file, json_encode($mining_stat));
    }

    static function processMiningStat($data) {
        _log("processMiningStat data=".json_encode($data));
        $miningStat = Nodeutil::readMiningStat();
        $hashes = $data['hashes'];
        $interval = $data['interval'];
        $height = $data['height'];
        $address = $data['address'];
        $minerid = $data['minerid'];
        $ip = $_SERVER['REMOTE_ADDR'];
        @$miningStat['totals'][$height]['hashes']+=$hashes;
        @$miningStat['totals'][$height]['intervals']+=$interval;
        @$miningStat['totals'][$height]['miner'][$minerid]=$minerid;
        @$miningStat['totals'][$height]['address'][$address]=$address;
        @$miningStat['totals'][$height]['ip'][$ip]=$ip;
        Nodeutil::saveMiningStat($miningStat);
    }

    static function clearOldMiningStat() {
        $miningStat = Nodeutil::readMiningStat();
        if(!isset($miningStat['totals'])) {
            return;
        }
        $current_height = Block::getHeight();
        $count1 = count(array_keys($miningStat['totals']));
        foreach ($miningStat['totals'] as $height => $stat) {
            if($height < $current_height - 100) {
                unset($miningStat['totals'][$height]);
            }
        }
        $count2 = count(array_keys($miningStat['totals']));
        $deleted = $count1 - $count2;
        _log("clearOldMiningStat deleted=$deleted",3);
        Nodeutil::saveMiningStat($miningStat);
    }

    static function resetMiningStats() {
        $mining_stat_file = ROOT . '/tmp/mining-stat.json';
        @unlink($mining_stat_file);
        $generator_stat_file = ROOT . '/tmp/generator-stat.json';
        @unlink($generator_stat_file);
    }

    static function getHashrateStat() {
        $data = self::readMiningStat();
        $currentHeight = Block::getHeight();

        try {

            $last10blocks = [
                "hashes"=>0,
                "count"=>0
            ];
            $last100blocks = [
                "hashes"=>0,
                "count"=>0
            ];
            $stat = [];
            $stat['current']['hashRate']=0;
            $stat['current']['address']=0;
            $stat['current']['miner']=0;
            $stat['current']['ip']=0;
            $stat['prev']['hashRate']=0;
            $stat['prev']['address']=0;
            $stat['prev']['miner']=0;
            $stat['prev']['ip']=0;
            foreach ($data['totals'] as $height => $item) {
                if($height == $currentHeight) {
                    $stat['current']['hashRate'] = round($item['hashes'] / 60, 2);
                    $stat['current']['address'] = count($item['address']);
                    $stat['current']['miner'] = count($item['miner']);
                    $stat['current']['ip'] = count($item['ip']);
                }
                if($height == $currentHeight-1) {
                    $stat['prev']['hashRate'] = round($item['hashes'] / 60, 2);
                    $stat['prev']['address'] = count($item['address']);
                    $stat['prev']['miner'] = count($item['miner']);
                    $stat['prev']['ip'] = count($item['ip']);
                }
                if($height >= $currentHeight - 10 ) {
                    $last10blocks['hashes']+=$item['hashes'];
                    $last10blocks['count']++;
                    foreach ($item['address'] as $address) {
                        $last10blocks['address'][$address]=$address;
                    }
                    foreach ($item['miner'] as $miner) {
                        $last10blocks['miner'][$miner]=$miner;
                    }
                    foreach ($item['ip'] as $ip) {
                        $last10blocks['ip'][$ip]=$ip;
                    }
                }
                if($height >= $currentHeight - 100 ) {
                    $last100blocks['hashes']+=$item['hashes'];
                    $last100blocks['count']++;
                    foreach ($item['address'] as $address) {
                        $last100blocks['address'][$address]=$address;
                    }
                    foreach ($item['miner'] as $miner) {
                        $last100blocks['miner'][$miner]=$miner;
                    }
                    foreach ($item['ip'] as $ip) {
                        $last100blocks['ip'][$ip]=$ip;
                    }
                }
            }

            $stat['last10blocks']['hashRate']=$last10blocks['count'] ==0 ? 0 : round($last10blocks['hashes']/(60*$last10blocks['count']),2);
            $stat['last10blocks']['address']=count($last10blocks['address']);
            $stat['last10blocks']['miner']=count($last10blocks['miner']);
            $stat['last10blocks']['ip']=count($last10blocks['ip']);
            $stat['last100blocks']['hashRate']=$last100blocks['count'] == 0 ? 0 : round($last100blocks['hashes']/(60*$last100blocks['count']),2);
            $stat['last100blocks']['address']=count($last100blocks['address']);
            $stat['last100blocks']['miner']=count($last100blocks['miner']);
            $stat['last100blocks']['ip']=count($last100blocks['ip']);

        } catch (Error $e) {
//            _log("MINE_STAT ERROR=".json_encode(["error"=>$e->getMessage(), "trace"=>$e->getTraceAsString()]));
            $stat=[];
        }
        return $stat;
    }

    static function initPeers() {
        $peers = Peer::getInitialPeers();
        _log("Fork: initPeers ".count($peers));
        $forker = Forker::instance();
        $cnt = 0;
        $responses = [
            "success"=>0,
            "failed"=>0
        ];
        define("FORKED_PROCESS", getmypid());
        $info=Peer::getInfo();
        foreach ($peers as $peer) {
            $cnt++;
//            if($cnt > 20) break;
            $forker->fork(function ($peer) use ($info) {

                global $_config;


                if(!Peer::validate($peer)) {
                    return ["response"=>false, "error"=>"Peer not validated"];
                }

                _log("Fork: Process peer ".$peer, 4);

                if($peer === $_config['hostname']) {
                    return ["response"=>false, "error"=>"Peer is local"];
                }

                if ($_config['passive_peering'] == true) {
                    DB::reconnect();
                    $res=Peer::insert(md5($peer), $peer);
                } else {
                    $res = peer_post($peer."/peer.php?q=peer", ["hostname" => $_config['hostname'], "repeer" => 1], 30, $err, $info);
                    _log("Fork: Response post from peer ".$peer. " res=".json_encode($res), 5);
                }
                if ($res !== false) {
                    _log("Peering OK - $peer");
                    return ["response"=>true, "data"=>$res];
                } else {
                    _log("Peering FAIL - $peer Error: $err");
                    return ["response"=>false, "error"=>$err];
                }

            }, $peer);
        }
        $forker->on(function ($res) use (&$responses) {
            _log("Fork: Data from fork ".json_encode($res),5);
            if($res['response']) {
                $responses['success']++;
            } else {
                $responses['failed']++;
            }
        });
        $forker->exec();
        _log("Fork: Completed initPeers");
        _log("Fork: ".json_encode($responses));

    }

    static function measureTime($name, Closure $fn) {
        $t1=microtime(true);
        $res = $fn();
        $t2=microtime(true);
        $diff = round($t2 - $t1, 2);
        _log("Measure time $name time=$diff");
        return $res;
    }

    static function debugError(Error $e) {
        _log("DEBUG error:" . $e->getMessage());
        $backtrace = debug_backtrace();
        $backtrace_str = [];
        if (!empty($backtrace)) {
            foreach ($backtrace as $info) {
                if ($info["file"] != __FILE__) {
                    $backtrace_str[] = $info["file"]." at line ".$info["line"];
                }
            }
        }
        foreach ($backtrace_str as $line) {
            _log("DEBUG error: BACKTRACE: " . $line);
        }
    }

    static function checkStuckMempool() {
        $block = Block::current();
        $date = $block['date'];
        $elapsed = time() - $date;
        _log("checkStuckMempool elapsed=$elapsed");
        if($elapsed > 60*60) {
            $size = Mempool::getSize();
            _log("checkStuckMempool size=$size");
            if($size > 0) {
                Mempool::empty_mempool();
                _log("checkStuckMempool cleared mempool");
            }
        }
    }


    static function calculateSmartContractsHashV2($height) {
        global $db;
        if(empty($height)) {
            $height=Block::getHeight();
        }
        $res=$db->run("SELECT * FROM smart_contract_state where height < :height 
                                order by height desc, sc_address, variable, var_key, var_value
                                limit 100",
            [":height"=>$height]);
        return [
            'height'=>$height,
            'count'=>count($res),
            'hash'=>md5(json_encode($res))
        ];
    }

    static function runAtInterval($name, $interval, $callable)
    {
        global $db;
        $lockDir = ROOT . '/tmp/run-' . $name.".lock";
        $lastExecution=$db->getConfig("ts-$name");
        $currentTime = time();
        $elapsed = $currentTime - $lastExecution;
        if ($elapsed >= $interval) {
            if(@mkdir($lockDir)) {
                return;
            }
            $db->setConfig("ts-$name", $currentTime);
            if (is_callable($callable)) {
                call_user_func($callable);
            }
            @rmdir($lockDir);
        }

    }
}
