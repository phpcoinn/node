<?php

declare(ticks = 1);

class Daemon
{

	static $max_run_time = 2 * 60;
	static $run_interval = 30;

	static $options = [];

	static function getLockFile() {
		$name = static::$name;
		$lock_file = ROOT."/tmp/$name-lock";
		return $lock_file;
	}

	static function isEnabled() {
		global $_config;
		$name = static::$name;
		return isset($_config[$name]) && $_config[$name];
	}

	static function enable() {
		global $db;
		$name = static::$name;
		$db->setConfig($name, 1);
	}

	static function disable() {
		global $db;
		$name = static::$name;
		$db->setConfig($name, 0);
	}

	static function getStatus() {
		return null;
	}

	static function checkDaemon() {
		$name = static::$name;

		if(!static::isEnabled()) {
			_log("Daemon: $name: not enabled", 5);
			return;
		}
		if(defined(strtoupper($name)."_DAEMON_SKIP")) {
			_log("Daemon: $name: skipped", 5);
			return;
		}
		$lock_file = static::getLockFile();
		if (!file_exists($lock_file)) {
			_log("Daemon: $name: lock file not exists - check process", 5);
			$cmd = "ps uax | grep '".ROOT."/cli/$name.php' | grep -v grep";
			$res = shell_exec($cmd);
			if(empty($res)) {
				_log("Daemon: $name: process not exists - start it");
				$dir = ROOT . "/cli";
				$cmd = "php $dir/$name.php > /dev/null 2>&1  &";
				_log("Dapps: Start $name daemon: $cmd");
				system($cmd);
			} else {
				_log("Daemon: $name: process exists without lock!", 3);
			}
		} else {
			$pid_time = filemtime($lock_file);
			$elapsed = time() - $pid_time;
			$max_run_time = static::$max_run_time;
			if($elapsed > $max_run_time) {
				_log("Daemon: $name - running more than $max_run_time - remove lock", 5);
				self::unlock();
            }

		}
	}

    static function checkLongRunning() {
        $name = static::$name;
        $cmd = "php ".ROOT . "/cli/$name.php --daemon status";
        $res = shell_exec($cmd);
        $status = json_decode($res, true);
        if(!$status['enabled']) {
            _log("Daemon: checkLongRunning $name: enabled=false", 5);
            return;
        }
        _log("Daemon: checkLongRunning: ".json_encode($status), 5);
        if($status['running'] && !$status['locked']) {
            $started = $status['started'];
            $running_time = time() - $started;
            _log("Daemon: checkLongRunning: process exists without lock time=$running_time");
            if($running_time > static::$max_run_time * 2) {
                $pid = $status['pid'];
                _log("Daemon: checkLongRunning need to kill process $pid");
                $scmd = "kill -9 $pid";
                shell_exec($scmd);
            }
        } else if (!$status['running'] && $status['locked']) {
            _log("Daemon: checkLongRunning: process not running but locked");
            self::unlock();
        }
    }

    static function isDbNeeded() {
        return true;
    }

	static function runDaemon() {

		global $_config;

		set_time_limit(0);
		error_reporting(0);
		self::processArgs();

		$name = static::$name;

		$lock_file = ROOT."/tmp/$name-lock";
		$ignore_lock = isset(self::$options['ignore-lock']);
		if (!mkdir($lock_file, 0777) && !$ignore_lock) {
			_log("Daemon: $name - lock file in place - exit", 3);
			exit;
		}

		register_shutdown_function(function () use ($lock_file, $name) {
			$error = error_get_last();
			if ($error['type'] === E_ERROR) {
				_log("Daemon: $name - Error in execution ". $error['message']);
			}
			_log("Daemon: $name - exit function" , 3);
			self::unlock();
		});

		pcntl_signal(SIGINT, function() use ($name) {
			_log("Daemon: $name: Caught SIGINT" , 3);
			exit;
		});
		pcntl_signal(SIGTERM, function() use ($name) {
			_log("Daemon: $name: Caught SIGTERM", 3);
			exit;
		});

		$running = true;
		$started_time = time();

		$max_run_time = static::$max_run_time;

		$run_interval = static::$run_interval;

		$rune_once = self::hasArg("once");

		$control_file = ROOT . "/include/coinspec.inc.php";
		if($_config['chain_id']!=DEFAULT_CHAIN_ID) {
			$control_file = ROOT . "/include/coinspec.".$_config['chain_id']."inc.php";
		}
		$control_file_md5 = md5_file($control_file);
		$control_file_time = filemtime($control_file);
		_log("Daemon: control_file=$control_file md5=$control_file_md5 time=$control_file_time", 5);

        global $db;
        $db = null;

		_log("Daemon: $name - process started", 5);
		while($running) {
			_log("Daemon: $name - running loop", 5);
			$t1 = microtime(true);

            $db = new DB($_config['db_connect'], $_config['db_user'], $_config['db_pass'], $_config['enable_logging']);
            if (!$db) {
                _log("Daemon: can not connect database");
                break;
            }
            if($db->isSqlite()) {
                $db->exec('PRAGMA journal_mode=WAL;');
                $db->exec("PRAGMA busy_timeout=5000");
            } else {
//                $db->exec('set SESSION innodb_lock_wait_timeout=30');
            }

			global $_config;
			$_config = load_db_config();

			try {
				static::process();
				self::clearError();
                _logr();
                $db = null;

			} catch (Exception $e) {
				_log("Daemon: $name: error in process ".$e->getMessage());
				self::writeError($e);
			}
			$t2 = microtime(true);
			$diff = $t2 - $t1;
			_log("Daemon: $name - process finished in $diff sec", 5);
			if($rune_once) {
				_log("Daemon: $name - Run only once - exit" , 3);
				break;
			}
			clearstatcache($control_file);
			$control_file_md5_check = md5_file($control_file);
			$control_file_time_check = filemtime($control_file);
			_log("Daemon: control_file=$control_file start md5=$control_file_md5 time=$control_file_time", 5);
			_log("Daemon: control_file=$control_file check md5=$control_file_md5_check time=$control_file_time_check", 5);

			if($control_file_md5 != $control_file_md5_check) {
				_log("Daemon: Control file md5 check - unlock", 5);
				self::unlock();
			} else if($control_file_time != $control_file_time_check) {
				_log("Daemon: Control file time check - unlock", 5);
				self::unlock();
			}

			$running = file_exists($lock_file);
			if(!$running) {
				_log("Daemon: $name - Not exists lock file - exit" , 3);
				break;
			}
			$sleep_time = round($run_interval - $diff);
			_log("Daemon: $name - Calculated sleep time $sleep_time",5);
			if($sleep_time < 5) {
				$sleep_time = 5;
			}
			_log("Daemon: $name - sleeping $sleep_time", 5);
			sleep($sleep_time);
			_log("Daemon: $name - check max run time running=".(time() - $started_time)." max_run_time=".$max_run_time, 5);
			if(time() - $started_time > $max_run_time) {
				_log("Daemon: $name - Exceeded max run time - exit", 1);
				break;
			}
			$running = file_exists($lock_file);
		}

		self::unlock();

	}

	static function hasArg($a) {
		return isset(self::$options[$a]);
	}

	static function getArg($a, $def) {
		if(isset(self::$options[$a])) {
			return self::$options[$a];
		} else {
			return $def;
		}
	}

	static function processArgs() {
		global $argv;
		$name = static::$name;
		self::$options = [];
		$args = implode(" ",array_slice($argv, 1));
		$arr = explode("--", $args);
		foreach($arr as $line) {
			if(strlen($line)==0) {
				continue;
			}
			$line=trim($line);
			if(strpos($line, "=")!== false) {
				$arr2=explode("=", $line);
				self::$options[$arr2[0]]=$arr2[1];
			} else if(strpos($line, " ")!== false) {
				$arr2=explode(" ", $line);
				self::$options[$arr2[0]]=$arr2[1];
			} else {
				self::$options[$line]="";
			}
		}
		_log("Options: ".json_encode(self::$options),5);

		if(self::hasArg("daemon")) {
			$cmd = self::getArg("daemon", null);
			if (empty($cmd)) {
				_log("Daemon: $name - no args to process", 5);
				echo "$name.php --daemon {status|stop|kill|enable|disable|status-ext}" . PHP_EOL;
				exit;
			} else {
				_log("Daemon: $name - process command $cmd",5);
				if ($cmd == "status") {
					$lock_file = self::getLockFile();
					$locked = file_exists($lock_file);

					$scmd = "TZ=UTC0 ps -e -o pid,pcpu,pmem,lstart,user,cmd | grep ".ROOT."/cli/$name.php | grep -v grep | grep -v status";
					$res = shell_exec($scmd);
                    $res = trim($res);
					$data = [];
					$data['name'] = static::$title;
					if ($res) {
						$arr = preg_split("/\s+/", $res);
						$pid = $arr[0];
						if ($pid != getmypid()) {
							$started =array_slice($arr, 3, 5);
							$started = implode(" ", $started);
							$started = strtotime($started);
							$cpu = $arr[1];
							$memory = $arr[2];
							$data['running'] = true;
							$data['started'] = $started;
							$data['pid'] = $pid;
							$data['cpu'] = $cpu;
							$data['memory'] = $memory;
							$data['owner'] = $arr[8];
						} else {
							$data['running'] = false;
						}
					} else {
						$data['running'] = false;
					}
					$data['locked'] = $locked;
					$data['enabled'] = static::isEnabled();
					if ($locked) {
						$data['locked_time'] = filemtime($lock_file);
						$data['lock_file'] = $lock_file;
						$data['lock_owner'] = posix_getpwuid(fileowner($lock_file))["name"];
					}
					$error = self::getError();
					if($error) {
						$data['error']=$error['message'];
					}
					echo json_encode($data) . PHP_EOL;
					exit;
				} else if ($cmd == "stop") {
					self::unlock();
					exit;
				} else if ($cmd == "kill") {
					$cmd = "ps uax | grep ".ROOT."/cli/$name.php | grep -v grep";
					$res = shell_exec($cmd);
					$arr = preg_split("/\s+/", $res);
					$pid = $arr[1];
					if ($pid != getmypid()) {
						$scmd = "kill $pid";
						shell_exec($scmd);
					}
					exit;
				} else if ($cmd == "enable") {
					static::enable();
					exit;
				} else if ($cmd == "disable") {
					static::disable();
					exit;
				} else if ($cmd == "status-ext") {
					$status = static::getStatus();
					echo json_encode($status) . PHP_EOL;
					exit;
				} else {
					_log("Daemon: Unknown command $cmd");
					echo "Unknown command $cmd" . PHP_EOL;
					exit;
				}
			}
		}
	}

	static function unlock() {
		$name = static::$name;
		_log("Daemon: $name - remove lock file");
		$lock_dir = ROOT."/tmp/$name-lock";
		$error_file = $lock_dir ."/error";
		@unlink($error_file);
		@rmdir($lock_dir);
	}

	static function writeError(Exception  $e) {
		$name = static::$name;
		$lock_dir = ROOT."/tmp/$name-lock";
		$error_file = $lock_dir ."/error";
		$error = [
			"message"=>$e->getMessage(),
			"trace"=>$e->getTraceAsString()
		];
		@file_put_contents($error_file, json_encode($error));
		@chmod($error_file, 0777);
	}

	static function clearError() {
		$name = static::$name;
		$lock_dir = ROOT."/tmp/$name-lock";
		$error_file = $lock_dir ."/error";
		@unlink($error_file);
	}

	static function getError() {
		$name = static::$name;
		$lock_dir = ROOT."/tmp/$name-lock";
		$error_file = $lock_dir ."/error";
		$res = @file_get_contents($error_file);
		return json_decode($res, true);
	}

	static function availableDaemons() {
		$daemons = ["dapps", "miner", "sync","masternode"];
		return $daemons;
	}

	static function getDaemonStatus($daemon) {
		$cmd = "php ".ROOT."/cli/$daemon.php --daemon status";
		$res = shell_exec($cmd);
		return json_decode($res, true);
	}

	static function runAtInterval($name, $interval, $callable) {
		static $executed;
		$min = intval(date("i"));
		$key = "{$name}-{$min}";
		$run = $min % $interval == 0 && !$executed[$key];
		_log("MNC: runAtInterval min=$min run=$run", 5);
		if($run) {
			if(is_callable($callable)) {
				call_user_func($callable);
				$executed[$key] = true;
			}
		}
	}

}
