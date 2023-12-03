<?php

class SmartContractEngine
{

	public static $virtual = false;
	public static $smartContract;


	private static function getRunFolder() {
		return ROOT . "/tmp/sc";
	}


	static function verifySmartContract($sc_address, $test = false) {

		if(self::$virtual) {
            if(self::$smartContract['address']!=$sc_address) {
                throw new Exception("Not found Smart Contract with address $sc_address");
            }
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

	static function get($sc_address, $property, $key=null, &$error=null) {
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

	static function exec($transaction, $method, $height=0, $params=[], &$error=null, $test=false) {
		$sc_address = $transaction->dst;
		return try_catch(function () use ($sc_address, $transaction, $method, $params, $height, $error,$test) {

			$sc_exec_file = SmartContractEngine::buildRunCode($sc_address);

			if(!empty($params) && !is_array($params)) {
				$params = [$params];
			}

			$cmd_args = [
				'type'=>'exec',
				'method' => $method,
				'params' => $params,
				'transaction' => $transaction,
				'height'=>$height,
                "test"=>$test,
                "virtual"=>self::$virtual
			];

			$cmd_args = base64_encode(json_encode($cmd_args));

			$cmd = "$sc_exec_file $cmd_args";

            $res = self::isolateCmd($cmd);
			self::processOutput($res);

			return true;

		}, $error);

	}

	static function view($sc_address, $method, $params=[], &$error=null) {
		return try_catch(function () use ($sc_address, $method, $params) {

			$sc_exec_file = SmartContractEngine::buildRunCode($sc_address);

			if(!empty($params) && !is_array($params)) {
				$params = [$params];
			}

			$cmd_args = [
				'type'=>'view',
				'method' => $method,
				'params' => $params,
                "virtual"=>self::$virtual
			];

			$cmd_args = base64_encode(json_encode($cmd_args));

			$cmd = "$sc_exec_file $cmd_args";

			$res = self::isolateCmd($cmd);
			$out = self::processOutput($res);
			$response = $out['response'];
			return $response;

		}, $error);
	}

	static function send($transaction, $method, $height=0, $params=[], &$error=null, $test=false) {
		$sc_address = $transaction->src;
		return try_catch(function () use ($sc_address, $transaction, $method, $params, $height, $test) {

			$sc_exec_file = SmartContractEngine::buildRunCode($sc_address);

			if(!empty($params) && !is_array($params)) {
				$params = [$params];
			}

			$cmd_args = [
				'type'=>'exec',
				'method' => $method,
				'params' => $params,
				'transaction' => $transaction,
				'height'=>$height,
                "test"=>$test,
                "virtual"=>self::$virtual
			];

			$cmd_args = base64_encode(json_encode($cmd_args));

			$cmd = "$sc_exec_file $cmd_args";

            $res = self::isolateCmd($cmd);
			self::processOutput($res);


			return true;

		}, $error);

	}

	static function isolateCmd($cmd) {
        $debug="-dxdebug.start_with_request=1";
        $debug="";


        $allowed_files = [
            ROOT . "/chain_id",
            ROOT . "/include/sc.inc.php",
            ROOT . "/include/db.inc.php",
            ROOT . "/include/common.functions.php",
            ROOT . "/include/class/sc"
        ];

        if(file_exists(ROOT."/chain_id")) {
            $chain_id = trim(file_get_contents(ROOT."/chain_id"));
            $allowed_files[]=ROOT . "/include/coinspec.".$chain_id.".inc.php";
        }

        $allowed_files_list = implode(":", $allowed_files);

        global $_config;
        $config=[
            "db_connect"=>$_config['db_connect'],
            "db_user"=>$_config['db_user'],
            "db_pass"=>$_config['db_pass'],
        ];

        $config=base64_encode(json_encode($config));
        $error_reporting=E_ALL^E_NOTICE;

		$exec_cmd = "CONFIG=$config php $debug -d disable_functions=exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source,ini_set,getenv,sleep,set_time_limit,error_reporting ";
		$exec_cmd.= " -d memory_limit=".SC_MEMORY_LIMIT." -d max_execution_time=".SC_MAX_EXEC_TIME." -d error_reporting=$error_reporting";
		$exec_cmd.= " -d open_basedir=".self::getRunFolder().":".$allowed_files_list;
		$exec_cmd.= " -f $cmd ";
		$exec_cmd.= " 2>&1";
		$output = shell_exec ($exec_cmd);
		$lines2 = [];
		$lines = explode(PHP_EOL, $output);
        $errors=[];
		foreach($lines as $line) {
			if(strpos($line, 'PHP Startup:')===0) {
                $errors[]=trim($line);
				continue;
			}
			if(strpos($line, 'PHP Warning:')===0) {
                $errors[]=trim($line);
				continue;
			}
			if(strpos($line, 'PHP Fatal error:')===0) {
                $errors[]=trim($line);
				continue;
			}
			$lines2[]=$line;
		}
		$output = implode(PHP_EOL, $lines2);
		$output = trim($output);
		return [
            "output"=>$output,
            "errors"=>$errors
        ];
	}

	static function buildRunCode($sc_address, $test=false) {

		$smartContract = SmartContractEngine::verifySmartContract($sc_address, $test);
		$code = $smartContract['code'];
		$data = json_decode(base64_decode($code), true);
		$code = base64_decode($data['code']);

		return self::buildRunFile($sc_address, $code);
	}

    static function buildDeployCode(Transaction  $transaction, $test=false) {

        $sc_address=$transaction->dst;
        $data = json_decode(base64_decode($transaction->data), true);
        $code = base64_decode($data['code']);

        return self::buildRunFile($sc_address, $code);
    }

    static function buildRunFile($sc_address, $code) {
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

        $run_code = "<?php

        if(function_exists('xdebug_disable')) {
            xdebug_disable();
        }
		define(\"SC_ADDRESS\", \"$sc_address\");
		
		require_once dirname(dirname(__DIR__)) . '/include/sc.inc.php';
		require_once \"phar://$phar_file\";
		
		ob_start();

		\$smartContractWrapper = new SmartContractWrapper();
		\$smartContractWrapper->run();
		
		";

        $sc_run_file = $sc_dir. "/{$sc_address}_run.php";
        file_put_contents($sc_run_file, $run_code);

        return $sc_run_file;
    }

	static function loadState($sc_address) {
		global $db;

		if(self::$virtual) {
			$state = [];
			$state_file = self::getRunFolder() . "/{$sc_address}.state.json";
			if(file_exists($state_file)) {
				$state = file_get_contents($state_file);
				$state = json_decode($state, true);
			}
		} else {
            $state = [];
            $sql="select ss.variable, ss.var_key, ss.var_value
            from (select s.sc_address, s.variable, ifnull(s.var_key, 'null') as var_key, max(s.height) as height
                  from smart_contract_state s
                  where s.sc_address = :address
                  group by s.variable, s.var_key) as last_vars
                     join smart_contract_state ss on (ss.sc_address = last_vars.sc_address and ss.variable = last_vars.variable
                and ifnull(ss.var_key, 'null') = last_vars.var_key and ss.height = last_vars.height);
            ";
            $rows = $db->run($sql, [":address"=> $sc_address]);
            foreach ($rows as $row) {
                if($row['var_key']!==null) {
                    $state[$row['variable']][$row['var_key']]=$row['var_value'];
                } else {
                    $state[$row['variable']]=$row['var_value'];
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

			$res = file_put_contents($phar_file, $code);
            if(!$res) {
                throw new Error("Unable to write phar file $phar_file");
            }

			$sc_verify_file = self::getRunFolder() . "/{$name}_verify.php";
			$sc_verify_content="<?php

			require_once dirname(dirname(__DIR__)) . '/include/sc.inc.php';

			ob_start();
			require_once '$phar_file';
			\$smartContractWrapper = new SmartContractWrapper();
			\$smartContractWrapper->verify();
			";

			$res = file_put_contents($sc_verify_file, $sc_verify_content);
            if(!$res) {
                throw new Error("Unable to write verify phar file $sc_verify_file");
            }

			$cmd = "$sc_verify_file";
            $res = self::isolateCmd($cmd);
			self::processOutput($res);

			if(!DEVELOPMENT) {
				unlink($sc_verify_file);
			}

			return true;

		}, $error);
	}

	static function processOutput($res) {
        $output=$res['output'];
        $errors=$res['errors'];
		$output_decoded = json_decode($output , true);

		if(!is_array($output_decoded)) {
			throw new Exception("Smart contract failed: $output errors=".implode(PHP_EOL, $errors));
		} else {
			if($output_decoded['status']=='error') {
				throw new Exception("Smart contract failed: ".$output_decoded['error']. " errors=".implode(PHP_EOL, $errors) .
                " trace=".$output_decoded['trace']);
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
            $res = self::isolateCmd($cmd);
			$out = self::processOutput($res);
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

			$sc_exec_file = SmartContractEngine::buildDeployCode($transaction, $test);

            if(self::$virtual) {
                $state_file = ROOT . '/tmp/sc/'.$sc_address.'.state.json';
                @unlink($state_file);
            }

			$data = $transaction->data;
			$data = json_decode(base64_decode($data), true);

			$cmd_args = [
				'type'=>'deploy',
				'transaction' => $transaction,
				'height'=>$height,
                "test"=>$test,
                "virtual"=>self::$virtual
			];

			if(isset($data['params'])) {
				$cmd_args['params']=$data['params'];
			}

			$cmd_args = base64_encode(json_encode($cmd_args));

			$cmd = "$sc_exec_file $cmd_args";
            $res = self::isolateCmd($cmd);
			self::processOutput($res);
			if(!self::$virtual && !DEVELOPMENT) {
				unlink($sc_exec_file);
			}
			return $res['output'];
		}, $error);


	}

}
