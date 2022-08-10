<?php

class SmartContractEngine
{

	public static $virtual = false;
	public static $smartContract;


	private static function getRunFolder() {
		return ROOT . "/tmp/sc";
	}

	static function storeState($sc_address, $height, $state) {

		if(self::$virtual) {
			$state_file = self::getRunFolder() . "/{$sc_address}_state.json";
			file_put_contents($state_file, json_encode($state));
		} else {
			foreach ($state as $name => $value) {
				if(is_array($value)) {
					foreach ($value as $var_key => $var_value) {
						self::insertSmartConractStateRow($sc_address, $name, $var_value, $var_key, $height);
					}
				} else {
					self::insertSmartConractStateRow($sc_address, $name, $value, null, $height);
				}
			}
		}


	}

	private static function insertSmartConractStateRow($sc_address,$name,$value,$key,$height) {
		global $db;

		if(strlen($value) > 1000) {
			throw new Exception("Storing value for variable $name key $key exceeds 1000 characters");
		}

		$sql="replace into smart_contract_state (sc_address, variable, var_key, var_value, height)
					values (:sc_address, :variable, :var_key, :var_value, :height)";
		$bind = [
			":sc_address"=>$sc_address,
			":variable"=>$name,
			":var_key"=>$key,
			":var_value"=>"$value",
			":height"=>$height
		];
		$res = $db->run($sql, $bind);
		if($res === false) {
			throw new Exception("Error storing variable $name key=$key for Smart Contract $sc_address: ".$db->errorInfo()[2]);
		}
	}

	static function verifySmartContract($sc_address, $test = false) {

		if(self::$virtual) {
			return self::$smartContract;
		}

		$smartContract = SmartContract::getById($sc_address);
		if(!$smartContract) {
			throw new Exception("Not found Smart Contract with address $sc_address");
		}

		if(!$test) {

			$signature = $smartContract['signature'];

			$scCreateTx = Transaction::getSmartContractCreateTransaction($sc_address);
			if (!$scCreateTx) {
				throw new Exception("Can not found transaction for Smart Contract $sc_address");
			}

			$sc_public_key = $scCreateTx['public_key'];
			if (empty($sc_public_key)) {
				throw new Exception("Empty public key for Smart Contract $sc_address");
			}

			$res = ec_verify($smartContract['code'], $signature, $sc_public_key);
			if (!$res) {
				throw new Exception("Signature not valid for Smart Contract $sc_address");
			}
		}

		return $smartContract;
	}

	static function SCGet($sc_address, $property, $key=null, &$error=null) {
		return try_catch(function () use ($sc_address, $property, $key, &$error){

			$smartContract = SmartContractEngine::verifySmartContract($sc_address);

			$state = SmartContractEngine::loadState($sc_address);
			if(strlen($key)==0) {
				if(!isset($state[$property])) {
					throw new Exception("Property $property not found on smart contract");
				}
				if(is_array($state[$property])) {
					return count($state[$property]);
				}
				return $state[$property];
			} else {
				if(!isset($state[$property][$key])) {
					throw new Exception("Property $property with key $key not found on smart contract");
				}
				return $state[$property][$key];
			}
		}, $error);
	}

	static function exec($transaction, $method, $height=0, $params=[], &$error=null) {
		$sc_address = $transaction->dst;
		return try_catch(function () use ($sc_address, $transaction, $method, $params, $height, $error) {

			$sc_exec_file = SmartContractEngine::buildRunCode($sc_address);

			SmartContractEngine::buildState($sc_address);

			if(!empty($params) && !is_array($params)) {
				$params = [$params];
			}

			$cmd_args = [
				'type'=>'exec',
				'method' => $method,
				'params' => $params,
				'transaction' => $transaction,
				'height'=>$height
			];

			$cmd_args = base64_encode(json_encode($cmd_args));

			$cmd = "$sc_exec_file $cmd_args";

			$output = self::isolateCmd($cmd);

			$out = self::processOutput($output);
			$state = $out['state'];

			SmartContractEngine::storeState($sc_address, $height, $state);

			return true;

		}, $error);

	}

	static function call($sc_address, $method, $params=[], &$error=null) {
		return try_catch(function () use ($sc_address, $method, $params) {

			$sc_exec_file = SmartContractEngine::buildRunCode($sc_address);

			SmartContractEngine::buildState($sc_address);

			if(!empty($params) && !is_array($params)) {
				$params = [$params];
			}

			$cmd_args = [
				'type'=>'call',
				'method' => $method,
				'params' => $params,
			];

			$cmd_args = base64_encode(json_encode($cmd_args));

			$cmd = "$sc_exec_file $cmd_args";

			$output = self::isolateCmd($cmd);
			$out = self::processOutput($output);
			$response = $out['response'];
			return $response;

		}, $error);
	}

	static function send($transaction, $method, $height=0, $params=[], &$error=null) {
		$sc_address = $transaction->src;
		return try_catch(function () use ($sc_address, $transaction, $method, $params, $height, $error) {

			$sc_exec_file = SmartContractEngine::buildRunCode($sc_address);

			SmartContractEngine::buildState($sc_address, $height);

			if(!empty($params) && !is_array($params)) {
				$params = [$params];
			}

			$cmd_args = [
				'type'=>'exec',
				'method' => $method,
				'params' => $params,
				'transaction' => $transaction,
				'height'=>$height
			];

			$cmd_args = base64_encode(json_encode($cmd_args));

			$cmd = "$sc_exec_file $cmd_args";

			$output = self::isolateCmd($cmd);

			$out = self::processOutput($output);

			$state = $out['state'];

			SmartContractEngine::storeState($sc_address, $height, $state);

			return true;

		}, $error);

	}

	static function isolateCmd($cmd) {
		$exec_cmd = "php ".XDEBUG_CLI." -d disable_functions=exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source,ini_set ";
		$exec_cmd.= " -d memory_limit=".SC_MEMORY_LIMIT;
		$exec_cmd.= " -d open_basedir=".self::getRunFolder();
		$exec_cmd.= " -f $cmd ";
		$exec_cmd.= " 2>&1";
		$output = shell_exec ($exec_cmd);
		$lines2 = [];
		$lines = explode(PHP_EOL, $output);
		foreach($lines as $line) {
			if(strpos($line, 'PHP Startup:')===0) {
				continue;
			}
			if(strpos($line, 'PHP Warning:')===0) {
				continue;
			}
			$lines2[]=$line;
		}
		$output = implode(PHP_EOL, $lines2);
		$output = trim($output);
		return $output;
	}

	static function buildRunCode($sc_address, $test=false) {

		$smartContract = SmartContractEngine::verifySmartContract($sc_address, $test);
		$code = $smartContract['code'];
		$data = json_decode(base64_decode($code), true);
		$code = base64_decode($data['code']);

		$sc_dir = self::getRunFolder();
		if(!file_exists($sc_dir)) {
			@mkdir($sc_dir);
		}

		$phar_file = $sc_dir . "/$sc_address.phar";
		if(!file_exists($phar_file) || DEVELOPMENT) {
			file_put_contents($phar_file, $code);
			@chmod($phar_file, 0777);
		}

		$sc_run_file = $sc_dir. "/{$sc_address}_run.php";
		if(file_exists($sc_run_file) && !DEVELOPMENT) {
			return $sc_run_file;
		}

		self::copyRequiredFiles(
			[
				"/include/class/SmartContractWrapper.php" => "/SmartContractWrapper.php",
				"/include/class/SmartContractBase.php" => "/SmartContractBase.php",
				"/include/common.functions.php" => "/common.functions.php"
			]
		);

		$run_code = "<?php

		set_time_limit(".SC_MAX_EXEC_TIME.");
		
		error_reporting(E_ALL^E_NOTICE);
		define(\"ROOT\", __DIR__);
		define(\"SC_ADDRESS\", \"$sc_address\");
		
		require_once 'common.functions.php';
		require_once 'SmartContractWrapper.php';
		require_once \"phar://$phar_file\";
		
		ob_start();

		\$smartContractWrapper = new SmartContractWrapper();
		\$smartContractWrapper->run();
		
		";

		$sc_run_file = $sc_dir. "/{$sc_address}_run.php";
		file_put_contents($sc_run_file, $run_code);

		return $sc_run_file;
	}

	static function copyRequiredFiles($files) {
		foreach($files as $src=>$dst) {
			$file_src = ROOT . $src;
			$file_dst = self::getRunFolder() . $dst;
			if(!file_exists($file_dst) ) {
				copy($file_src, $file_dst);
			} else {
				if(md5_file($file_src) != md5_file($file_dst)) {
					copy($file_src, $file_dst);
				}
			}
		}
	}

	static function buildState($sc_address) {
		$state = self::loadState($sc_address);
		$state_file = self::getRunFolder() . "/{$sc_address}_state.json";
		@file_put_contents($state_file, json_encode($state));
	}


	static function loadState($sc_address) {
		global $db;

		if(self::$virtual) {
			$state = [];
			$state_file = self::getRunFolder() . "/{$sc_address}_state.json";
			if(file_exists($state_file)) {
				$state = file_get_contents($state_file);
				$state = json_decode($state, true);
			}
		} else {
			$sql="select max(height) from smart_contract_state where sc_address = :address";
			$height = $db->single($sql, [":address"=> $sc_address]);
			$state = [];
			if($height) {
				$sql = "select * from smart_contract_state where sc_address = :address and height = :height";
				$rows = $db->run($sql, [":address"=>$sc_address, ":height" => $height]);
				foreach ($rows as $row) {
					if($row['var_key']!==null) {
						$state[$row['variable']][$row['var_key']]=$row['var_value'];
					} else {
						$state[$row['variable']]=$row['var_value'];
					}
				}
			}
		}

		return $state;
	}

	static function verifyCode($code, &$error = null, $address=null) {
		return try_catch(function () use ($error, $code, $address) {

			$code = base64_decode($code);

			$name = empty($address) ? hash("sha256", $code) : $address;

			$phar_file = self::getRunFolder() . "/$name.phar";

			file_put_contents($phar_file, $code);

			self::copyRequiredFiles(
				[
					"/include/class/SmartContractWrapper.php" => "/SmartContractWrapper.php",
					"/include/class/SmartContractBase.php" => "/SmartContractBase.php",
					"/include/common.functions.php" => "/common.functions.php"
				]
			);

			$sc_test_file = self::getRunFolder() . "/{$name}_verify.php";
			$sc_test_content="<?php

			set_time_limit(".SC_MAX_EXEC_TIME.");

			require_once 'common.functions.php';
			require_once 'SmartContractWrapper.php';

			ob_start();
			require_once '$phar_file';
			\$smartContractWrapper = new SmartContractWrapper();
			\$smartContractWrapper->verify();
			";

			file_put_contents($sc_test_file, $sc_test_content);

			$cmd = "$sc_test_file";
			$output = self::isolateCmd($cmd);

			self::processOutput($output);

			if(!DEVELOPMENT) {
				unlink($sc_test_file);
			}

			return true;

		}, $error);
	}

	static function processOutput($output) {
		$output_decoded = json_decode($output , true);

		if(!is_array($output_decoded)) {
			throw new Exception("Smart contract failed: $output");
		} else {
			if($output_decoded['status']=='error') {
				throw new Exception("Smart contract failed: ".$output_decoded['error']);
			}
		}
		return $output_decoded['data'];
	}

	static function getInterface($sc_address, &$error = null) {
		return try_catch(function () use ($sc_address) {
			$sc_exec_file = SmartContractEngine::buildRunCode($sc_address);

			$cmd_args = [
				'type'=>'interface',
			];

			$cmd_args = base64_encode(json_encode($cmd_args));

			$cmd = "$sc_exec_file $cmd_args";
			$output = self::isolateCmd($cmd);
			$out = self::processOutput($output);
			if(!self::$virtual) {
//				unlink($sc_exec_file);
			}
			return $out['interface'];
		}, $error);
	}


	static function deploy($transaction, $height, &$error = null, $test = false)
	{
		$sc_address = $transaction->dst;
		return try_catch(function () use ($sc_address, $height, $test, $transaction) {

			$sc_exec_file = SmartContractEngine::buildRunCode($sc_address, $test);

			$state_file = self::getRunFolder() . "/{$sc_address}_state.json";
			@unlink($state_file);

			$data = $transaction->data;
			$data = json_decode(base64_decode($data), true);

			$cmd_args = [
				'type'=>'deploy',
				'transaction' => $transaction,
				'height'=>$height
			];

			if(isset($data['params'])) {
				$cmd_args['params']=$data['params'];
			}

			$cmd_args = base64_encode(json_encode($cmd_args));

			$cmd = "$sc_exec_file $cmd_args";
			$output = self::isolateCmd($cmd);
			$out = self::processOutput($output);
			$state = $out['state'];
			SmartContractEngine::storeState($sc_address, $height, $state);
			if(!self::$virtual) {
				unlink($sc_exec_file);
			}
			return $output;
		}, $error);


	}

}
