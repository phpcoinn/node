<?php

class SmartContractBase
{

    public static $virtual = false;
    public static $sc_state_updates = [];

	protected $src;

	protected $value;
	protected $height;

	protected $address;

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
		$this->address = SC_ADDRESS;
        $this->tx = $tx;
        $this->id = $tx['id'];
	}

	public function log($s) {
		$log_file = ROOT . "/tmp/sc/smart_contract.log";
		$s = $this->height." ".$this->address.": ".$s.PHP_EOL;
		@file_put_contents($log_file, $s, FILE_APPEND);
	}

    static function getStateVar($db, $address, $name, $key=null) {

        if(self::$virtual) {
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

        if($key===null) {
            $sql="select s.var_value from smart_contract_state s 
                   where s.sc_address = :sc_address and s.variable = :variable order by s.height desc limit 1";
            $res=$db->single($sql, [":sc_address" =>$address, ":variable" => $name]);
        } else {
            $sql="select s.var_value from smart_contract_state s 
                   where s.sc_address = :sc_address and s.variable = :variable and s.var_key =:var_key order by s.height desc limit 1";
            $res=$db->single($sql, [":sc_address" =>$address, ":variable" => $name, ":var_key"=>$key]);
        }
        return $res;
    }

    static function setStateVar($db, $address, $height, $name, $value, $key=null) {
        if(strlen($value) > 1000) {
            throw new Exception("Storing value for variable $name key $key exceeds 1000 characters");
        }
        if(self::$virtual) {
            $state_file = ROOT . '/tmp/sc/'.$address.'.state.json';
            $state = json_decode(file_get_contents($state_file), true);
            if($key === null) {
                $state[$name]=$value;
            } else {
                $state[$name][$key]=$value;
            }
            file_put_contents($state_file, json_encode($state));
            return;
        }


        $var_value = $value === null ? null : "$value";
        if($key === null) {
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
        self::$sc_state_updates[]=$sc_state_update;
    }

    static function insertSmartContract($db, $transaction, $height) {

        if(self::$virtual) {
            return;
        }

        $sql="insert into smart_contracts (address, height, code, signature, name, description)
            values (:address, :height, :code, :signature, :name, :description)";

        $data=$transaction['data'];
        $data = json_decode(base64_decode($data), true);
        $name = $data['name'];
        $description = $data['description'];

        $bind = [
            ":address" => $transaction['dst'],
            ":height" => $height,
            ":code" => $transaction['data'],
            ":signature" => $transaction['msg'],
            ":name"=>$name,
            ":description"=>$description,
        ];

        $res = $db->run($sql, $bind);
        _log("INSERT CS = $res");
        if(!$res) {
            throw new Exception("Error inserting smart contract: ".$db->errorInfo()[2]);
        }
    }

    static function existsStateVar($db, $address, $name, $key=null) {
        if(self::$virtual) {
            $state_file = ROOT . '/tmp/sc/'.$address.'.state.json';
            $state = json_decode(file_get_contents($state_file), true);
            if($key === null) {
                return isset($state[$name]);
            } else {
                return isset($state[$name][$key]);
            }
        }

        if($key===null) {
            $sql="select 1 from smart_contract_state s 
                   where s.sc_address = :sc_address and s.variable = :variable order by s.height desc limit 1";
            $res=$db->single($sql, [":sc_address" =>$address, ":variable" => $name]);
        } else {
            $sql="select 1 from smart_contract_state s 
                   where s.sc_address = :sc_address and s.variable = :variable and s.var_key =:var_key order by s.height desc limit 1";
            $res=$db->single($sql, [":sc_address" =>$address, ":variable" => $name, ":var_key"=>$key]);
        }
        return $res == 1;
    }

    static function countStateVar($db, $address, $name) {
        if(self::$virtual) {
            $state_file = ROOT . '/tmp/sc/'.$address.'.state.json';
            $state = json_decode(file_get_contents($state_file), true);
            return @count($state[$name]);
        }

        $sql="select count(distinct s.var_key) from smart_contract_state s 
               where s.sc_address = :sc_address and s.variable = :variable";
        $res=$db->single($sql, [":sc_address" =>$address, ":variable" => $name]);
        return $res;
    }

    static function stateVarKeys($db, $address, $name) {
        if(self::$virtual) {
            $state_file = ROOT . '/tmp/sc/'.$address.'.state.json';
            $state = json_decode(file_get_contents($state_file), true);
            return array_keys($state[$name]);
        }

        $sql="select s.var_key from smart_contract_state s 
               where s.sc_address = :sc_address and s.variable = :variable";
        $rows=$db->run($sql, [":sc_address" =>$address, ":variable" => $name]);
        $list = [];
        foreach ($rows as $row) {
            $list[]=$row['var_key'];
        }
        return $list;
    }

    static function stateAll($db, $address, $name) {
        if(self::$virtual) {
            $state_file = ROOT . '/tmp/sc/'.$address.'.state.json';
            $state = json_decode(file_get_contents($state_file), true);
            return $state[$name];
        }

        $sql="select s.var_key, s.var_value from smart_contract_state s 
               where s.sc_address = :sc_address and s.variable = :variable";
        $rows=$db->run($sql, [":sc_address" =>$address, ":variable" => $name]);
        $list = [];
        foreach ($rows as $row) {
            $key = $row['var_key'];
            $val = $row['var_value'];
            $list[$key]=$val;
        }
        return $list;
    }

    static function stateClear($db, $address, $name, $key=null) {
        if(self::$virtual) {
            $state_file = ROOT . '/tmp/sc/'.$address.'.state.json';
            $state = json_decode(file_get_contents($state_file), true);
            if($key == null) {
                unset($state[$name]);
            } else {
                unset($state[$name][$key]);
            }
            file_put_contents($state_file, json_encode($state));
        }

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

    public static $debug_logs = [];

    public function debug($log) {
        if(self::$virtual) {
            self::$debug_logs[]=$log;
        }
    }

}
