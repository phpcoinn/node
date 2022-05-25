<?php

class SmartContractWrapper
{

	private $store = [];
	private $response;

	public function __construct()
	{

	}

	public function store($obj) {
		$reflect = new ReflectionClass($obj);
		$props = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
		foreach($props as $prop) {
			$name = $prop->getName();
			$this->store[$name]=$prop->getValue($obj);
		}
		$this->outState();
	}

	public function load($obj) {
		$this->loadState();
		$reflect = new ReflectionClass($obj);
		$props = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
		foreach($props as $prop) {
			$name = $prop->getName();
			if(isset($this->store[$name])) {
				$prop->setValue($obj,$this->store[$name]);
			}
		}
	}

	public function run($obj, $method) {
		if($method == "exec") {
			$this->exec_method($obj);
		} elseif ($method == "call") {
			$this->call_method($obj);
		} elseif ($method == "interface") {
			echo $this->getInterface($obj);
		} elseif ($method == "deploy") {
			$this->deploy($obj);
		}
	}

	public function call_method($obj) {
		global $argv;
		$methodName = $argv[1];
		$params = $argv[2];
		$this->load($obj);
		$reflect = new ReflectionClass($obj);
		$method = $reflect->getMethod($methodName);
		if(!$this->hasAnnotation($method, "SmartContractView")) {
			throw new Exception("Method $methodName is not callable");
		}
		$params = base64_decode($params);
		$params = json_decode($params, true);
		$this->invoke($obj, $method, $params);
		$this->outResponse();
	}

	private function invoke($obj, $method, $params) {
		if(empty($params)) {
			$this->response = $method->invoke($obj);
		} else {
			$this->response = $method->invoke($obj, ...$params);
		}
	}

	private function exec_method($obj) {
		global $argv;
		$methodName = $argv[1];
		$params = $argv[2];
		$tx_sender = $argv[3];
		$tx_value = $argv[4];
		define("TX_SENDER", $tx_sender);
		define("TX_VALUE", $tx_value);
		$this->load($obj);
		$reflect = new ReflectionClass($obj);
		$method = $reflect->getMethod($methodName);
		if(!$this->hasAnnotation($method, "SmartContractTransact")) {
			throw new Exception("Method $methodName is not executable");
		}
		$params = base64_decode($params);
		$params = json_decode($params, true);
		$this->invoke($obj, $method, $params);
		$this->store($obj);
	}

	private function outState() {
		@ob_end_clean();
		ob_start();
		$out = [
			"state" => $this->store
		];
		echo(json_encode($out));
	}

	private function outResponse() {
		@ob_end_clean();
		ob_start();
		$out = [
			"response" => $this->response
		];
		echo(json_encode($out));
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

	public function verify($obj) {
		$reflect = new ReflectionClass($obj);
		$methods = $reflect->getMethods(ReflectionProperty::IS_PUBLIC);
		$deploy_method = false;
		foreach ($methods as $method) {
			if($this->hasAnnotation($method, "SmartContractDeploy")) {
				$deploy_method = true;
			}
		}
		if(!$deploy_method) {
			throw new Exception("Deploy method not found");
		}
		return "OK";
	}

	public function getInterface($obj) {
		$interface = [];
		$reflect = new ReflectionClass($obj);

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
		return json_encode($interface);
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

	public function deploy($obj) {
		global $argv;
		$reflect = new ReflectionClass($obj);
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

		$tx_sender = $argv[1];
		$tx_value = $argv[2];
		define("TX_SENDER", $tx_sender);
		define("TX_VALUE", $tx_value);

		$deploy_method->invoke($obj);
		$this->store($obj);
	}

	static function call_ext($contract, $method, $params) {
		$cmd = "$sc_exec_file $method $params";
	}


}
