<?php

class Dapps extends Task
{

	static $name = "dapps";
	static $title = "Dapps";

	static $run_interval = 30;

	private static function validDappsId($dapps_id) {
		return Security::validDappsId($dapps_id);
	}

	private static function extractDappsArchive($archive, $dapps_id, $extract = true) {
		if(!self::validDappsId($dapps_id)) return false;
		return Security::extractTarSafely($archive, ROOT, 'dapps/'.$dapps_id, $extract);
	}

	static function isLocal($dapps_id) {
		global $_config;
		return self::isEnabled() && Account::getAddress($_config['dapps_public_key'])==$dapps_id;
	}

	static function calcDappsHash($dapps_id) {
		$dapps_dir = self::getDappsDir() . "/" . $dapps_id;
		$dappsHash = null;
		if(file_exists($dapps_dir)) {
			$cmd = "cd ".self::getDappsDir()." && tar -cf - $dapps_id --owner=0 --group=0 --sort=name --mode=744 --mtime='2020-01-01 00:00:00 UTC' | sha256sum";
			$res = shell_exec($cmd);
			$arr = explode(" ", $res);
            $dappsHash = trim($arr[0]);
//			_log("Executing calcDappsHash dappsHash=$dappsHash", 5);
		}
		return $dappsHash;
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

			$url = (string) ($_GET['url'] ?? '');
			if(substr($url, 0, 1)=='/') {
				$url = substr($url, 1);
			}
			$arr = explode("/", $url);
			$dapps_id = $arr[0];
			$dapps_dir = Dapps::getDappsDir();
			if($dapps_id === '' || !preg_match('/\A[A-Za-z0-9_-]+\z/', $dapps_id)) {
				http_response_code(404);
				return;
			}

			$dapps_root = realpath($dapps_dir);
			$dapp_root = realpath($dapps_dir . "/" . $dapps_id);
			if($dapps_root === false) {
				http_response_code(404);
				return;
			}

			if($dapp_root === false) {
				_log("Dapps: Does not exists $dapps_id");
				$res = Dapps::downloadDapps($dapps_id);
			if($res) {
				sleep(5);
				header("location: " . $_SERVER['REQUEST_URI']);
				}
				return;
			}

			// A dapp root must be a real directory directly below the dapps root.
			// This rejects traversal in the dapp id and dapp-directory symlinks.
			if(!is_dir($dapp_root) || dirname($dapp_root) !== $dapps_root) {
				http_response_code(404);
				return;
			}

		_log("Dapps: Start render dapps page $dapps_id", 5);

			$url_info = parse_url($url);
			if($url_info === false || empty($url_info['path'])) {
				http_response_code(404);
				return;
			}

			$file = realpath($dapps_root . "/" . ltrim($url_info['path'], '/'));
			if($file === false) {
				_log("Dapps: Requested file does not exist");
				if(!Dapps::isLocal($dapps_id)) {
					Dapps::downloadDapps($dapps_id);
				}
				http_response_code(404);
				return;
			}
			if($file !== $dapp_root && !str_starts_with($file, $dapp_root . DIRECTORY_SEPARATOR)) {
				_log("Dapps: Requested file is outside dapp root");
				http_response_code(404);
				return;
			}

		if(is_dir($file)) {
			_log("Dapps: File $file is dir", 5);
			$files = scandir($file);
			_log("Dapps: Files in dir ".json_encode($files), 5);
				foreach ($files as $dir_file) {
					if($dir_file == "index.html") {
						$file = realpath($file . "/" . $dir_file);
						break;
					}
					if($dir_file == "index.php") {
						$file = realpath($file . "/" . $dir_file);
						break;
					}
				}
			}

			if($file === false || !is_file($file)
				|| !str_starts_with($file, $dapp_root . DIRECTORY_SEPARATOR)) {
				_log("Dapps: Entry $file does not exists", 5);
				http_response_code(404);
				return;
			}

		$file_type = mime_content_type($file);
		$file_info = pathinfo($file);
		$ext = $file_info['extension'];
        if($ext === "css") $file_type = "text/css";
        if($ext === "js") $file_type = "text/javascript";
		_log("Dapps: Resolve file $file content-type:" . $file_type." ext=$ext", 5);


		if($file_type != 'text/x-php' && $ext!="php") {
			_log("Dapps: file is not php: render it directly", 5);
			ob_end_clean();
			header("Content-Type: ".$file_type);
			readfile($file);
			exit;
		}


			_log("Dapps: Starting session", 5);
			$dapps_session_namespace = 'dapp:'.$dapps_id;
			$tmp_dir = ROOT."/tmp/dapps/".hash('sha256', $dapps_session_namespace);
			@mkdir($tmp_dir, 0700, true);
			session_name('DAPPSESSID_'.substr(hash('sha256', $dapps_id), 0, 16));
	        CommonSessionHandler::setup(null, $dapps_session_namespace);
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

		@parse_str($query, $parsed);
		foreach ($_GET as $key=>$val) {
			$parsed[$key]=$val;
		}
		$get_data = base64_encode(json_encode($parsed));
        $input_data = base64_encode(json_encode(file_get_contents('php://input')));

		$dapp_cookies = $_COOKIE;
		foreach (array_keys($dapp_cookies) as $cookie_name) {
			if ($cookie_name === 'PHPSESSID' || str_starts_with($cookie_name, 'DAPPSESSID_')) {
				unset($dapp_cookies[$cookie_name]);
			}
		}
		$cookie_data = base64_encode(json_encode($dapp_cookies));

		$functions_file = ROOT . "/include/dapps.functions.php";

			$allowed_files = [
				ROOT . "/chain_id",
				ROOT . "/include/dapps.functions.php",
				ROOT . "/include/common.functions.php",
				ROOT . "/include/coinspec.inc.php",
				ROOT . "/include/network_chain_id.inc.php",
				ROOT . "/tmp/sessions/".hash('sha256', 'dapp:'.$dapps_id),
				$tmp_dir,
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

        if(empty($_SESSION)) {
            @session_destroy();
        } else {
            session_write_close();
        }

        $_SERVER['SESSION_ID']=$session_id;
	        $_SERVER['DAPPS_ID']=$dapps_id;
	        $_SERVER['DAPPS_LOCAL']=$dapps_local;
	        $_SERVER['DAPPS_SESSION_NAMESPACE']=$dapps_session_namespace;

		$dapp_server = [];
		$dapp_server_keys = [
			'REMOTE_ADDR', 'REQUEST_SCHEME', 'HTTP_HOST', 'REQUEST_URI', 'REQUEST_METHOD',
			'PHP_SELF_BASE', 'REWRITE_URL', 'DAPPS_URL', 'DAPPS_NETWORK', 'DAPPS_CHAIN_ID',
			'DAPPS_FULL_URL', 'DAPPS_HOSTNAME', 'DAPPS_HASH', 'SESSION_ID', 'DAPPS_ID',
			'DAPPS_LOCAL', 'DAPPS_SESSION_NAMESPACE', 'HTTP_PHPCRAFT_AJAX',
		];
		foreach ($dapp_server_keys as $server_key) {
			if (array_key_exists($server_key, $_SERVER)) $dapp_server[$server_key] = $_SERVER[$server_key];
		}

		$cmdData = [
	            'GET_DATA' => $get_data,
	            'POST_DATA' => $post_data,
	            'INPUT_DATA' => $input_data,
	            'COOKIE_DATA' => $cookie_data,
	            'SERVER' =>$dapp_server,
	        ];

	        $output = Sandbox::runDapp($file, $cmdData, $allowed_files, false,
			function($request) use ($dapps_id) { return self::handleRpc($request, $dapps_id); });

        ob_end_clean();
        ob_start();
        header("X-Dapps-Id: $dapps_id");

        $out = trim($output);
        _log("Dapps: Parsing output $out", 5);

        header("Access-Control-Allow-Origin: *");
        $out = str_replace("PHP Warning:  JIT is incompatible with third party extensions that override zend_execute_ex(). JIT disabled. in Unknown on line 0\n", "", $out);

        if(strpos($out, "action:")===0) {
            self::processAction($out, $dapps_id);
        }

        _log("Dapps: Writing out", 5);
        echo $out;
        exit;

	}

	public static function handleRpc($request, $dapps_id) {
		global $_config;
		if(!is_array($request)) return ['ok'=>false];
		if(($request['type'] ?? '') === 'peers') {
			$hostnames = [];
			foreach(Peer::getAll() as $peer) {
				if(!empty($peer['hostname']) && Security::isSafePeerUrl($peer['hostname'])) {
					$hostnames[] = rtrim((string)$peer['hostname'], '/');
				}
			}
			return empty($hostnames) ? ['ok'=>false, 'error'=>'No peers']
				: ['ok'=>true, 'body'=>base64_encode(implode(PHP_EOL, $hostnames))];
		}
		if(in_array(($request['type'] ?? ''), ['dapp_get', 'dapp_post'], true)) {
			$isPost = ($request['type'] ?? '') === 'dapp_post';
			$path = ltrim((string)($request['path'] ?? ''), '/');
			$query = $request['query'] ?? [];
			$bodyData = (string)($request['body'] ?? '');
			$remote = !empty($request['remote']);
			if(!preg_match('/\A[A-Za-z0-9_\/-]+\.php\z/', $path) || !is_array($query) || count($query) > 50
				|| strlen($bodyData) > 262144) {
				return ['ok'=>false, 'error'=>'Dapp request rejected'];
			}
			foreach($query as $key=>$value) {
				if(!is_string($key) || (!is_scalar($value) && $value !== null)) return ['ok'=>false, 'error'=>'Dapp request rejected'];
			}
			if($isPost && !empty($request['phpcraft_ajax'])) {
				$phpcraftData = json_decode($bodyData, true);
				if(!is_array($phpcraftData) || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', (string)($phpcraftData['api'] ?? ''))
					|| !is_array($phpcraftData['params'] ?? null) || count($phpcraftData['params']) > 32) {
					return ['ok'=>false, 'error'=>'PhpCraft request rejected'];
				}
			}
			$base = rtrim((string)($_config['hostname'] ?? ''), '/');
			if($remote && !self::isLocal($dapps_id)) {
				$peer = Peer::findByDappsId($dapps_id);
				$base = rtrim((string)($peer['hostname'] ?? ''), '/');
			}
			if(!Security::isSafePeerUrl($base)) return ['ok'=>false, 'error'=>'Dapp host rejected'];
			$url = $base.'/dapps.php?'.http_build_query(['url'=>$dapps_id.'/'.$path] + $query);
			$http = ['method'=>$isPost ? 'POST' : 'GET', 'timeout'=>10, 'ignore_errors'=>true,
				'follow_location'=>0, 'max_redirects'=>0, 'header'=>"Accept: application/json\r\nConnection: close\r\n"];
			if($isPost) {
				$http['header'] .= "Content-Type: application/json\r\n";
				if(!empty($request['phpcraft_ajax'])) $http['header'] .= "phpcraft-ajax: 1\r\n";
				$http['content'] = $bodyData;
			}
			$context = stream_context_create(['http'=>$http, 'ssl'=>['verify_peer'=>true, 'verify_peer_name'=>true]]);
			$body = @file_get_contents($url, false, $context, 0, 2 * 1024 * 1024 + 1);
			if($body === false || strlen($body) > 2 * 1024 * 1024) return ['ok'=>false, 'error'=>'Dapp request failed'];
			return ['ok'=>true, 'body'=>base64_encode($body)];
		}
		if(($request['type'] ?? '') === 'sql') {
			$query = trim((string)($request['query'] ?? ''));
			$params = $request['params'] ?? [];
			if(!self::isLocal($dapps_id) || strlen($query) > 65536 || !is_array($params)
				|| count($params) > 100 || !preg_match('/\Aselect\s/iu', $query)
				|| preg_match('/;|--|#|\/\*/', $query)) return ['ok'=>false, 'error'=>'SQL request rejected'];
			global $db;
			return ['ok'=>true, 'data'=>$db->select($query, $params)];
		}
		if(($request['type'] ?? '') === 'exec_fn') {
			if(!self::isLocal($dapps_id)) return ['ok'=>false, 'error'=>'Function request rejected'];
			$fnName = $request['fn_name'] ?? '';
			$params = $request['params'] ?? [];
			$functionsFile = ROOT.'/include/dapps.local.inc.php';
			if(!is_string($fnName) || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $fnName)
				|| !is_array($params) || count($params) > 32 || !is_file($functionsFile)) {
				return ['ok'=>false, 'error'=>'Function request rejected'];
			}
			require_once $functionsFile;
			if(!function_exists($fnName)) return ['ok'=>false, 'error'=>'Function not found'];
			$reflection = new ReflectionFunction($fnName);
			$definedIn = realpath((string)$reflection->getFileName());
			$allowedFile = realpath($functionsFile);
			$dappRoot = realpath(self::getDappsDir().'/'.$dapps_id);
			if($definedIn === false || ($definedIn !== $allowedFile
				&& ($dappRoot === false || !str_starts_with($definedIn, $dappRoot.DIRECTORY_SEPARATOR)))) {
				return ['ok'=>false, 'error'=>'Function source rejected'];
			}
			return ['ok'=>true, 'data'=>$reflection->invokeArgs($params)];
		}
		if(($request['type'] ?? '') !== 'http') return ['ok'=>false];
		$method = strtoupper((string)($request['method'] ?? ''));
		$node = rtrim((string)($request['node'] ?? ''), '/');
		$api = (string)($request['api'] ?? '');
		$allowedNodes = [rtrim((string)($_config['hostname'] ?? ''), '/')];
		foreach(Peer::getAll() as $peer) {
			if(!empty($peer['hostname'])) $allowedNodes[] = rtrim((string)$peer['hostname'], '/');
		}
		if(!in_array($method, ['GET', 'POST'], true) || !Security::isSafePeerUrl($node)
			|| !in_array($node, $allowedNodes, true)
			|| strlen($api) < 1 || strlen($api) > 2048 || preg_match('/[\x00-\x1f\x7f#?]/', $api)) {
			return ['ok'=>false, 'error'=>'RPC request rejected'];
		}
		$url = $node.'/api.php?q='.$api;
		$http = ['method'=>$method, 'timeout'=>5, 'ignore_errors'=>true, 'follow_location'=>0, 'max_redirects'=>0,
			'header'=>"Accept: application/json\r\nConnection: close\r\n"];
		if($method === 'POST') {
			$http['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$http['content'] = http_build_query(['data'=>json_encode($request['data'] ?? null)]);
		}
		$context = stream_context_create(['http'=>$http, 'ssl'=>['verify_peer'=>true, 'verify_peer_name'=>true]]);
		$body = @file_get_contents($url, false, $context, 0, 2 * 1024 * 1024 + 1);
		if($body === false || strlen($body) > 2 * 1024 * 1024) return ['ok'=>false, 'error'=>'RPC request failed'];
		return ['ok'=>true, 'body'=>base64_encode($body)];
	}

	static function processAction($out, $dapps_id) {
		global $_config;
		$str = str_replace("action:", "", $out);
		$actionObj = json_decode($str, true);
		if(!is_array($actionObj) || !isset($actionObj['type']) || !is_string($actionObj['type'])) {
			http_response_code(502);
			return;
		}
		if($actionObj['type']=="redirect") {
			$redirect = str_replace(["\r", "\n"], '', (string)($actionObj['url'] ?? ''));
			if($redirect === '') {
				http_response_code(400);
				return;
			}
			header("location: " . $redirect);
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
		if($actionObj['type']=="dapps_exec") {
			http_response_code(403);
			echo 'Raw dapp code execution is disabled';
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
			if(!is_string($fn_name) || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $fn_name)
				|| !function_exists($fn_name)) {
				die("Called function $fn_name not exists");
			}
			$reflection = new ReflectionFunction($fn_name);
			$definitionFile = $reflection->getFileName();
			$definedIn = is_string($definitionFile) ? realpath($definitionFile) : false;
			$localFunctions = realpath($dapps_fn_file);
			$dappRoot = realpath(self::getDappsDir().'/'.$dapps_id);
			if($definedIn === false || ($definedIn !== $localFunctions
				&& ($dappRoot === false || !str_starts_with($definedIn, $dappRoot.DIRECTORY_SEPARATOR)))) {
				http_response_code(403);
				die('Dapp function is not allowed');
			}
			if(!is_array($params) || count($params) > 32) {
				http_response_code(400);
				die('Invalid dapp function parameters');
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
			$contentType = (string)($actionObj['content_type'] ?? 'application/octet-stream');
			if(!preg_match('#\A[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*(?:;\s*charset=[a-z0-9_-]+)?\z#i', $contentType)) {
				http_response_code(400);
				exit;
			}
			ob_end_clean();
			header('Content-Type: '.$contentType);
			echo $data;
			exit;
		}
	        if($actionObj['type']=="dapps_sql" && self::isLocal($dapps_id)) {
	            $query = trim((string)($actionObj['query'] ?? ''));
	            $params = $actionObj['params'] ?? [];
	            if(strlen($query) > 65536 || !is_array($params) || count($params) > 100
					|| !preg_match('/\Aselect\s/iu', $query)
					|| preg_match('/;|--|#|\/\*/', $query)) {
				http_response_code(403);
				echo json_encode(['error' => 'Dapp SQL query rejected']);
				exit;
			}
	            global $db;
            $rows = $db->select($query, $params);
            echo json_encode($rows);
            exit;
        }
	}

	static function getDappsDir() {
		return ROOT . "/dapps";
	}

	static function updateDapps($data, $ip) {
		global $_config;
		$dapps_hash = $data['dapps_hash'];
		$dapps_id = $data['dapps_id'];
		if(!self::validDappsId($dapps_id)) {
			api_err("Dapps: Invalid dapps id", 0);
		}
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
					if(!self::extractDappsArchive($local_file, $dapps_id, false)) {
						api_err("Dapps: Rejected unsafe archive", 0);
					}
					$cmd = "cd ".escapeshellarg(self::getDappsDir())." && rm -rf ".escapeshellarg($dapps_id);
					shell_exec($cmd);
					if(!self::extractDappsArchive($local_file, $dapps_id)) {
						api_err("Dapps: Rejected unsafe archive", 0);
					}
					$cmd = "cd ".escapeshellarg(self::getDappsDir())." && find ".escapeshellarg($dapps_id)." -type f -exec touch {} +";
					shell_exec($cmd);
					$cmd = "cd ".escapeshellarg(self::getDappsDir())." && find ".escapeshellarg($dapps_id)." -type d -exec touch {} +";
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
			if(!self::validDappsId($dapps_id)) return false;
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
