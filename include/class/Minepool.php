<?php

class Minepool
{

	public $address;
	public $height;
	public $miner;
	public $iphash;

	public static function checkIp($address, $ip, $height, $iphash, $miner)
	{
		global $db, $_config;
		_log("Minepool: checkIp address=$address ip=$ip iphash=$iphash");
		$sql = "select * from minepool where iphash=:iphash";
		$row=$db->row($sql, [":iphash" => $iphash]);
		if($row) {
			//validate iphash
			$ip_verified = password_verify($ip, $iphash);
			if(!$ip_verified) {
				_log("Check ip failed: Argon not verified - ip=$ip argon=$iphash address=$address");
				return false;
			} else {
				if($row['address']!=$address) {
					_log("Check ip failed: Address not valid, submitted: $address, in DB: ".$row['address']);
					return false;
				}
			}
		} else {
			$options = HASHING_OPTIONS;
			$generator_address = Account::getAddress($_config['generator_public_key']);
			$options['salt']=substr($generator_address, 0, 16);
			$argon = @password_hash(
				$ip,
				HASHING_ALGO,
				$options
			);
			if($argon != $iphash) {
				_log("Check ip failed: Iphash not valid, submited: $iphash, calculated: $argon");
				return false;
			}
		}
		return true;
	}

	public static function insert($address, $height, $miner, $iphash) {
		global $db;
		$sql = "select * from minepool where address=:address";
		$row=$db->row($sql, [":address" => $address]);
		if(!$row) {
			$sql="insert into minepool (address, height, miner, iphash) values (:address, :height, :miner, :iphash)";
			$bind = [":address"=>$address, ":height"=>$height, ":miner" => $miner, ":iphash" => $iphash];
			$res = $db->run($sql, $bind);
			if($res === false) {
				_log("Insert in minepool failed. Unable to insert row in minepool - ".json_encode($bind));
				return false;
			}
		}
		return true;
	}

	public static function deleteOldEntries() {
		global $db;
		$current = Block::getHeight();
		$height = $current - 60;
		$sql="delete from minepool where height < :$height";
		$res = $db->run($sql,[$height]);
		_log("Minepool: deleted old entries " . $res);
	}

}
