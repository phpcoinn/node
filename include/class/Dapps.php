<?php

class Dapps extends Task
{

	static $name = "dapps";
	static $title = "Dapps";

	static $run_interval = 30;

	static function isLocal($dapps_id) {
		global $_config;
		return self::isEnabled() && Account::getAddress($_config['dapps_public_key'])==$dapps_id;
	}

	static function calcDappsHash($dapps_id) {
		$dapps_dir = self::getDappsDir() . "/" . $dapps_id;
		$appsHash = null;
		if(file_exists($dapps_dir)) {
			$cmd = "cd ".self::getDappsDir()." && tar -cf - $dapps_id --owner=0 --group=0 --sort=name --mode=744 --mtime='2020-01-01 00:00:00 UTC' | sha256sum";
			$res = shell_exec($cmd);
			$arr = explode(" ", $res);
			$appsHash = trim($arr[0]);
//			_log("Executing calcAppsHash appsHash=$appsHash", 5);
		}
		return $appsHash;
	}

	static function buildDappsArchive($dapps_id) {
        $res=Nodeutil::psAux("tar -czf tmp/dapps.tar.gz dapps/$dapps_id", 1);
		_log("Dapps: check buildDappsArchive res=$res", 5);
		if($res !== null) {
			_log("Dapps: buildDappsArchive running", 5);
			return false;
		} else {
			$cmd = "cd ".ROOT." && rm tmp/dapps.tar.gz";
			_log("Dapps: Delete old archive $cmd", 5);
			@shell_exec($cmd);
			$cmd = "cd ".ROOT." && tar -czf tmp/dapps.tar.gz dapps/$dapps_id --owner=0 --group=0 --sort=name --mode=744 --mtime='2020-01-01 00:00:00 UTC'";
			_log("Dapps: buildDappsArchive call process $cmd", 5);
			shell_exec($cmd);
			if (php_sapi_name() == 'cli') {
				$cmd = "cd ".ROOT." && chmod 777 tmp/dapps.tar.gz";
				_log("Dapps: cli set chmod $cmd", 5);
				@shell_exec($cmd);
			}
			return true;
		}
	}

	static function createDir() {
		$dapps_root_dir = self::getDappsDir();
		if(!file_exists($dapps_root_dir)) {
			@mkdir($dapps_root_dir);
			@chown($dapps_root_dir, "www-data");
			@chgrp($dapps_root_dir, "www-data");
		}
	}

	static function process($force = false) {
		global $_config, $db;
		_log("Dapps: start process" , 5);
		$dapps_public_key = $_config['dapps_public_key'];
		$dapps_id = Account::getAddress($dapps_public_key);
		$dapps_root_dir = self::getDappsDir();
		if(!file_exists($dapps_root_dir)) {
			_log("Dapps: dapps root folder $dapps_root_dir does not exists");
			if (php_sapi_name() == 'cli') {
				_log("Dapps: create root folder $dapps_root_dir and set permissions");
				self::createDir();
			}
			return;
		}

		$dapps_folder = self::getDappsDir() . "/$dapps_id";
		if(!file_exists($dapps_folder)) {
			_log("Dapps: dapps folder $dapps_folder does not exists");
			if (php_sapi_name() == 'cli') {
				@mkdir($dapps_folder, 0777, true);
			}
			return;
		}

		$public_key = Account::publicKey($dapps_id);
		if(!$public_key) {
			_log("Dapps: Dapps $dapps_id - public key not found");
			return;
		}

		$dapps_disable_auto_propagate = isset($_config['dapps_disable_auto_propagate']) && $_config['dapps_disable_auto_propagate'];

		$saved_dapps_hash = $db->getConfig('dapps_hash');
		_log("Dapps: hash from db = $saved_dapps_hash", 5);
		$dapps_hash = self::calcDappsHash($dapps_id);
		$archive_built = file_exists(ROOT  . "/tmp/dapps.tar.gz");
		_log("Dapps: exists archive file = $archive_built", 5);
		if($saved_dapps_hash != $dapps_hash || $force || !$archive_built) {
			Cache::remove("dapps_data");
			$db->setConfig("dapps_hash", $dapps_hash);
			Cache::set("dapps_data", Dapps::getLocalData());
			_log("Dapps: build archive");
			self::buildDappsArchive($dapps_id);
			if(!$dapps_disable_auto_propagate || $force) {
				_log("Dapps: Propagating dapps",5);
				Propagate::dappsLocal();
			} else {
				_log("Dapps: disabled auto propagate", 5);
			}
		} else {
			_log("Dapps: not changed dapps", 5);
		}
		if(!Cache::exists("dapps_data")) {
			_log("Cache dapps_data not exists", 5);
			Cache::set("dapps_data", Dapps::getLocalData());
		} else {
			_log("Cache dapps_data exists", 5);
		}

	}

	static function propagate($id) {
		global $_config, $db;
		_log("Dapps: called propagate for $id", 5);
		$dapps_public_key = $_config['dapps_public_key'];
		if(empty($dapps_public_key)) {
			_log("Dapps: not configured");
			return;
		}
		$dapps_private_key = $_config['dapps_private_key'];
		$dapps_id = Account::getAddress($dapps_public_key);
		$dapps_hash = self::calcDappsHash($dapps_id);
		if($id === "local") {
			//start propagate to each peer
			$peers = Peer::getAll();
			if(count($peers)==0) {
				_log("Dapps: No peers to propagate", 5);
			} else {
				_log("Dapps: Found ".count($peers)." to propagate", 5);
				if(Propagate::PROPAGATE_BY_FORKING) {
					$start = microtime(true);
					$dapps_signature = ec_sign($dapps_hash, $dapps_private_key);
					$data = [
						"dapps_id"=>$dapps_id,
						"dapps_hash"=>$dapps_hash,
						"dapps_signature"=>$dapps_signature,
					];
					$info = Peer::getInfo();
					define("FORKED_PROCESS", getmypid());
                    $i=0;
                    $pipes = [];
					foreach ($peers as $peer) {
                        $i++;
                        $socket = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                        if (!$socket) {
                            continue;
                        }
						$pid = pcntl_fork();
						if ($pid == -1) {
							die('could not fork');
                        } elseif ($pid > 0) {
                            fclose($socket[1]);
                            $pipes[$i] = $socket;
						} else if ($pid == 0) {
                            pcntl_signal(SIGALRM, function($signal) use ($i, $start){
                                if ($signal == SIGALRM) {
                                    _log("PD: exit $i because of timout after ".(microtime(true) - $start));
                                    exit();
                                }
                            });
                            pcntl_alarm(30);
                            register_shutdown_function(function() use ($i,$socket){
                                fclose($socket[1]);
                                posix_kill(getmypid(), SIGKILL);
                            });
                            fclose($socket[0]);
							$hostname = $peer['hostname'];
							$url = $hostname."/peer.php?q=updateDapps";
							$res = peer_post($url, $data, 30, $err, $info, $curl_info);
                            $output = ["hostname"=>$hostname, "connect_time" => $curl_info['connect_time'], "res"=>$res, "err"=>$err];
                            fwrite($socket[1], json_encode($output));
                            exit();
						}
					}
					while (pcntl_waitpid(0, $status) != -1) ;

                    $responded = 0;
                    foreach($pipes as $i => $pipe) {
                        $output = stream_get_contents($pipe[0]);
                        fclose($pipe[0]);
                        $output = json_decode($output, true);
                        $hostname = $output['hostname'];
                        $connect_time = $output['connect_time'];
                        if(!empty($connect_time)) {
                            $responded++;
                        }
                        $res = $output['res'];
                        if($res !== false) {
                            Peer::storeResponseTime($hostname, $connect_time);
                        }
                    }

					_log("Dapps: Total time = ".(microtime(true)-$start)." total=".count($pipes)." responded=".$responded);
					_log("Dapps: process " . getmypid() . " exit");
					exit;
				} else {
					foreach ($peers as $peer) {
						self::propagateToPeer($peer);
					}
				}
			}
		} else {
			//propagate to single peer
			$peer = $id;
			_log("Dapps: propagating dapps to $peer pid=".getmypid(), 5);
			$url = $peer."/peer.php?q=updateDapps";
			$dapps_signature = ec_sign($dapps_hash, $dapps_private_key);
			$data = [
				"dapps_id"=>$dapps_id,
				"dapps_hash"=>$dapps_hash,
				"dapps_signature"=>$dapps_signature,
			];
			$res = peer_post($url, $data, 30, $err);
			_log("Dapps: Propagating to peer: ".$peer." data=".http_build_query($data)." res=".json_encode($res). " err=$err", $err ? 0 : 5);
		}
	}

	private static function propagateToPeer($peer) {
		$hostname = $peer['hostname'];
		Propagate::dappsToPeer($hostname);
	}

	static function render() {

		global $_config, $db;

		require_once ROOT . "/include/dapps.functions.php";
		if(php_sapi_name() === 'cli') {
			return;
		}

		$url = $_GET['url'];
		if(substr($url, 0, 1)=='/') {
			$url = substr($url, 1);
		}
		$arr = explode("/", $url);
		$dapps_id = $arr[0];
		$dapps_dir = Dapps::getDappsDir();
		if(!file_exists($dapps_dir ."/". $dapps_id)) {
			_log("Dapps: Does not exists $dapps_id");
			$res = Dapps::downloadDapps($dapps_id);
			if($res) {
				sleep(5);
				header("location: " . $_SERVER['REQUEST_URI']);
			}
			return;
		}

		_log("Dapps: Start render dapps page $dapps_id", 5);

		$url_info = parse_url($url);
		$file = $url_info['path'];

		$file = $dapps_dir . "/" . $file;

		if(!file_exists($file)) {
			_log("Dapps: File $file not exists");
			if(!Dapps::isLocal($dapps_id)) {
				Dapps::downloadDapps($dapps_id);
			}
			return;
		}

		if(is_dir($file)) {
			_log("Dapps: File $file is dir", 5);
			$files = scandir($file);
			_log("Dapps: Files in dir ".json_encode($files), 5);
			foreach ($files as $dir_file) {
				if($dir_file == "index.html") {
					$file = $file . "/" . $dir_file;
					break;
				}
				if($dir_file == "index.php") {
					$file = $file . "/" . $dir_file;
					break;
				}
			}
		}

		if(!is_file($file)) {
			_log("Dapps: Entry $file does not exists", 5);
			return;
		}

		$file_type = mime_content_type($file);
		$file_info = pathinfo($file);
		$ext = $file_info['extension'];
        if($ext === "css") $file_type = "text/css";
		_log("Dapps: Resolve file $file content-type:" . $file_type." ext=$ext", 5);


		if($file_type != 'text/x-php' && $ext!="php") {
			_log("Dapps: file is not php: render it directly", 5);
			ob_end_clean();
			header("Content-Type: ".$file_type);
			readfile($file);
			exit;
		}


		_log("Dapps: Starting session", 5);
		$tmp_dir = ROOT."/tmp/dapps";
		@mkdir($tmp_dir);
        CommonSessionHandler::setup();
		ob_start();
		$session_id = session_id();
		_log("Dapps: Getting session_id=$session_id", 5);

		$query = @$url_info['query'];
		$server_args = "";
		$_SERVER['PHP_SELF_BASE']=$url_info['path'];

		$request_uri = $_SERVER['REQUEST_URI'];
		if(substr($request_uri, 0, strlen("/dapps/")) == "/dapps/") {
			$_SERVER['REWRITE_URL'] = 1;
			$url = substr($request_uri, strlen("/dapps/" . $dapps_id));
		} else {
			$_SERVER['REWRITE_URL'] = 0;
			$url = substr($request_uri, strlen("/dapps.php?url=" . $dapps_id));
		}
		$_SERVER['DAPPS_URL']=$url;
		$_SERVER['DAPPS_NETWORK']=NETWORK;

		$_SERVER['DAPPS_CHAIN_ID']=CHAIN_ID;
		$_SERVER['DAPPS_FULL_URL']=$_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'].$_SERVER["REQUEST_URI"];
		$_SERVER['DAPPS_HOSTNAME']=$_config['hostname'];
		if(Dapps::isLocal($dapps_id)) {
			$dapps_hash = $db->getConfig('dapps_hash');
		} else {
			$peer = Peer::getDappsIdPeer($dapps_id);
			$dapps_hash = $peer['dappshash'];
		}
		$_SERVER['DAPPS_HASH']=$dapps_hash;

		foreach ($_SERVER as $key=>$val) {
			$server_args.=" $key='$val' ";
		}
		$post_data = base64_encode(json_encode($_POST));
		$session_data = base64_encode(json_encode($_SESSION));

		@parse_str($query, $parsed);
		foreach ($_GET as $key=>$val) {
			$parsed[$key]=$val;
		}
		$get_data = base64_encode(json_encode($parsed));

		$cookie_data = base64_encode(json_encode($_COOKIE));

		$functions_file = ROOT . "/include/dapps.functions.php";

		$allowed_files = [
			ROOT . "/chain_id",
			ROOT . "/include/dapps.functions.php",
			ROOT . "/include/common.functions.php",
			ROOT . "/include/coinspec.inc.php",
			ROOT . "/tmp/sessions",
			ROOT . "/include/class/CommonSessionHandler.php",
		];

		if(file_exists(ROOT."/chain_id")) {
			$chain_id = trim(file_get_contents(ROOT."/chain_id"));
			$allowed_files[]=ROOT . "/include/coinspec.".$chain_id.".inc.php";
		}

		$dapps_local = 0;
		if( Account::getAddress($_config['dapps_public_key'])==$dapps_id) {
			$dapps_local = 1;
			$allowed_files [] = ROOT . "/config/dapps.config.inc.php";
		}

		$allowed_files_list = implode(":", $allowed_files);

        $debug="-dxdebug.start_with_request=1";
        $debug="";

		$cmd = "$server_args GET_DATA=$get_data POST_DATA=$post_data SESSION_ID=$session_id SESSION_DATA=$session_data COOKIE_DATA=$cookie_data" .
			" DAPPS_ID=$dapps_id DAPPS_LOCAL=$dapps_local " .
			" php $debug -d disable_functions=exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source,set_time_limit,ini_set" .
			" -d open_basedir=" . $dapps_dir . "/$dapps_id:".$tmp_dir.":".$allowed_files_list .
			" -d max_execution_time=5 -d memory_limit=128M " .
			" -d auto_prepend_file=$functions_file $file 2>&1";
		_log("Dapps: Executing dapps file cmd=$cmd", 5);

        if(empty($_SESSION)) {
            @session_destroy();
        } else {
            session_write_close();
        }

		$res = exec ($cmd, $output2);
//		_log("Dapps: Parsing output ". json_encode($output2), 5);

		ob_end_clean();
		ob_start();
		header("X-Dapps-Id: $dapps_id");

		$out = implode(PHP_EOL, $output2);
        $out = trim($out);
		_log("Dapps: Parsing output $out", 5);

		header("Access-Control-Allow-Origin: *");


		if(strpos($out, "action:")===0) {
			self::processAction($out, $dapps_id);
		}

		_log("Dapps: Writing out", 5);
		echo $out;
		exit;

	}

	static function processAction($out, $dapps_id) {
		global $_config;
		$str = str_replace("action:", "", $out);
		$actionObj = json_decode($str, true);
		if($actionObj['type']=="redirect") {
			header("location: " . $actionObj['url']);
			exit;
		}
		if($actionObj['type']=="dapps_request") {
			$dapps_id = $actionObj['dapps_id'];
			$remote = $actionObj['remote'];
			$url = $actionObj['url'];
			if(substr($url, 0, 1) != "/") {
				$url = "/" . $url;
			}
			$host= "";
			if($remote) {
				$peer = Peer::findByDappsId($dapps_id);
				$host = $peer['hostname'];
			}
			$url = $host."/dapps.php?url=" . $dapps_id . $url;
			header("location: $url");
			exit;
		}
		if($actionObj['type']=="dapps_exec" && self::isLocal($dapps_id)) {
			$code = $actionObj['code'];
			eval($code);
			exit;
		}
		if($actionObj['type']=="dapps_exec_fn" && self::isLocal($dapps_id)) {
			$fn_name = $actionObj['fn_name'];
			$params = $actionObj['params'];
			$dapps_fn_file = ROOT . "/include/dapps.local.inc.php";
			if(!file_exists($dapps_fn_file)) {
				die("Dapps local functions file not exists");
			}
                require_once $dapps_fn_file;
			if(!function_exists($fn_name)) {
				die("Called function $fn_name not exists");
			}
			call_user_func($fn_name, ...$params);
			exit;
		}
		if($actionObj['type']=="dapps_json_response") {
			header('Content-Type: application/json');
			$data = $actionObj['data'];
			echo json_encode($data);
			exit;
		}
		if($actionObj['type']=="dapps_response") {
			$data = $actionObj['data'];
			$data = base64_decode($data);
			ob_end_clean();
			header('Content-Type: '.$actionObj['content_type']);
			echo $data;
			exit;
		}
        if($actionObj['type']=="dapps_sql") {
            $query = $actionObj['query'];
            $params = $actionObj['params'];
            global $db;
        }
	}

	static function getDappsDir() {
		return ROOT . "/dapps";
	}

	static function updateDapps($data, $ip) {
		global $_config;
		$dapps_hash = $data['dapps_hash'];
		$dapps_id = $data['dapps_id'];
		$dapps_signature = $data['dapps_signature'];
		_log("Dapps: received update dapps dapps_id=$dapps_id dapps_hash=$dapps_hash dapps_signature=$dapps_signature");

		$dapps_root_dir = self::getDappsDir();
		if(!file_exists($dapps_root_dir)) {
			api_err("Dapps: Root dapps folder $dapps_root_dir does not exists",0);
		}

		$public_key = Account::publicKey($dapps_id);
		if(!$public_key) {
			api_err("Dapps: Dapps $dapps_id - public key not found");
		}

		if(!isset($_config['dapps_anonymous']) || !$_config['dapps_anonymous']) {
			Peer::updateDappsId($ip, $dapps_id, $dapps_hash);
		}

		$calc_dapps_hash = Dapps::calcDappsHash($dapps_id);

		if($calc_dapps_hash == $dapps_hash) {
			api_echo("Dapps: No need to update dapps $dapps_id",0);
		}

		_log("Dapps: check signature with public_key = $public_key",5);
		$res = Account::checkSignature($dapps_hash, $dapps_signature, $public_key);

		if(!$res) {
			api_err("Dapps: Dapps node signature not valid",0);
		}

		$peer = Peer::findByIp($ip);
		if(!$peer) {
			api_err("Dapps: Remote peer ip=$ip not found", 0);
		}

		_log("Dapps: Request from ip=$ip peer=".$peer['hostname'], 5);

		$link = $peer['hostname']."/dapps.php?download";
		_log("Dapps: Download dapps from $link");

		$arrContextOptions=array(
			"ssl"=>array(
				"verify_peer"=>!DEVELOPMENT,
				"verify_peer_name"=>!DEVELOPMENT,
			),
		);
		$local_file = ROOT . "/tmp/dapps.$dapps_id.tar.gz";
		$res = file_put_contents($local_file, fopen($link, "r", false,  stream_context_create($arrContextOptions)));
		if($res === false) {
			api_err("Dapps: Error downloading apps from remote server", 0);
		} else {
			$size = filesize($local_file);
			if(!$size) {
				api_err("Dapps: Downloaded empty file from remote server", 0);
			} else {
				_log("Dapps: Downloaded size $size file=$local_file", 5);
				$cmd = "cd ".self::getDappsDir()." && rm -rf $dapps_id";
				shell_exec($cmd);
				$cmd = "cd ".ROOT." && tar -xzf tmp/dapps.$dapps_id.tar.gz -C . --owner=0 --group=0 --mode=744 --mtime='2020-01-01 00:00:00 UTC'";
				shell_exec($cmd);
				$cmd = "cd ".self::getDappsDir()." && find $dapps_id -type f -exec touch {} +";
				shell_exec($cmd);
				$cmd = "cd ".self::getDappsDir()." && find $dapps_id -type d -exec touch {} +";
				shell_exec($cmd);
				if (php_sapi_name() == 'cli') {
					$cmd = "cd ".self::getDappsDir()." && chown -R www-data:www-data $dapps_id";
					shell_exec($cmd);
				}
				$new_dapps_hash = Dapps::calcDappsHash($dapps_id);
				_log("Dapps: new_dapps_hash=$new_dapps_hash", 5);
				if($new_dapps_hash != $dapps_hash) {
					api_err("Dapps: Error updating dapps $dapps_id new_dapps_hash=$new_dapps_hash dapps_hash=$dapps_hash", 0);
				} else {
					api_echo("Dapps: OK");
				}
			}
		}

	}

	public static function download()
	{
		_log("Dapps: called download");

		$file = ROOT . "/tmp/dapps.tar.gz";
		if(!file_exists($file)) {
			_log("Dapps: File $file not exists");
			header("HTTP/1.0 404 Not Found");
			exit;
		}

		header('Content-Type: application/octet-stream');
		header("Content-Transfer-Encoding: Binary");
		header("Content-disposition: attachment; filename=\"" . basename($file) . "\"");
		readfile($file);
		exit;
	}

	public static function downloadDapps($dapps_id)
	{
		_log("Dapps: downloadDapps dapps_id=$dapps_id", 5);

		if(empty($dapps_id)) {
			_log("Dapps: downloadDapps dapps from all peers", 5);
			$dappsPeers = Peer::getDappsPeers();
			_log("Found total ".count($dappsPeers)." peers", 5);
			foreach ($dappsPeers as $dappsPeer) {
				self::downloadDapps($dappsPeer['dapps_id']);
			}
		} else {
			if(!Account::valid($dapps_id)) {
				_log("Dapps: downloadDapps dapps_id=$dapps_id NOT VALID");
				return false;
			}
			$peer = Peer::getDappsIdPeer($dapps_id);
			_log("Dapps: downloadDapps found_peer=".json_encode($peer), 5);
			$found = false;
			if($peer) {
				$peers = [$peer];
				$found = true;
			} else {
				$peers = Peer::getPeersForSync();
			}
			if(count($peers)==0) {
				_log("Dapps: No peers to update dapps $dapps_id");
			} else {
				_log("Dapps: Found ".count($peers)." to ask for update dapps $dapps_id", 5);
				foreach ($peers as $peer) {
					Propagate::dappsUpdateToPeer($peer['hostname'], $dapps_id);
				}
			}
			return $found;
		}


	}

	public static function propagateDappsUpdate($hash, $id)
	{
		$hostname = decodeHostname($hash);
		_log("Dapps: called propagate update apps id=$id to host=$hostname");
		$url = $hostname."/peer.php?q=checkDapps";
		$res = peer_post($url, ["dapps_id"=>$id], 30, $err);
		_log("Dapps: response $res err=$err");
	}

	static function checkDapps($dapps_id, $ip) {
		global $_config;
		_log("Dapps: received request to check dapps $dapps_id from peer $ip");
		if(!self::isEnabled()) {
			api_err("Dapps: this server is not hosting dapps");
		}
		$dapps_public_key = $_config['dapps_public_key'];
		$local_dapps_id = Account::getAddress($dapps_public_key);
		if($local_dapps_id != $dapps_id) {
			api_err("Dapps: this server is not host for dapps id = $dapps_id");
		}
		$peer = Peer::findByIp($ip);
		if(!$peer) {
			api_err("Dapps: can not find peer with ip=$ip");
		} else {
			_log("Dapps: propagate dapps to ".$peer['hostname']);
		}
		self::propagateToPeer($peer);
		api_echo("OK");
	}

	public static function getLink($dapps_id)
	{
		return "/dapps.php?url=".$dapps_id;
	}

	public static function getLocalData()
	{
		global $_config;
		$dapps_id = null;
		$dapps_hash = null;
		if(self::isEnabled()) {
			if(isset($_config['dapps_public_key'])) {
				$dapps_id = Account::getAddress($_config['dapps_public_key']);
				if(!empty($dapps_id)) {
					$dapps_hash = Dapps::calcDappsHash($dapps_id);
				}
			}
		}
		return [
			"dapps_id"=>$dapps_id,
			"dapps_hash"=>$dapps_hash,
		];
	}

}
