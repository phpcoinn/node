<?php

class SmartContractWrapper
{

	public $args;
	private $response;
	private $smartContract;
    private $db;
    private $state;

	public $internal = false;

	public function __construct($name = null)
	{
		$this->smartContract = $this->getSmartContract($name);
		if(!$this->smartContract) {
			throw new Exception("Smart contract class not found");
		}
		$this->parseArgs();
	}

	private function parseArgs() {
		global $argv;
		if(isset($argv[1])) {
			$args = $argv[1];
			$args = json_decode(base64_decode($args), true);
			$this->args = $args;
		}
	}

	public function store() {
        if(!$this->internal) {
            $this->outResponse();
        }
	}

	public function run() {
        $this->connectDb();
		$method = $this->args['type'];
		$this->smartContract->log("RUN $method");
        $this->initSmartContractVars();
		try {
			if($method == "exec") {
				$this->exec_method();
			} elseif ($method == "view") {
				$this->view_method();
			} elseif ($method == "interface") {
				$this->getInterface();
			} elseif ($method == "deploy") {
				$this->deploy();
			} elseif ($method == "verify") {
				$this->verify();
			}
		} catch (Throwable $e) {
			$this->error($e, $method);
		}
	}

    private function loadState() {
        $reflect = new ReflectionClass($this->smartContract);
        $props = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
        $this->state = $this->readState();
        foreach($props as $prop) {
            if($this->hasAnnotation($prop, "SmartContractVar")) {
                $name = $prop->getName();
                $prop->setValue($this->smartContract, $this->state[$name]);
            }
        }
    }

    private function readState() {
        $virtual=$this->args['virtual'];
        if($virtual) {
            $state = [];
            $state_file = ROOT . '/tmp/sc/'.SC_ADDRESS.'.state.json';
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
            $rows = $this->db->run($sql, [":address"=> SC_ADDRESS]);
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


    private function saveState() {
        $height=intval($this->args['height'])+1;
        $reflect = new ReflectionClass($this->smartContract);
        $props = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach($props as $prop) {
            if($this->hasAnnotation($prop, "SmartContractVar")) {
                $name = $prop->getName();
                $value = $prop->getValue($this->smartContract);
                if((string)$this->state[$name]!==(string)$value) {
                    SmartContractBase::setStateVar($this->db, SC_ADDRESS, $height, $name, $value, null);
                }
            }
        }
    }

    private function connectDb() {
        require_once ROOT.'/include/db.inc.php';
        $CONFIG=$_SERVER['CONFIG'];
        $CONFIG=json_decode(base64_decode($CONFIG),true);
        $this->db = new DB($CONFIG['db_connect'], $CONFIG['db_user'], $CONFIG['db_pass'], false);
    }

	public function view_method() {
		$methodName = $this->args['method'];
		$params = $this->args['params'];
		$reflect = new ReflectionClass($this->smartContract);
		$method = $reflect->getMethod($methodName);
		if(!$this->hasAnnotation($method, "SmartContractView")) {
			throw new Exception("Method $methodName is not callable");
		}
        $this->db->beginTransaction();
        $this->loadState();
		$this->invoke($method, $params);
        $this->db->rollBack();
		$this->outResponse();
	}

	private function invoke($method, $params) {
        unset($_SERVER);
		if(empty($params)) {
			$this->response = $method->invoke($this->smartContract);
		} else {
			$this->response = $method->invoke($this->smartContract, ...$params);
		}
	}

	public function exec_method() {
		$methodName = $this->args['method'];
		$params = $this->args['params'];
		$this->smartContract->setFields($this->args);
		$reflect = new ReflectionClass($this->smartContract);
		$method = $reflect->getMethod($methodName);
		if(!$this->hasAnnotation($method, "SmartContractTransact")) {
			throw new Exception("Method $methodName is not executable");
		}
		$this->smartContract->log("Invoke method $methodName params=".json_encode($params));
        $this->startTx();
        $this->loadState();
		$this->invoke($method, $params);
        $this->saveState();
        $this->endTx();
		$this->store();
	}

    private function initSmartContractVars() {
        $virtual=$this->args['virtual'];
        SmartContractBase::$virtual = $virtual;
        $reflect = new ReflectionClass($this->smartContract);
        $props = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach($props as $prop) {
            if($this->hasAnnotation($prop, "SmartContractMap")) {
                $name = $prop->getName();
                $height=intval($this->args['height'])+1;
                $smartContractMap = new SmartContractMap($this->db, $name, $height);
                $prop->setValue($this->smartContract, $smartContractMap);
            }
        }
    }

	private function outResponse() {
		$out = [
			"response" => $this->response
		];
		$this->out($out);
	}

	public function get($propertyName) {
		$reflect = new ReflectionClass($this);
		$prop = $reflect->getProperty($propertyName);
		$value = $prop->getValue($this);
		return $value;
	}

    private function get_deploy_method() {
        $reflect = new ReflectionClass($this->smartContract);
        $methods = $reflect->getMethods(ReflectionProperty::IS_PUBLIC);
        foreach ($methods as $method) {
            if($this->hasAnnotation($method, "SmartContractDeploy")) {
                return $method;
            }
        }
        return false;
    }

	public function verify() {
		$reflect = new ReflectionClass($this->smartContract);
		if(!$reflect->isSubclassOf(SmartContractBase::class)) {
			throw new Error("Main class not extends SmartContractBase");
		}
        $deploy_method = $this->get_deploy_method();
		if(!$deploy_method) {
            throw new Error("Deploy method not found");
		}
		$this->out("OK");
	}

	public function getInterface() {
		$interface = [];
		$reflect = new ReflectionClass($this->smartContract);

		$version = "1.0.0";

		$annotations = $this->getAnnotations($reflect);
		if(!empty($annotations) && is_array($annotations)) {
			foreach($annotations as $annotation) {
				if(strpos($annotation, "@SmartContractVersion") == 0) {
					$p1 = strpos($annotation, "(");
					$p2 = strpos($annotation, ")", $p1);
					$version = substr($annotation, $p1+1, $p2 - $p1 - 1);
				}
			}
		}

		$interface['version']=$version;

		$props = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
		foreach($props as $prop) {
			$name = $prop->getName();
			if($prop->class == 'SmartContractBase') {
				continue;
			}
			if($this->hasAnnotation($prop, "SmartContractIgnore")) {
				continue;
			}
            if($this->hasAnnotation($prop, "SmartContractVar")) {
                $property=[];
                $property['name']=$name;
                $interface["properties"][]=$property;
            }
            if($this->hasAnnotation($prop, "SmartContractMap")) {
                $property=[];
                $property['name']=$name;
                $property['type']="map";
                $interface["properties"][]=$property;
            }
		}
		$methods = $reflect->getMethods(ReflectionProperty::IS_PUBLIC);
		foreach ($methods as $method) {
			$name = $method->getName();
			if($name == "__construct") {
				continue;
			}
			if($this->hasAnnotation($method, "SmartContractDeploy")) {
				continue;
			}
			if($this->hasAnnotation($method, "SmartContractIgnore")) {
				continue;
			}
			if($this->hasAnnotation($method, "SmartContractTransact")) {
				$ref_params = $method->getParameters();
				$params = [];
				foreach($ref_params as $ref_param) {
					$params[]=$ref_param->getName();
				}
				$interface["methods"][]=[
					"name"=>$name,
					"params"=>$params
				];
			}
			if($this->hasAnnotation($method, "SmartContractView")) {
				$ref_params = $method->getParameters();
				$params = [];
				foreach($ref_params as $ref_param) {
					$params[]=$ref_param->getName();
				}
				$interface["views"][]=[
					"name"=>$name,
					"params"=>$params
				];
			}
		}

		$this->out(["interface"=>$interface]);
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

	public function deploy() {
        $deploy_method = $this->get_deploy_method();
		if(!$deploy_method) {
			throw new Exception("Deploy method not found");
		}
        $this->startTx();
        $this->createSmartContract();

		$this->smartContract->setFields($this->args);

		$params = $this->args['params'];
        $this->loadState();
		$this->invoke($deploy_method, $params);
        $this->saveState();
        $this->endTx();
		$this->store();
	}

    private function startTx() {
        $this->db->beginTransaction();
    }

    private function endTx() {
        $test = $this->args['test'];
        if($test) {
            $this->db->rollBack();
        } else {
            $this->db->commit();
        }
    }

    private function createSmartContract() {
        $transaction=$this->args['transaction'];
        $height=$this->args['height'];
        SmartContractBase::insertSmartContract($this->db, $transaction, $height);
    }

	private function getSmartContract($className = null) {
		if(empty($className)) {
			if(defined("SC_CLASS_NAME")) {
				$className = SC_CLASS_NAME;
			} else if (class_exists('SmartContract')) {
				$className = 'SmartContract';
			}
		}
		if(!empty($className)) {
			$class = new $className();
			return $class;
		}
	}

	public function out($data) {
        @ob_end_clean();
        ob_start();
		echo json_encode(["status" => "ok", "data" => $data]);
		exit;
	}

	public function error(Throwable $e, $method) {
        @ob_end_clean();
        ob_start();
        $error = "Error running smart contract method=$method: ".$e->getMessage();
		echo json_encode(["status" => "error", "error" => $error, "trace"=>$e->getTraceAsString()]);
		exit;
	}

	private function log($s) {
		$this->smartContract->log($s);
	}


}
