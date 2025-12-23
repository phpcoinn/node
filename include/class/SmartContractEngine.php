<?php

class SmartContractEngine
{

	public static $virtual = false;
	public static $debug_logs = [];
	public static $smartContracts = [];

    public static $debug = true;


	public static function getRunFolder() {
		return ROOT . "/tmp/sc";
	}


	static function verifySmartContract($sc_address, $test = false) {

		if(self::$virtual) {
            if(!self::$smartContracts[$sc_address]) {
                throw new Exception("Not found Smart Contract with address $sc_address");
            }
			return self::$smartContracts[$sc_address];
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
            $code = base64_decode(@$data['code']);
            if(empty($code)) {
                throw new Exception("Invalid code for smart contract process");
            }
            $virtual = SmartContractEngine::$virtual;
            $debug = SmartContractEngine::$debug;

            $phar_path = self::getRunFolder()."/$sc_address.phar";
            if(!file_exists($phar_path) || DEVELOPMENT) {
                $phar_path = self::buildPharFile($sc_address, $code);
            }

//            $sc_exec_file = SmartContractEngine::buildRunCode($sc_address, $code);
            $cmd_args = [
                'type'=>'process',
                'transactions' => $transactions,
                'height'=>$height,
                "test"=>$test,
                "virtual"=>$virtual,
                "dbConfig"=>self::getDbConfig(),
			];

            $result = Sandbox::exec($phar_path, $cmd_args, $sc_address, $virtual, $debug);

            $data = self::processOutput($result);
            $state_updates = $data['state_updates'];

            if(self::$virtual) {
                self::$debug_logs = $data['debug_logs'];
            }

            if(isset($data['logs'])) {
                foreach ($data['logs'] as $log) {
                    _log($log);
                }
            }

            return $data['hash'];

		}, $error);

	}

	static function view($sc_address, $method, $params=[], &$error=null) {
		return try_catch(function () use ($sc_address, $method, $params) {

            $smartContract = SmartContractEngine::verifySmartContract($sc_address);
            $code = $smartContract['code'];
            $data = json_decode(base64_decode($code), true);
            $code = base64_decode(@$data['code']);
            $phar_path = self::getRunFolder()."/$sc_address.phar";
            if(!file_exists($phar_path) || DEVELOPMENT) {
                $phar_path = self::buildPharFile($sc_address, $code);
            }
            $height =  self::$virtual ? 0 : Block::getHeight();
            $debug = SmartContractEngine::$debug;
            $virtual = SmartContractEngine::$virtual;
            $cmd_args = [
                'type'=>'view',
                'method' => $method,
                'params' => $params,
                'height'=>$height,
                "virtual"=>$virtual,
                "dbConfig"=>self::getDbConfig(),
            ];
            $result = Sandbox::exec($phar_path, $cmd_args, $sc_address, $virtual, $debug);
            $data = self::processOutput($result);
            return $data['response'];

		}, $error);
	}

    /**
     * @param $sc_address
     * @param $code string raw phar file code
     * @return string
     * @throws Exception
     */
    static function buildPharFile($sc_address, $code) {
        $sc_dir = self::getRunFolder();
        if(!file_exists($sc_dir)) {
            $res = @mkdir($sc_dir);
            if(!$res) {
                throw new Exception("Unable to create smart contracts run dir");
            }
        }
        $phar_file = $sc_dir . "/$sc_address.phar";
        if(!file_exists($phar_file) || self::$virtual || DEVELOPMENT) {
            $res = file_put_contents($phar_file, $code);
            if(!$res) {
                throw new Exception("Enable to write phar $sc_address file");
            }
            $res = @chmod($phar_file, 0777);
//            if(!$res) {
//                throw new Exception("Enable to set permissions to phar file");
//            }
        }
        return $phar_file;
    }

	static function loadVarState($sc_address, $property, $key, $map) {
		global $db;

		if(self::$virtual) {
			$state = [];
			$state_file = self::getRunFolder() . "/{$sc_address}.json";
			if(file_exists($state_file)) {
				$state = file_get_contents($state_file);
				$state = json_decode($state, true);
			}

            if($map) {
                if($key === null) {
                    return count($state[$property]);
                } else {
				    return $state[$property][$key];
                }
			} else {
				return @$state[$property];
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

    /**
     * @param $code string base64 encoded phar code
     * @param $error
     * @param $address
     * @return false|mixed
     * @throws Exception
     */
	static function verifyCode($code, &$error = null, $address=null) {
		return try_catch(function () use ($error, $code, $address) {

            $code = base64_decode($code);
            $phar_file = SmartContractEngine::buildPharFile($address, $code);
            $interface = Compiler::readInterface($phar_file);
            return $interface;

		}, $error);
	}

	static function processOutput($res) {
        if(isset($res['status']) && $res['status']=='ok') {
            return $res['data'];
        }

        if(isset($res['error']) && $res['error']=='sandbox_error' ) {
            throw new Exception("Smart contract Sandbox error: ".$res['details']);
        }

        throw new Exception("Smart contract failed: ".$res['error'] . ': ' . $res['message'] .
            " trace=".$res['trace']);

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
            return StatePersistence::loadState($sc_address);
        }
        return SmartContract::getState($sc_address);
    }

    public static function cleanVirtualState($sc_address) {
        if(self::$virtual) {
            $state_file = ROOT . '/tmp/sc/'.$sc_address.'.json';
            @unlink($state_file);
        }
    }

    static function parseCmdLineArgs($args) {
        $args = trim($args ?? '');
        $arr=str_getcsv($args, " ");

        $arr = @array_map('trim', $arr);
        $arr = @array_map('stripslashes', $arr);
        $arr = @array_filter($arr, function($item) {
            return strlen(trim($item))>0;
        });
        return $arr;
    }


    private static function getDbConfig()
    {
        global $_config;
        return[
            'db_connect' => $_config['db_connect'],
            'db_user' => $_config['db_user'],
            'db_pass' => $_config['db_pass'],
        ];
    }

}
