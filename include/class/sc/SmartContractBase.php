<?php

class SmartContractBase
{

	protected $src;
	protected $dst;
	protected $tx;
	protected $id;

	protected $value;
	protected $height;

	public $address;

	public function isOwner() {
		return $this->src == $this->address;
	}

	public function error($msg) {
		throw new Exception($msg);
	}

    function isExec()
    {
        return $this->tx['type'] == TX_TYPE_SC_EXEC;
    }

    function isSend()
    {
        return $this->tx['type'] == TX_TYPE_SC_SEND;
    }

	public function setFields($args) {
		$tx = $args['transaction'];
		$this->src = $tx['src'];
		$this->dst = $tx['dst'];
		$this->value = floatval($tx['val']);
		$this->height = intval($args['height']);
		$this->address = $args['address'];
        $this->tx = $tx;
        $this->id = $tx['id'];
	}

	public function log($s) {
		$log_file = ROOT . "/tmp/sc/smart_contract.log";
		$s = $this->height." ".$this->address.": ".$s.PHP_EOL;
		@file_put_contents($log_file, $s, FILE_APPEND);
	}

    static function getStateVar($address, $height, $name, $key=null) {
        $db = SmartContractContext::$db;
        if(SmartContractContext::$virtual) {
            $state_file = ROOT . '/tmp/sc/'.$address.'.state.json';
            $state = json_decode(file_get_contents($state_file), true);
            $val = $state[$name];
            if(is_array($val)) {
                if(strlen($key)==0) {
                    return count($val);
                } else {
                    return $state[$name][$key];
                }
            } else {
                return $val;
            }
        }

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
        if(strlen($value) > 1000) {
            throw new Exception("Storing value for variable $name key $key exceeds 1000 characters");
        }
        if(SmartContractContext::$virtual) {
            $state_file = ROOT . '/tmp/sc/'.$address.'.state.json';
            $state = @json_decode(file_get_contents($state_file), true);
            if($key==null || strlen($key)==0) {
                $state[$name]=$value;
            } else {
                $state[$name][$key]=$value;
            }
            file_put_contents($state_file, json_encode($state));
            return;
        }


        $var_value = $value === null ? null : "$value";
        if(strlen($key)==0) {
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
            $res = $db->run($sql, [":address"=>$address, ":variable"=>$name, ":height"=>$height, ":var_value"=>$var_value]);
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
            $res = $db->run($sql, [":address"=>$address, ":variable"=>$name, ":height"=>$height, ":var_value"=>$var_value,":var_key"=>$key]);
            if($res === false) {
                throw new Exception("Error storing variable $name key=$key for Smart Contract ".$address.": ".$db->errorInfo()[2]);
            }
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

    static function insertSmartContract($db, $transaction, $height) {

        if(SmartContractContext::$virtual) {
            return;
        }

        $sql="select max(height) from blocks";

        $top_height = $db->single($sql);
        if($height <= $top_height) {
            return;
        }

        $sql="insert into smart_contracts (address, height, code, signature, name, description, metadata)
            values (:address, :height, :code, :signature, :name, :description, :metadata)";

        $data=$transaction['data'];
        $data = json_decode(base64_decode($data), true);
        $metadata = $data['metadata'];
        $name = $metadata['name'];
        $description = $metadata['description'];

        $bind = [
            ":address" => $transaction['dst'],
            ":height" => $height,
            ":code" => $transaction['data'],
            ":signature" => $transaction['msg'],
            ":name"=>$name,
            ":description"=>$description,
            ":metadata"=>json_encode($metadata),
        ];

        $res = $db->run($sql, $bind);
        _log("INSERT CS = $res");
        if(!$res) {
            throw new Exception("Error inserting smart contract: ".$db->errorInfo()[2]);
        }
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

    static function countStateVar($address, $height, $name) {
        $db = SmartContractContext::$db;

        $sql = "select count(distinct s.var_key) from smart_contract_state s 
           where s.sc_address = :sc_address and s.variable = :variable and s.height <= :height";
        $res=$db->single($sql, [":sc_address" =>$address, ":variable" => $name, ":height"=>$height]);
        _log("countStateVar res=$res sql=$sql address=$address name=$name height=$height");
        return $res;
    }

    static function stateVarKeys($address, $height, $name) {
        $db = SmartContractContext::$db;

        $sql="select distinct(s.var_key) from smart_contract_state s 
               where s.sc_address = :sc_address and s.variable = :variable and s.height<=:height order by s.var_key";
        $rows=$db->run($sql, [":sc_address" =>$address, ":variable" => $name, ":height"=>$height]);
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
        $rows=$db->run($sql, [":sc_address" =>$address, ":variable" => $name, ":height"=>$height]);
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
            $res = $db->run($sql, [":sc_address" => $address, ":variable" => $name]);
        } else {
            $sql = "delete from smart_contract_state 
               where sc_address = :sc_address and variable = :variable and var_key =:key";
            $res = $db->run($sql, [":sc_address" => $address, ":variable" => $name, ":key"=>$key]);
        }
        if($res === false) {
            throw new Exception("Error deleting map variable $name for Smart Contract ".$address.": ".$db->errorInfo()[2]);
        }
        return $res;
    }

    static function query($address, $name, $height, $sql, $params =[]) {
        $db = SmartContractContext::$db;

        $sql="select s.var_key, s.var_value from smart_contract_state s 
               where s.sc_address = :sc_address and s.variable = :variable and s.height <= :height $sql order by s.var_key";
        $all_params = $params;
        $all_params[":sc_address"] = $address;
        $all_params[":variable"] = $name;
        $all_params[":height"] = $height;
        $rows=$db->run($sql, $all_params);
        $list = [];
        foreach ($rows as $row) {
            $key = $row['var_key'];
            $val = $row['var_value'];
            $list[$key]=$val;
        }
        return $list;
    }

    public function debug($log) {
        if(SmartContractContext::$virtual) {
            SmartContractContext::$debug_logs[]=$log;
        }
    }

    public function execSmartContract($contract, $method, $params) {
        $this->requireExtFile($contract);
        $smartContractWrapper = new SmartContractWrapper($contract);
        if($this->isFromTransactMethod()) {
            $is_transact_method = $smartContractWrapper->isTransactMethod($method);
            if($is_transact_method) {
                $smartContractWrapper->execExt($this, $method, $params);
            } else {
                $this->error("Can not exec transact external method from non-transact method");
            }
        } else {
            $this->error("Can not exec external method from non-transact method");
        }
    }

    public function callSmartContract($contract, $method, $params) {
        $this->requireExtFile($contract);
        $smartContractWrapper = new SmartContractWrapper($contract);
        return $smartContractWrapper->callExt($this, $method, $params);
    }

    private function requireExtFile($contract) {
        require_once "phar://".ROOT."/tmp/sc/$contract.phar";
    }

    private function isFromTransactMethod() {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($backtrace as $frame) {
            if (isset($frame['class'], $frame['function'])) {
                try {
                    $reflection = new ReflectionMethod($frame['class'], $frame['function']);
                    $docComment = $reflection->getDocComment();
                    if ($docComment && strpos($docComment, '@SmartContractTransact') !== false) {
                        _log("Detected @SmartContractView in {$frame['class']}::{$frame['function']}");
                        return true;
                    }
                } catch (ReflectionException $e) {
                    // Handle cases where the method doesn't exist
                    continue;
                }
            }
        }
        return false;
    }

    public function getExtFields() {
        $args['transaction']=$this->tx;
        $args['height']=$this->height;
        $args['address']=$this->address;
        return $args;
    }

}
