<?php

class SmartContractEngine
{

	public static $virtual = false;
	public static $debug_logs = [];
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

            $code = $smartContract['code'];
            $data = json_decode(base64_decode($code),true);
            $interface = $data['interface'];

            $found=false;
            foreach ($interface['properties'] as $item) {
                if($item['name']==$property) {
                    $found = $item;
                    break;
                }
            }

            if(!$found) {
                throw new Exception("Property $property not found on smart contract");
            }

			$val = SmartContractEngine::loadVarState($sc_address, $property, $key, isset($found['type']) && $found['type']=="map");
            return $val;
		}, $error);
	}


    static function process($sc_address, $transactions, $height, $test, &$error=null, &$state_updates=null) {
        return try_catch(function () use ($sc_address, $transactions, $height, $test, &$state_updates) {

            $smartContract = SmartContract::getById($sc_address, self::$virtual);
            $code =  null;
            if(!$smartContract) {
                if(count($transactions)>1) {
                    throw new Exception("Not allowed multiple smart contract create transactions");
                }
                foreach ($transactions as $tx) {
                    $code = $tx->data;
                }
            } else {
                $smartContract = SmartContractEngine::verifySmartContract($sc_address, $test);
                $code = $smartContract['code'];
            }

            $data = json_decode(base64_decode($code), true);
            $code = base64_decode($data['code']);
            if(empty($code)) {
                throw new Exception("Invalid code for smart contract process");
            }

            $sc_exec_file = SmartContractEngine::buildRunCode($sc_address, $code);
            $cmd_args = [
                'type'=>'process',
                'transactions' => $transactions,
                'height'=>$height,
                "test"=>$test,
                "virtual"=>self::$virtual,
			];

			$cmd_args = base64_encode(json_encode($cmd_args));

			$cmd = "$sc_exec_file $cmd_args";

            $res = self::isolateCmd($cmd);
            $data = self::processOutput($res);
            $state_updates = $data['state_updates'];

            if(self::$virtual) {
                self::$debug_logs = $data['debug_logs'];
            }

            return $data['hash'];

		}, $error);

	}

	static function view($sc_address, $method, $params=[], &$error=null) {
		return try_catch(function () use ($sc_address, $method, $params) {

			$sc_exec_file = SmartContractEngine::buildRunCode($sc_address);

			if(!empty($params) && !is_array($params)) {
				$params = [$params];
			}

            $height = Block::getHeight();

			$cmd_args = [
				'type'=>'view',
				'method' => $method,
				'params' => $params,
                'height'=>$height,
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

	static function isolateCmd($cmd) {
        $debug=false;

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

        $disable_functions=get_sc_disable_functions();

        $debug_str="";
        $env="";
        if($debug) {
            $debug_str="-dxdebug.start_with_request=1";
            $disable_functions = str_replace("getenv,", '', $disable_functions);
            $env="PHP_IDE_CONFIG=serverName=local";
        }

		$exec_cmd = "CONFIG=$config $env php $debug_str -d disable_functions=$disable_functions ";
		$exec_cmd.= " -d memory_limit=".SC_MEMORY_LIMIT." -d max_execution_time=".SC_MAX_EXEC_TIME." -d error_reporting=$error_reporting";
		$exec_cmd.= " -d open_basedir=".self::getRunFolder().":".$allowed_files_list;
		$exec_cmd.= " -f $cmd ";

        $proc = proc_open($exec_cmd,[
            0 => ['pipe','r'],
            1 => ['pipe','w'],
            2 => ['pipe','w'],
        ],$pipes);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($proc);
        $res= [
            "output"=>$stdout,
            "errors"=>$stderr
        ];
        return $res;
	}

	static function buildRunCode($sc_address, $code = null, $test=false) {

        if($code == null) {
            $smartContract = SmartContractEngine::verifySmartContract($sc_address, $test);
            $code = $smartContract['code'];
            $data = json_decode(base64_decode($code), true);
            $code = base64_decode($data['code']);
        }

		return self::buildRunFile($sc_address, $code);
	}

    static function buildRunFile($sc_address, $code) {
        $sc_dir = self::getRunFolder();
        if(!file_exists($sc_dir)) {
            $res = @mkdir($sc_dir);
            if(!$res) {
                throw new Exception("Unalbe to create smart contracts run dir");
            }
        }

        $phar_file = $sc_dir . "/$sc_address.phar";
        if(!file_exists($phar_file) || self::$virtual) {
            $res = file_put_contents($phar_file, $code);
            if(!$res) {
                throw new Exception("Enable to write phar file");
            }
            $res = @chmod($phar_file, 0777);
            if(!$res) {
                throw new Exception("Enable to set permissions to phar file");
            }
        }

        $sc_run_file = $sc_dir. "/{$sc_address}_run.php";
        if(file_exists($sc_run_file)) {
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
        $res = file_put_contents($sc_run_file, $run_code);
        if(!$res) {
            throw new Exception("Enable to write run file");
        }

        return $sc_run_file;
    }

	static function loadVarState($sc_address, $property, $key, $map) {
		global $db;

		if(self::$virtual) {
			$state = [];
			$state_file = self::getRunFolder() . "/{$sc_address}.state.json";
			if(file_exists($state_file)) {
				$state = file_get_contents($state_file);
				$state = json_decode($state, true);
			}

            if($map) {
                if($key == null) {
                    return count($state[$property]);
                } else {
				    return $state[$property][$key];
                }
			} else {
				return $state[$property];
			}

		} else {

            if($map) {
                if($key === null || strlen($key)==0) {
                    $sql="select count(*) as cnt
                        from (select s.variable,
                                     s.var_value,
                                     row_number() over (partition by s.sc_address, s.variable, s.var_key order by s.height desc) as rn
                              from smart_contract_state s
                              where s.sc_address = :address
                                and s.variable = :variable) as ranked
                        where ranked.rn = 1";
                    return $db->single($sql, [":address"=> $sc_address,":variable"=>$property]);
                } else {
                    $sql="select s.var_value
                        from smart_contract_state s
                        where s.sc_address = :address and s.variable =:variable 
                          and s.var_key = :key
                        order by s.height desc limit 1";
                    return $db->single($sql, [":address"=> $sc_address,":variable"=>$property, ":key"=>$key]);
                }
            } else {
                $sql="select s.var_value
                        from smart_contract_state s
                        where s.sc_address = :address and s.variable =:variable
                          and s.var_key is null
                        order by s.height desc limit 1";
                return $db->single($sql, [":address"=> $sc_address,":variable"=>$property]);
            }
		}

	}

	static function verifyCode($code, &$error = null, $address=null) {
		return try_catch(function () use ($error, $code, $address) {

			$code = base64_decode($code);

			$name = empty($address) ? hash("sha256", $code) : $address;

            $sc_verify_file = self::buildRunFile($name, $code);

            $cmd_args = [
                'type'=>'verify'
            ];

            $cmd_args = base64_encode(json_encode($cmd_args));

			$cmd = "$sc_verify_file $cmd_args";
            $res = self::isolateCmd($cmd);
			$interface = self::processOutput($res);

            unlink($sc_verify_file);

			return $interface;

		}, $error);
	}

	static function processOutput($res) {
        $output=$res['output'];
        $errors=$res['errors'];
		$output_decoded = json_decode($output , true);

        if(!is_array($errors)) {
            $errors = [$errors];
        }

		if(!is_array($output_decoded)) {
			throw new Exception("Smart contract failed: $output errors=".@implode(PHP_EOL, $errors));
		} else {
			if($output_decoded['status']=='error') {
				throw new Exception("Smart contract failed: ".$output_decoded['error']. " errors=".@implode(PHP_EOL, $errors) .
                " trace=".$output_decoded['trace']);
			}
		}
		return $output_decoded['data'];
	}

	static function getInterface($sc_address, &$error = null) {

        $smartContract = SmartContract::getById($sc_address, self::$virtual);
        if(!$smartContract) {
            return false;
        }

        $code = $smartContract['code'];
        $data = json_decode(base64_decode($code),true);
        $interface = $data['interface'];
        return $interface;
	}

    public static function getState($sc_address) {
        if(self::$virtual) {
            $state_file = ROOT . '/tmp/sc/'.$sc_address.'.state.json';
            $state = @json_decode(@file_get_contents($state_file), true);
            return $state;
        }
        return SmartContract::getState($sc_address);
    }

    public static function cleanVirtualState($sc_address) {
        if(self::$virtual) {
            $state_file = ROOT . '/tmp/sc/'.$sc_address.'.state.json';
            @unlink($state_file);
        }
    }

    static function parseCmdLineArgs($args) {
        $args = trim($args);
        $arr=str_getcsv($args, " ");

        $arr = @array_map('trim', $arr);
        $arr = @array_map('stripslashes', $arr);
        $arr = @array_filter($arr, function($item) {
            return strlen(trim($item))>0;
        });
        return $arr;
    }

}
