<?php

class SmartContractBase
{

    public static $virtual = false;

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

	public function setFields($args) {
		$tx = $args['transaction'];
		$this->src = $tx['src'];
		$this->dst = $tx['dst'];
		$this->value = floatval($tx['val']);
		$this->height = intval($args['height'])+1;
		$this->address = SC_ADDRESS;
	}

	public function log($s) {
		$log_file = ROOT . "/tmp/sc/smart_contract.log";
		$s = date("r")." ".$this->address.": ".$s.PHP_EOL;
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


        $sql="replace into smart_contract_state (sc_address, variable, var_key, var_value, height)
					values (:sc_address, :variable, :var_key, :var_value, :height)";
        $bind = [
            ":sc_address"=>$address,
            ":variable"=>$name,
            ":var_key"=>$key,
            ":var_value"=> $value === null ? null : "$value",
            ":height"=>$height
        ];
        $res = $db->run($sql, $bind);
        if($res === false) {
            throw new Exception("Error storing variable $name key=$key for Smart Contract ".$address.": ".$db->errorInfo()[2]);
        }
    }

    static function insertSmartContract($db, $transaction, $height) {

        if(self::$virtual) {
            return;
        }

        $sql="insert into smart_contracts (address, height, code, signature)
            values (:address, :height, :code, :signature)";

        $bind = [
            ":address" => $transaction['dst'],
            ":height" => $height,
            ":code" => $transaction['data'],
            ":signature" => $transaction['msg'],
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

}
