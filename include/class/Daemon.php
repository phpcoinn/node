<?php

declare(ticks = 1);

class Daemon
{

	static $max_locked_time = 60 * 60;
	static $max_run_time_min = (DEVELOPMENT ? 10 : 60) * 60;
	static $run_interval = 30;
	static $max_stuck_time = 60 * (DEVELOPMENT ? 2 : 10);

	static function getLockFile() {
		$name = static::$name;
		$lock_file = ROOT."/tmp/$name-lock";
		return $lock_file;
	}


	static function checkDaemon() {
		$name = static::$name;
		_log("Daemon: $name: check");

		if(!static::isEnabled()) {
			_log("Daemon: $name: not enabled");
			return;
		}
		if(defined(strtoupper($name)."_DAEMON_SKIP")) {
			_log("Daemon: $name: skipped");
			return;
		}
		$lock_file = self::getLockFile();
		_log("Daemon: $name: check lock file $lock_file");
		if (!file_exists($lock_file)) {
			_log("Daemon: $name: lock file not exists - check process");
			$cmd = "ps uax | grep '".ROOT."/cli/$name.php' | grep -v grep";
			$res = shell_exec($cmd);
			if(empty($res)) {
				_log("Daemon: $name: process not exists - start it");
				$dir = ROOT . "/cli";
				$cmd = "php $dir/$name.php > /dev/null 2>&1  &";
				_log("Dapps: Start $name daemon: $cmd");
				system($cmd);
			} else {
				_log("Daemon: $name: process exists without lock!");
			}
		} else {
			_log("Daemon: $name: lock file exists - check process");
			$cmd = "ps uax | grep '".ROOT."/cli/$name.php' | grep -v grep";
			$res = shell_exec($cmd);
			if(empty($res)) {
				_log("Daemon: $name: process not exists - check stucked");
				$pid_time = filemtime($lock_file);
				$elapsed = time() - $pid_time;

				$max_stuck_time = static::$max_stuck_time;

				if($elapsed > $max_stuck_time) {
					_log("Daemon: $name: process stucked for $elapsed secs - remove lock");
					@rmdir($lock_file);
				} else {
					_log("Daemon: $name: process stucked but not elapsed stuck check elapsed=$elapsed max_stuck_time=$max_stuck_time");
				}
			} else {
				_log("Daemon: $name: process exists - OK");
			}
		}
	}

	static function runDaemon() {
		set_time_limit(0);
		error_reporting(0);

		self::processArgs();

		$name = static::$name;
		$max_locked_time = static::$max_locked_time;

		_log("Daemon: run $name");

		$lock_file = ROOT."/tmp/$name-lock";
		if (!mkdir($lock_file, 0700)) {
			$pid_time = filemtime($lock_file);
			if (time() - $pid_time > $max_locked_time) {
				@rmdir($lock_file);
			}
			_log("Daemon: $name - lock file in place - exit");
			exit;
		}

		register_shutdown_function(function () use ($lock_file, $name) {
			$error = error_get_last();
			if ($error['type'] === E_ERROR) {
				_log("Daemon: $name - Error in execution ". $error['message']);
			}
			_log("Daemon: $name - exit function");
			@rmdir($lock_file);
		});

		pcntl_signal(SIGINT, function() use ($name) {
			_log("Daemon: $name: Caught SIGINT");
			exit;
		});
		pcntl_signal(SIGTERM, function() use ($name) {
			_log("Daemon: $name: Caught SIGTERM");
			exit;
		});

		$running = true;
		$started_time = time();

		$max_run_time_min = static::$max_run_time_min;

		$run_interval = static::$run_interval;

		while($running) {
			_log("Daemon: $name - process started");
			$t1 = microtime(true);
			static::process();
			$t2 = microtime(true);
			$diff = $t2 - $t1;
			_log("Daemon: $name - process finished in $diff sec");
			$running = file_exists($lock_file);
			sleep($run_interval);
			if(time() - $started_time > $max_run_time_min) {
				_log("Daemon: $name - Exceeded max run time - exit");
				break;
			}
		}

		_log("Daemon: $name - remove lock file");
		@rmdir($lock_file);

	}

	static function processArgs() {
		global $argv;
		$name = static::$name;
		if(!isset($argv[1])) {
			_log("Daemon: $name - no args to process");
		} else {
			$cmd = $argv[1];
			_log("Daemon: $name - process command $cmd");
			if($cmd == "status") {
				$lock_file = ROOT."/tmp/$name-lock";
				$locked = file_exists($lock_file);

				$scmd = "ps uax | grep $name.php | grep -v grep";
				$res = shell_exec($scmd);
				$data = [];
				if($res) {
					$arr = preg_split("/\s+/", $res);
					$pid = $arr[1];
					if($pid != getmypid()) {
						$started = $arr[8];
						$cpu = $arr[2];
						$memory = $arr[3];
						$data['running']=true;
						$data['started']=$started;
						$data['pid']=$pid;
						$data['cpu']=$cpu;
						$data['memory']=$memory;
						$data['owner']=$arr[0];
					} else {
						$data['running']=false;
					}
				} else {
					$data['running']=false;
				}
				$data['locked']=$locked;
				if($locked) {
					$data['locked_time'] = filemtime($lock_file);
					$data['lock_file'] = $lock_file;
					$data['lock_owner'] = posix_getpwuid(fileowner($lock_file))["name"];
				}

				echo json_encode($data) . PHP_EOL;
				exit;
			} else if ($cmd == "stop") {
				$lock_file = ROOT."/tmp/$name-lock";
				$res = @rmdir($lock_file);
				if(!$res) {
					echo "Error: can not remove lock file : $lock_file";
				}
				exit;
			} else if ($cmd == "kill") {
				$cmd = "ps uax | grep $name.php | grep -v grep";
				$res = shell_exec($cmd);
				$arr = preg_split("/\s+/", $res);
				$pid = $arr[1];
				if($pid != getmypid()) {
					$scmd = "kill $pid";
					shell_exec($scmd);
				}
				exit;
			} else {
				echo "Unknown command $cmd".PHP_EOL;
				exit;
			}
		}
	}


}
