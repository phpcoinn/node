<?php

class SmartContractWrapper
{

	public $args;
	private $response;
	private $smartContract;
    private SmartContractDB $db;
    private $state;
    private $address;

	public $internal = false;

    private $interface;

	public function __construct($class, $address, $interface = null)
	{
        $this->smartContract = $class;
		if(!$this->smartContract) {
			throw new Exception("Smart contract class not found");
		}
        $this->address = $address;
        $this->interface = $interface;
        SmartContractContext::$address = $address;
	}

	public function store() {
        if(!$this->internal) {
            return $this->outResponse();
        }
	}

	public function run($input, $initial_state) {
        $this->args = $input;
        SmartContractContext::$state = $initial_state;
        $method = $this->args['type'];
        $this->connectDb();
        $this->initSmartContractVars();
		try {
			if ($method == "view") {
                return $this->view_method();
			} elseif ($method == "process") {
				return $this->process();
			}
		} catch (Throwable $e) {
            return $this->error($e, $method);
		}
	}

    //TODO - check
    public function execExt($caller, $methodName, $params) {
        try {
            $this->db=SmartContractContext::$db;
//            $this->log("DB in tx ".$this->db->inTransaction());
            $this->log(" execExt method=$methodName params=".json_encode($params). " caller=" . json_encode($caller));
            $this->log("address=".$caller->address);
            $args = $caller->getExtFields();
            $args['address']=$this->address;
            $args['transaction']['src']=$caller->address;
            $this->log("args=".json_encode($args));
            $this->smartContract->setFields($args);
            $this->initSmartContractVars();
            $this->cleanState($args['height']);
            $this->loadState();
            // Use method name directly (no Reflection)
            $this->invoke($methodName, $params);
            $this->saveState();
        } catch (Throwable $e) {
            $this->error($e, $methodName);
        }
    }

    // TODO -check
    public function callExt($caller, $methodName, $params) {
        try {
            $this->db=SmartContractContext::$db;
//            $this->log("DB in tx ".$this->db->inTransaction());
            $this->log(" execExt method=$methodName params=".json_encode($params). " caller=" . json_encode($caller));
            $this->log("address=".$caller->address);
            $args = $caller->getExtFields();
            $args['address']=$this->address;
            $args['transaction']['src']=$caller->address;
            $this->log("args=".json_encode($args));
            $this->smartContract->setFields($args);
            $this->initSmartContractVars();
            $this->cleanState($args['height']);
            $this->loadState();
            // Use method name directly (no Reflection)
            $this->invoke($methodName, $params);
            return $this->response;
        } catch (Throwable $e) {
            $this->error($e, $methodName);
            return null;
        }
    }

    private function loadState() {
        $interface = $this->interface;
        $this->state = $this->readVarsState();
        
        if ($interface && isset($interface['properties'])) {
            foreach($interface['properties'] as $prop) {
                // Only load @SmartContractVar properties (not maps)
                if($prop['type'] === 'var') {
                    $name = $prop['name'];
                    // Direct property access (no Reflection)
                    $this->smartContract->$name = @$this->state[$name];
                }
            }
        } else {
            // Fallback: if interface not available, skip loading (backward compatibility)
        }
    }

    private function readVarsState() {
        $height=intval($this->args['height']);
        return SmartContractState::readVarsState($height, $this->address);
    }


    private function saveState() {
        $height=intval($this->args['height']);
        $interface = $this->interface;
        
        if ($interface && isset($interface['properties'])) {
            foreach($interface['properties'] as $prop) {
                // Only save @SmartContractVar properties (not maps)
                if($prop['type'] === 'var') {
                    $name = $prop['name'];
                    // Direct property access (no Reflection)
                    $value = $this->smartContract->$name;
                    if((string)@$this->state[$name]!==(string)$value) {
                        $this->setStateVar( $height, $name, $value, null);
                    }
                }
            }
        } else {
            // Fallback: if interface not available, skip saving (backward compatibility)
        }
    }

    private function setStateVar($height, $name, $value, $key=null) {
        SmartContractState::setStateVar($this->address, $height, $name, $value, $key);
    }

    private function connectDb() {
        $virtual=$this->args['virtual'];
        if($virtual) {
            return;
        }
        $dbConfig = $this->args['dbConfig'];
        $this->db = new SmartContractDB($dbConfig);
        SmartContractContext::$db=$this->db;
    }

	public function view_method() {
		$methodName = $this->args['method'];
		$params = $this->args['params'];

        $interface = $this->interface;
		if ($interface && isset($interface['views'])) {
			$isView = false;
			foreach($interface['views'] as $view) {
				if($view['name'] === $methodName) {
					$isView = true;
					break;
				}
			}
			if(!$isView) {
				throw new Exception("Method $methodName is not callable");
			}
		} else {
			// Fallback: if interface not available, assume it's valid (backward compatibility)
		}
		
        $this->beginTransaction();
        $this->loadState();
		$this->invoke($methodName, $params);
        $this->rollBack();
		return $this->outResponse();
	}

    private function beginTransaction() {
        if(!$this->isVirtual()) {
            $this->db->beginTransaction();
        }
    }


    private function commit() {
        if(!$this->isVirtual()) {
            $this->db->commit();
        }
    }

    private function rollBack(){
        if(!$this->isVirtual()) {
            $this->db->rollBack();
        }
    }

	/**
	 * Invoke a contract method by name (without Reflection)
	 * @param string $methodName Method name to invoke
	 * @param array $params Parameters to pass to the method
	 */
	private function invoke($methodName, $params) {
        unset($_SERVER);
		
		try {
			// Execute the contract method using call_user_func_array (no Reflection)
			$contractClass = get_class($this->smartContract);
			
			// Validate method exists
			if (!method_exists($this->smartContract, $methodName)) {
				throw new Exception("Method '$methodName' does not exist in contract class '$contractClass'");
			}
			
			// Set up error handler to catch Reflection usage during contract execution
			$reflectionDetected = false;
			$previousErrorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$reflectionDetected) {
				// Catch Reflection-related errors from contract code
				if (stripos($errstr, 'Reflection') !== false) {
					$reflectionDetected = true;
					throw new Exception("Reflection is forbidden in smart contract code");
				}
				return false; // Let other handlers process it
			});
			
			try {
				// Execute method using call_user_func_array (no Reflection)
				if(empty($params)) {
					$this->response = call_user_func_array([$this->smartContract, $methodName], []);
				} else {
					$this->response = call_user_func_array([$this->smartContract, $methodName], $params);
				}
				
				// Check if Reflection was detected
				if ($reflectionDetected) {
					throw new Exception("Reflection is forbidden in smart contract code. Contract method '$methodName' in class '$contractClass' attempted to use Reflection.");
				}
				
			} finally {
				// Restore previous error handler
				if ($previousErrorHandler !== null) {
					set_error_handler($previousErrorHandler);
				} else {
					restore_error_handler();
				}
			}
			
			// TODO: Re-enable Reflection detection after removing Reflection from wrapper
			/*
			// After execution, check if Reflection was used by examining the call stack
			// Note: This checks the current call stack, which includes frames from the contract execution
			// Note: debug_backtrace() may be disabled in sandbox, so we wrap it
			$backtrace = [];
			try {
				if (function_exists('debug_backtrace')) {
					$backtrace = @debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT, 30);
				}
			} catch (Throwable $e) {
				// debug_backtrace disabled or failed, skip Reflection detection via backtrace
				$backtrace = [];
			}
			
			// Find where contract execution started (after our invoke call)
			$foundInvoke = false;
			foreach ($backtrace as $i => $frame) {
				// Find the invoke frame in SmartContractWrapper
				if (isset($frame['class']) && $frame['class'] === 'SmartContractWrapper' && 
					isset($frame['function']) && $frame['function'] === 'invoke') {
					$foundInvoke = true;
					continue;
				}
				
				// Check frames that occurred during contract execution (after invoke)
				if ($foundInvoke) {
					// Check if Reflection classes are being used
					if (isset($frame['class'])) {
						$className = $frame['class'];
						if (stripos($className, 'Reflection') !== false) {
							// Reflection class found - check if it's from contract code
							// If file is not wrapper file, it's from contract
							if (isset($frame['file']) && $frame['file'] !== $wrapperFile) {
								$reflectionDetected = true;
								break;
							}
						}
					}
					
					// Check for Reflection-related function calls
					if (isset($frame['function'])) {
						$funcName = $frame['function'];
						// Check for Reflection instantiation or method calls
						if (stripos($funcName, 'Reflection') !== false) {
							if (isset($frame['file']) && $frame['file'] !== $wrapperFile) {
								$reflectionDetected = true;
								break;
							}
						}
					}
					
					// Stop checking once we're back in wrapper code (beyond contract execution)
					if (isset($frame['class']) && $frame['class'] === 'SmartContractWrapper') {
						break;
					}
				}
			}
			
			// If Reflection was detected in contract code, throw exception
			if ($reflectionDetected) {
				throw new Exception("Reflection is forbidden in smart contract code. Contract method '$methodName' in class '$contractClass' attempted to use Reflection.");
			}
			*/
			
		} catch (Exception $e) {
			// Re-throw exceptions
			throw $e;
		} catch (Throwable $e) {
			throw $e;
		}
		/*
		} finally {
			// Restore previous error handler
			if ($previousErrorHandler !== null) {
				set_error_handler($previousErrorHandler);
			} else {
				restore_error_handler();
			}
		}
		*/
	}


	/**
	 * Validate parameters against interface definition
	 * @param string $methodName Method name (or 'deploy' for deploy method)
	 * @param array|null $params Parameters array (positional)
	 * @throws Exception If validation fails
	 */
	private function validateParameters($methodName, $params) {
        $interface = $this->interface;
		if (!$interface) {
			// If interface not available, skip validation (backward compatibility)
			return;
		}
		
		// Normalize params - ensure it's an array
		if (!is_array($params)) {
			$params = [];
		}
		
		// Get method definition from interface
		$methodDef = null;
		if ($methodName === 'deploy' && isset($interface['deploy'])) {
			$methodDef = $interface['deploy'];
		} else {
			// Find in methods array
			foreach ($interface['methods'] ?? [] as $m) {
				if ($m['name'] === $methodName) {
					$methodDef = $m;
					break;
				}
			}
		}
		
		if (!$methodDef) {
			// Method not in interface - this shouldn't happen, but skip validation
			return;
		}
		
		$expectedParams = $methodDef['params'] ?? [];
		$paramCount = count($params);
		$expectedCount = count($expectedParams);
		
		// Validate parameter count
		$requiredCount = 0;
		foreach ($expectedParams as $param) {
			// Parameter is required if:
			// 1. 'required' is explicitly true
			// 2. 'required' is not set AND no default value ('value') is set
			if (!empty($param['required']) || (!isset($param['required']) && !isset($param['value']))) {
				$requiredCount++;
			}
		}
		
		if ($paramCount < $requiredCount) {
			throw new Exception("Method $methodName requires at least $requiredCount parameters, but only $paramCount provided");
		}
		
		if ($paramCount > $expectedCount) {
			throw new Exception("Method $methodName accepts at most $expectedCount parameters, but $paramCount provided");
		}
		
		// Validate required parameters are not null
		for ($i = 0; $i < $requiredCount; $i++) {
			if (!array_key_exists($i, $params)) {
				$paramName = $expectedParams[$i]['name'] ?? "parameter $i";
				throw new Exception("Method $methodName requires parameter '$paramName' (position $i), but it is missing");
			}
		}
		
		// Validate parameter types (if specified in interface)
		for ($i = 0; $i < min($paramCount, $expectedCount); $i++) {
			if (!isset($expectedParams[$i]['type'])) {
				continue; // No type specified, skip validation
			}
			
			$expectedType = $expectedParams[$i]['type'];
			$actualValue = $params[$i];
			$paramName = $expectedParams[$i]['name'] ?? "parameter $i";
			
			// Skip type validation if value is null and parameter is optional
			if ($actualValue === null && $i >= $requiredCount) {
				continue;
			}
			
			// Type validation
			if ($expectedType === 'int' || $expectedType === 'integer') {
				// Strict check: must be int, not float or numeric string
				if (!is_int($actualValue)) {
					throw new Exception("Method $methodName parameter '$paramName' (position $i) must be integer, got " . gettype($actualValue));
				}
			} elseif ($expectedType === 'float' || $expectedType === 'double') {
				if (!is_float($actualValue) && !is_int($actualValue) && !is_numeric($actualValue)) {
					throw new Exception("Method $methodName parameter '$paramName' (position $i) must be float, got " . gettype($actualValue));
				}
			} elseif ($expectedType === 'string') {
				if (!is_string($actualValue)) {
					throw new Exception("Method $methodName parameter '$paramName' (position $i) must be string, got " . gettype($actualValue));
				}
			} elseif ($expectedType === 'bool' || $expectedType === 'boolean') {
				if (!is_bool($actualValue) && !is_int($actualValue)) {
					throw new Exception("Method $methodName parameter '$paramName' (position $i) must be boolean, got " . gettype($actualValue));
				}
			} elseif ($expectedType === 'array') {
				if (!is_array($actualValue)) {
					throw new Exception("Method $methodName parameter '$paramName' (position $i) must be array, got " . gettype($actualValue));
				}
			}
			// Note: We don't validate object types as they're complex
		}
	}


    public function process() {
        $transactions = $this->args['transactions'];
        $height=$this->args['height'];
        $this->beginTransaction();
        $res = $this->cleanState($height);
        if($res === false) {
            throw new Exception("Error cleaning SmartContract state");
        }

        $interface = $this->interface;
        
        foreach ($transactions as $transaction) {
            $this->loadState();
            $args['transaction']=$transaction;
            $args['height']=$height;
            $args['address']=$this->address;
            $this->smartContract->setFields($args);
            $msg = $transaction['msg'];
            $type = $transaction['type'];
            if($type == TX_TYPE_SC_CREATE) {
                $data=$transaction['data'];
                $data = json_decode(base64_decode($data), true);
                $deploy_method = $this->get_deploy_method();
                if(!$deploy_method) {
                    throw new Exception("Deploy method not found");
                }
                $this->createSmartContract($transaction, $height);
                $params = $data['params'];
                $this->validateParameters('deploy', $params);
                $this->invoke($deploy_method, $params);
                $this->log("::deploy params=".json_encode($params));
                $this->saveState();
            } else {
            $data = json_decode(base64_decode($msg), true);
            $methodName = $data['method'];
            $params = $data['params'];
            
            // Use interface.json to check if method is a transact method (no Reflection)
            if ($interface && isset($interface['methods'])) {
                $isTransact = false;
                foreach($interface['methods'] as $method) {
                    if($method['name'] === $methodName) {
                        $isTransact = true;
                        break;
                    }
                }
                if(!$isTransact) {
                    throw new Exception("Method $methodName is not executable");
                }
            } else {
                // Fallback: if interface not available, assume it's valid (backward compatibility)
            }
            
            $this->validateParameters($methodName, $params);
            $this->log("::$methodName params=".json_encode($params));
            $this->invoke($methodName, $params);
            $this->saveState();
        }
        }
        $this->endTx();
		return $this->store();
	}

    private function initSmartContractVars() {
        $virtual=$this->args['virtual'];
        SmartContractContext::$virtual = $virtual;
        
        $interface = $this->interface;

        if ($interface && isset($interface['properties'])) {
            foreach($interface['properties'] as $prop) {
                // Only initialize @SmartContractMap properties
                if($prop['type'] === 'map') {
                    $name = $prop['name'];
                    $height=intval(@$this->args['height']);
                    $smartContractMap = new SmartContractMap($this->address, $name, $height);
                    // Direct property access (no Reflection)
                    $this->smartContract->$name = $smartContractMap;
                }
            }
        } else {
            // Fallback: if interface not available, skip initialization (backward compatibility)
        }
    }

	private function outResponse() {
        $sc_state_updates = SmartContractContext::$sc_state_updates;
        $hash = hash("sha256", json_encode($sc_state_updates));
        $this->log("SC:".$this->address." hash=".$hash);
		$out = [
			"response" => $this->response,
            "hash"=>$hash,
            "state_updates"=>$sc_state_updates
		];
        if(SmartContractContext::$virtual) {
            $out["debug_logs"]=SmartContractContext::$debug_logs;
            $out['state']=SmartContractContext::$state;
        }
        $out['logs']=SmartContractContext::$logs;
        return $this->out($out);
	}

	public function get($propertyName) {
		// Direct property access (no Reflection)
		return $this->$propertyName;
	}

    private function get_deploy_method() {
        if (isset($this->interface['deploy']['name'])) {
            return $this->interface['deploy']['name'];
        }
        return false;
    }

	private function getAnnotations($obj) {
		$doc = $obj->getDocComment();
		$doc = trim($doc);
		if(empty($doc)) {
			return false;
		}
		$re = '/@(.+)/m';
		preg_match_all($re, $doc, $annotations);
		$arr =  $annotations[1];
		foreach($arr as &$item) {
			$item = trim($item);
		}
		return $arr;
	}

	private function hasAnnotation($obj, $annotation) {
		$annotations = $this->getAnnotations($obj);
		if(!$annotations) {
			return false;
		}
		return in_array($annotation, $annotations);
	}

    private function isVirtual() {
        $virtual=$this->args['virtual'];
        return $virtual;
    }

    private function cleanState($height) {
        return SmartContractState::cleanState($height, $this->address);
    }

    private function endTx() {
        $test = $this->args['test'];
        if($test) {
            $this->rollBack();
        } else {
            $this->commit();
        }
    }

    private function createSmartContract($transaction, $height) {
        if($this->isVirtual()) {
            return;
        }
        SmartContractState::createSmartContract($transaction, $height);
    }

	public function out($data) {
		return ["status" => "ok", "data" => $data];
	}

	public function error(Throwable $e, $method) {
        $error = "Error running smart contract method=$method: ".$e->getMessage();
        $this->log($error);
		return ["status" => "error", "error" => $error, "trace"=>$e->getTraceAsString()];
	}

    private $logs = [];

	private function log($s) {
        $this->logs[]=$s;
	}

    public function isTransactMethod($method)
    {
        $interface = $this->interface;
        if ($interface && isset($interface['methods'])) {
            foreach($interface['methods'] as $m) {
                if($m['name'] === $method) {
                    return true;
                }
            }
        }
        return false;
    }


}

class SmartContractContext {
    public static $address;
    public static $sc_state_updates = [];
    public static SmartContractDB $db;
    public static $debug_logs = [];
    public static $virtual;
    public static $state;
    public static $logs;
}

class SmartContractDB {

    private $pdo;

    function __construct($dbConfig) {
        $options = [
            PDO::ATTR_PERSISTENT       => false,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
        ];
        $this->pdo = new PDO($dbConfig['db_connect'], $dbConfig['db_user'], $dbConfig['db_pass'], $options);
    }

    function beginTransaction() {
        $this->pdo->beginTransaction();
    }


    function exec($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    function select($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        if ($stmt->execute($params) !== false) {
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $res;
        }
        return false;
    }

    function single($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        if ($stmt->execute($params) !== false) {
            $res =  $stmt->fetchColumn();
            return $res;
        }
        return false;
    }

    function row($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        if ($stmt->execute($params) !== false) {
            $res =  $stmt->fetch(PDO::FETCH_ASSOC);
            return $res;
        }
    }

    function errorInfo() {
        return $this->pdo->errorInfo();
    }

    function rollBack() {
        $this->pdo->rollBack();
    }

    function commit() {
        $this->pdo->commit();
    }

    static function existsStateVar($address, $height, $name, $key=null) {
        $db = SmartContractContext::$db;

        if(strlen($key)==0) {
            $sql="select 1 from smart_contract_state s 
                   where s.sc_address = :sc_address and s.variable = :variable and s.height <=:height order by s.height desc limit 1";
            $res=$db->single($sql, [":sc_address" =>$address, ":variable" => $name, ":height"=>$height]);
        } else {
            $sql="select 1 from smart_contract_state s 
                   where s.sc_address = :sc_address and s.variable = :variable and s.var_key =:var_key and s.height <=:height order by s.height desc limit 1";
            $res=$db->single($sql, [":sc_address" =>$address, ":variable" => $name, ":var_key"=>$key, ":height"=>$height]);
        }
        return $res == 1;
    }

    static function getStateVar($address, $height, $name, $key=null) {
        $db = SmartContractContext::$db;
        if($key ===  null || strlen($key)==0) {
            $sql="select s.var_value from smart_contract_state s 
                   where s.sc_address = :sc_address and s.variable = :variable and s.height <= :height and s.var_key is null order by s.height desc limit 1";
            $res=$db->single($sql, [":sc_address" =>$address, ":variable" => $name, ":height"=>$height]);
        } else {
            $sql="select s.var_value from smart_contract_state s 
                   where s.sc_address = :sc_address and s.variable = :variable and s.var_key =:var_key and s.height <= :height order by s.height desc limit 1";
            $res=$db->single($sql, [":sc_address" =>$address, ":variable" => $name, ":var_key"=>$key, ":height"=>$height]);
        }
        return $res;
    }
    static function setStateVar($address, $height, $name, $value, $key=null) {
        $db = SmartContractContext::$db;
        $var_value = $value === null ? null : "$value";
        if($key === null || strlen($key)==0) {
            $sql="select * from smart_contract_state sc where sc_address=:address
                and variable = :variable and var_key is null and height = :height";
            $row = $db->row($sql, [":address"=>$address, ":variable"=>$name, ":height"=>$height]);
            if($row) {
                $sql="update smart_contract_state set var_value=:var_value where sc_address=:address
                and variable = :variable and var_key is null and height = :height";
            } else {
                $sql="insert into smart_contract_state set var_value=:var_value, sc_address=:address
                , variable = :variable , var_key = null, height = :height";
            }
            $res = $db->exec($sql, [":address"=>$address, ":variable"=>$name, ":height"=>$height, ":var_value"=>$var_value]);
            if($res === false) {
                throw new Exception("Error storing variable $name key=$key for Smart Contract ".$address.": ".$db->errorInfo()[2]);
            }
        } else {
            $sql="select * from smart_contract_state sc where sc_address=:address
                and variable = :variable and var_key =:var_key and height = :height";
            $row = $db->row($sql, [":address"=>$address, ":variable"=>$name, ":height"=>$height,":var_key"=>$key]);
            if($row) {
                $sql="update smart_contract_state set var_value=:var_value where sc_address=:address
                and variable = :variable and var_key =:var_key and height = :height";
            } else {
                $sql="insert into smart_contract_state set var_value=:var_value, sc_address=:address
                , variable = :variable, var_key =:var_key, height = :height";
            }
            $res = $db->exec($sql, [":address"=>$address, ":variable"=>$name, ":height"=>$height, ":var_value"=>$var_value,":var_key"=>$key]);
            if($res === false) {
                throw new Exception("Error storing variable $name key=$key for Smart Contract ".$address.": ".$db->errorInfo()[2]);
            }
        }
    }

    static function countStateVar($address, $height, $name) {
        $db = SmartContractContext::$db;

        $sql = "select count(distinct s.var_key) from smart_contract_state s 
           where s.sc_address = :sc_address and s.variable = :variable and s.height <= :height";
        $res=$db->single($sql, [":sc_address" =>$address, ":variable" => $name, ":height"=>$height]);
        return $res;
    }

    static function stateVarKeys($address, $height, $name) {
        $db = SmartContractContext::$db;

        $sql="select distinct(s.var_key) from smart_contract_state s 
               where s.sc_address = :sc_address and s.variable = :variable and s.height<=:height order by s.var_key";
        $rows=$db->select($sql, [":sc_address" =>$address, ":variable" => $name, ":height"=>$height]);
        $list = [];
        foreach ($rows as $row) {
            $list[]=$row['var_key'];
        }
        return $list;
    }

    static function stateAll($address, $height, $name) {
        $db = SmartContractContext::$db;

        $sql="select s.var_key, s.var_value from smart_contract_state s 
               where s.sc_address = :sc_address and s.variable = :variable and s.height <= :height order by s.var_key";
        $rows=$db->select($sql, [":sc_address" =>$address, ":variable" => $name, ":height"=>$height]);
        $list = [];
        foreach ($rows as $row) {
            $key = $row['var_key'];
            $val = $row['var_value'];
            $list[$key]=$val;
        }
        return $list;
    }

    static function stateClear($address, $name, $key=null) {
        $db = SmartContractContext::$db;

        if($key == null) {
            $sql = "delete from smart_contract_state 
               where sc_address = :sc_address and variable = :variable";
            $res = $db->exec($sql, [":sc_address" => $address, ":variable" => $name]);
        } else {
            $sql = "delete from smart_contract_state 
               where sc_address = :sc_address and variable = :variable and var_key =:key";
            $res = $db->exec($sql, [":sc_address" => $address, ":variable" => $name, ":key"=>$key]);
        }
        if($res === false) {
            throw new Exception("Error deleting map variable $name for Smart Contract ".$address.": ".$db->errorInfo()[2]);
        }
        return $res;
    }

    static function query($address, $sql, $params =[]) {

        throw new Exception("Call to denied function");

        $db = SmartContractContext::$db;
        $final_sql="with s as (select ss.variable, ss.var_key, ss.var_value
               from (select s.sc_address, s.variable, ifnull(s.var_key, 'null') as var_key, max(s.height) as height
                     from smart_contract_state s
                     where s.sc_address = :sc_address
                     group by s.variable, s.var_key, s.sc_address) as last_vars
                        join smart_contract_state ss
                             on (ss.sc_address = last_vars.sc_address and ss.variable = last_vars.variable
                                 and ifnull(ss.var_key, 'null') = last_vars.var_key and ss.height = last_vars.height))
                                 select *
                from s
                where 1=1 ";
        $final_sql.= " $sql";
        $all_params = $params;
        $all_params[":sc_address"] = $address;
        $rows=$db->exec($final_sql, $all_params);
        $list = [];
        foreach ($rows as $row) {
            $key = $row['var_key'];
            $val = $row['var_value'];
            $list[$key]=$val;
        }
        return $list;
    }

    static function createSmartContract($transaction, $height) {
        $db = SmartContractContext::$db;
        $sql="select max(height) from blocks";

        $top_height = $db->single($sql);
        if($height <= $top_height) {
            return;
        }

        $sql="insert into smart_contracts (address, height, code, signature, name, description, metadata)
            values (?, ?, ?, ?, ?, ?, ?)";

        $data=$transaction['data'];
        $data = json_decode(base64_decode($data), true);
        $metadata = $data['metadata'] ?? null;
        $name = $metadata['name'] ?? null;
        $description = $metadata['description'] ?? null;

        $bind = [
            $transaction['dst'],
            $height,
            $transaction['data'],
            $transaction['msg'],
            $name,
            $description,
            json_encode($metadata),
        ];

        $res = $db->exec($sql, $bind);
        if(!$res) {
            throw new Exception("Error inserting smart contract: ".$db->errorInfo()[2]);
        }
    }

    static function readVarsState($height, $address) {
        $db = SmartContractContext::$db;
        $state = [];
        $sql="select * from (
            select s.variable, s.var_value,
                   row_number() over (partition by s.sc_address, s.variable order by s.height desc) as rn
            from smart_contract_state s
            where s.sc_address = :address and s.height <= :height
              and s.var_key is null ) as ranked
            where ranked.rn = 1
        ";
        $rows = $db->select($sql, [$address, $height]);
        foreach ($rows as $row) {
            $state[$row['variable']]=$row['var_value'];
        }
        return $state;
    }

    static function cleanState($height, $address) {
        $db = SmartContractContext::$db;
        $sql="delete from smart_contract_state where height >= ? and sc_address = ?";
        $res = $db->exec($sql, [$height, $address]);
        return $res;
    }

}

class SmartContractVirtual {

    static function existsStateVar($name, $key)
    {
        $state = SmartContractContext::$state;
        if(empty($key) || strlen($key)==0) {
            return isset($state[$name]);
        } else {
            return is_array(@$state[$name]) && key_exists($key, @$state[$name]);
        }
    }

    static function getStateVar($name, $key) {
        $state = SmartContractContext::$state;
        $val = @$state[ $name];
        if(is_array($val)) {
            if(empty($key) || strlen($key)==0) {
                return count($val);
            } else {
                return @$state[$name][$key];
            }
        } else {
            return $val;
        }
    }

    public static function setStateVar($name, $key, $value)
    {
        $state = SmartContractContext::$state;
        if($key === null || strlen($key)==0) {
            $state[$name]=$value;
        } else {
            $state[$name][$key]=$value;
        }
        SmartContractContext::$state = $state;
    }

    public static function countStateVar($name)
    {
        $state = SmartContractContext::$state;
        return @count($state[$name] ?? []);
    }

    public static function stateVarKeys($name)
    {
        $state = SmartContractContext::$state;
        ksort($state[$name]);
        return array_keys($state[$name]);
    }

    public static function stateAll($name)
    {
        $state = SmartContractContext::$state;
        ksort($state[$name]);
        return $state[$name];
    }

    public static function stateClear($name, mixed $key)
    {
        $state = SmartContractContext::$state;
        if($key == null) {
            unset($state[$name]);
        } else {
            unset($state[$name][$key]);
        }
        SmartContractContext::$state = $state;
        return true;
    }

    public static function cleanState()
    {
        SmartContractContext::$state = [];
        return true;
    }


}

class SmartContractState {
    static function existsStateVar($address, $height, $name, $key=null) {
        if(SmartContractContext::$virtual) {
            return SmartContractVirtual::existsStateVar($name, $key);
        }
        return SmartContractDB::existsStateVar($address, $height, $name, $key);
    }

    static function getStateVar($address, $height, $name, $key=null) {
        if(SmartContractContext::$virtual) {
            return SmartContractVirtual::getStateVar($name, $key);
        }
        return SmartContractDB::getStateVar($address, $height, $name, $key);
    }

    static function setStateVar($address, $height, $name, $value, $key=null) {
        if(strlen($value ?? "") > 1000) {
            throw new Exception("Storing value for variable $name key $key exceeds 1000 characters");
        }
        if(SmartContractContext::$virtual) {
            SmartContractVirtual::setStateVar($name, $key, $value);
        } else {
            SmartContractDB::setStateVar($address, $height, $name, $value, $key);
        }
        $sc_state_update = [
            "address"=>$address,
            "height"=>$height,
            "name"=>$name,
            "value"=>$value,
            "key"=>$key
        ];
        SmartContractContext::$sc_state_updates[]=$sc_state_update;
    }

    static function countStateVar($address, $height, $name) {
        if(SmartContractContext::$virtual) {
            return SmartContractVirtual::countStateVar($name);
        }
        return SmartContractDB::countStateVar($address, $height, $name);
    }

    static function stateVarKeys($address, $height, $name) {
        if(SmartContractContext::$virtual) {
            return SmartContractVirtual::stateVarKeys( $name);
        }
        return SmartContractDB::stateVarKeys($address, $height, $name);
    }

    static function stateAll($address, $height, $name) {
        if(SmartContractContext::$virtual) {
            return SmartContractVirtual::stateAll( $name);
        }
        return SmartContractDB::stateAll($address, $height, $name);
    }

    static function stateClear($address, $name, $key=null) {
        if(SmartContractContext::$virtual) {
            return SmartContractVirtual::stateClear( $name, $key);
        }
        return SmartContractDB::stateClear($address, $name, $key);
    }

    static function query($address, $sql, $params =[]) {
        throw new Exception("Call to denied function");
        return SmartContractDB::query($address, $sql, $params);
    }

    static function createSmartContract($transaction, $height) {
        SmartContractDB::createSmartContract($transaction, $height);
    }

    static function readVarsState($height, $address) {
        $virtual=SmartContractContext::$virtual;
        if($virtual) {
            $state = SmartContractContext::$state;
        } else {
            $state = SmartContractDB::readVarsState($height, $address);
        }
        return $state;
    }

    static function cleanState($height, $address) {
        $virtual=SmartContractContext::$virtual;
        if($virtual) {
            return true;
        }
        return SMartContractDB::cleanState($height, $address);
    }

}