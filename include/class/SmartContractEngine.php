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

			$sc_exec_file = SmartContractEngine::buildRunCode($sc_address,"exec");

			SmartContractEngine::buildState($sc_address);

			if(!empty($params) && !is_array($params)) {
				$params = [$params];
			}

			$params = json_encode($params);
			$params = base64_encode($params);

			$tx_sender = "";
			$tx_value = 0;
			if(!empty($transaction)) {
				$tx_sender = $transaction->src;
				$tx_value = $transaction->val;
			}

			$cmd = "$sc_exec_file $method $params $tx_sender $tx_value";

			$output = self::isolateCmd($cmd);

			$out = json_decode($output, true);
			if(empty($out)) {
				throw new Exception("Error executing smart contract $sc_address method $method: $output");
			}

			$state = $out['state'];

			SmartContractEngine::storeState($sc_address, $height, $state);

			return true;

		}, $error);

	}

	static function call($sc_address, $method, $params=[], &$error=null) {
		return try_catch(function () use ($sc_address, $method, $params) {

			$sc_exec_file = SmartContractEngine::buildRunCode($sc_address,"call");

			SmartContractEngine::buildState($sc_address);

			if(!empty($params) && !is_array($params)) {
				$params = [$params];
			}

			$params = json_encode($params);
			$params = base64_encode($params);

			$cmd = "$sc_exec_file $method $params";

			$output = self::isolateCmd($cmd);

			$out = json_decode($output, true);
			if(empty($out)) {
				throw new Exception("Error executing smart contract $sc_address method $method: $output");
			}

			$response = $out['response'];
			return $response;

		}, $error);
	}

	static function isolateCmd($cmd) {
		$exec_cmd = "php -d disable_functions=exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source,ini_set ";
		$exec_cmd.= " -d memory_limit=".SC_MEMORY_LIMIT;
		$exec_cmd.= " -d open_basedir=".self::getRunFolder();
		$exec_cmd.= " -f $cmd 2>&1";
		$output = shell_exec ($exec_cmd);
		$lines2 = [];
		if(strpos($output, 'PHP Startup:')) {
			$lines = explode(PHP_EOL, $output);
			foreach($lines as $line) {
				if(!strpos($line, 'PHP Startup:')) {
					$lines2[]=$line;
				}
			}
			$output = implode(PHP_EOL, $lines2);
		}
		$output = trim($output);
		return $output;
	}

	static function buildRunCode($sc_address, $type = '', $test=false) {

		$smartContract = SmartContractEngine::verifySmartContract($sc_address, $test);
		$code = $smartContract['code'];
		$code = base64_decode($code);

		$sc_dir = self::getRunFolder();
		if(!file_exists($sc_dir)) {
			@mkdir($sc_dir);
			@chmod($sc_dir, 0777);
		}

		$phar_file = $sc_dir . "/$sc_address.phar";
		file_put_contents($phar_file, $code);
		@chmod($phar_file, 0770);
		
		copy(ROOT . "/include/class/SmartContractWrapper.php", self::getRunFolder() . "/SmartContractWrapper.php");

		$run_code = "<?php

		set_time_limit(".SC_MAX_EXEC_TIME.");
		
		error_reporting(E_ALL^E_NOTICE);
		define(\"ROOT\", dirname(__DIR__));
		define(\"SC_ADDRESS\", \"$sc_address\");
		
		require_once 'SmartContractWrapper.php';
		require_once \"$phar_file\";
		
		ob_start();

		\$smartContract = new SmartContract();
		\$smartContractWrapper = new SmartContractWrapper();
		\$smartContractWrapper->run(\$smartContract,'$type');
		
		";

		$sc_run_file = $sc_dir. "/{$sc_address}_run_{$type}.php";
		file_put_contents($sc_run_file, $run_code);

		return $sc_run_file;
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

			if(!file_exists(self::getRunFolder() . "/SmartContractWrapper.php")) {
				copy(ROOT . "/include/class/SmartContractWrapper.php", self::getRunFolder() . "/SmartContractWrapper.php");
			}

			$sc_test_file = self::getRunFolder() . "/{$name}_verify.php";
			$sc_test_content="<?php

			set_time_limit(".SC_MAX_EXEC_TIME.");

			require_once 'SmartContractWrapper.php';

			ob_start();
			require_once '$phar_file';
			\$smartContract = new SmartContract();
			\$smartContractWrapper = new SmartContractWrapper();
			echo \$smartContractWrapper->verify(\$smartContract);
			";

			file_put_contents($sc_test_file, $sc_test_content);

			$cmd = "$sc_test_file";
			$output = self::isolateCmd($cmd);

			if($output != "OK") {
				throw new Exception("Smart contract verify failed: $output");
			}

			unlink($sc_test_file);

			return true;

		}, $error);
	}

	static function getInterface($sc_address, &$error = null) {
		return try_catch(function () use ($sc_address) {
			$sc_exec_file = SmartContractEngine::buildRunCode($sc_address,"interface");
			$cmd = "$sc_exec_file";
			$output = self::isolateCmd($cmd);
			if(!self::$virtual) {
//				unlink($sc_exec_file);
			}
			return $output;
		}, $error);
	}


	static function deploy($transaction, $height, &$error = null, $test = false)
	{
		$sc_address = $transaction->dst;
		return try_catch(function () use ($sc_address, $height, $test, $transaction) {

			$sc_exec_file = SmartContractEngine::buildRunCode($sc_address, "deploy", $test);

			$state_file = self::getRunFolder() . "/{$sc_address}_state.json";
			@unlink($state_file);

			$tx_sender = "";
			$tx_value = 0;
			if(!empty($transaction)) {
				$tx_sender = $transaction->src;
				$tx_value = $transaction->val;
			}

			$cmd = "$sc_exec_file $tx_sender $tx_value";
			$output = self::isolateCmd($cmd);
			$out = json_decode($output, true);
			if(empty($out)) {
				throw new Exception("Error deploying smart contract $sc_address: $output");
			}
			$state = $out['state'];
			SmartContractEngine::storeState($sc_address, $height, $state);
			if(!self::$virtual) {
				unlink($sc_exec_file);
			}
			return $output;
		}, $error);


	}

}
