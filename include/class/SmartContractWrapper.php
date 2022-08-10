<?php

require_once __DIR__ . "/SmartContractBase.php";

class SmartContractWrapper
{

	public $store = [];
	public $args;
	private $response;
	private $smartContract;

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
		$reflect = new ReflectionClass($this->smartContract);
		$props = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
		foreach($props as $prop) {
			$name = $prop->getName();
			$val = $prop->getValue($this->smartContract);
			$this->smartContract->log("Store state for property $name val=".json_encode($val));
			$this->store[$name]=$val;
		}
		$this->smartContract->log("End store internal=".$this->internal);
		if(!$this->internal) {
			$this->outState();
		}
	}

	public function load() {
		$this->loadState();
		$reflect = new ReflectionClass($this->smartContract);
		$props = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
		foreach($props as $prop) {
			$name = $prop->getName();
			if(isset($this->store[$name])) {
				$prop->setValue($this->smartContract,$this->store[$name]);
			}
		}
	}

	public function run() {
		$method = $this->args['type'];
		$this->smartContract->log("RUN $method");
		try {
			if($method == "exec") {
				$this->exec_method();
			} elseif ($method == "call") {
				$this->call_method();
			} elseif ($method == "interface") {
				$this->getInterface();
			} elseif ($method == "deploy") {
				$this->deploy();
			} elseif ($method == "verify") {
				$this->verify();
			}
		} catch (Exception $e) {
			$this->error("Error running Smart contract: ".$e->getMessage());
		}
	}

	public function call_method() {
		$methodName = $this->args['method'];
		$params = $this->args['params'];
		$this->load();
		$reflect = new ReflectionClass($this->smartContract);
		$method = $reflect->getMethod($methodName);
		if(!$this->hasAnnotation($method, "SmartContractView")) {
			throw new Exception("Method $methodName is not callable");
		}
		$this->invoke($method, $params);
		$this->outResponse();
	}

	private function invoke($method, $params) {
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
		$this->load();
		$reflect = new ReflectionClass($this->smartContract);
		$method = $reflect->getMethod($methodName);
		if(!$this->hasAnnotation($method, "SmartContractTransact")) {
			throw new Exception("Method $methodName is not executable");
		}
		$this->smartContract->log("Invoke method $methodName params=".json_encode($params));
		$this->invoke($method, $params);
		$this->store();
	}

	private function outState() {
		@ob_end_clean();
		ob_start();
		$out = [
			"state" => $this->store,
			"response" => $this->response
		];
		$this->out($out);
	}

	private function outResponse() {
		@ob_end_clean();
		ob_start();
		$out = [
			"response" => $this->response
		];
		$this->out($out);
	}

	private function loadState() {
		$state_file = __DIR__ . "/" . SC_ADDRESS."_state.json";
		$this->store = json_decode(file_get_contents($state_file), true);
	}

	public function get($propertyName) {
		$this->loadState();
		$reflect = new ReflectionClass($this);
		$prop = $reflect->getProperty($propertyName);
		$value = $prop->getValue($this);
		return $value;
	}

	public function verify() {
		$reflect = new ReflectionClass($this->smartContract);
		if(!$reflect->isSubclassOf(SmartContractBase::class)) {
			$this->error("Main class not extends SmartContractBase");
		}
		$methods = $reflect->getMethods(ReflectionProperty::IS_PUBLIC);
		$deploy_method = false;
		foreach ($methods as $method) {
			if($this->hasAnnotation($method, "SmartContractDeploy")) {
				$deploy_method = true;
			}
		}
		if(!$deploy_method) {
			$this->error("Deploy method not found");
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
			$property['name']=$name;
			if($this->hasAnnotation($prop, "SmartContractMap")) {
				$property['type'] = "map";
			}
			$interface["properties"][]=$property;
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
		$reflect = new ReflectionClass($this->smartContract);
		$methods = $reflect->getMethods(ReflectionProperty::IS_PUBLIC);
		$deploy_method = false;
		foreach ($methods as $method) {
			if($this->hasAnnotation($method, "SmartContractDeploy")) {
				$deploy_method = $method;
				break;
			}
		}
		if(!$deploy_method) {
			throw new Exception("Deploy method not found");
		}

		$this->smartContract->setFields($this->args);

		$params = $this->args['params'];
		$this->invoke($deploy_method, $params);
		$this->store();
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
		echo json_encode(["status" => "ok", "data" => $data]);
		exit;
	}

	public function error($error) {
		echo json_encode(["status" => "error", "error" => $error]);
		exit;
	}

	private function log($s) {
		$this->smartContract->log($s);
	}


}
