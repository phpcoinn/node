<?php

class Minepool
{

	public $address;
	public $height;
	public $miner;
	public $iphash;

	public static function checkIp($address, $ip)
	{
		global $db, $_config;

		$iphash = self::calculateIpHash($ip);
		_log("Minepool: checkIp address=$address ip=$ip iphash=$iphash");
		$sql = "select * from minepool where iphash=:iphash";
		$row=$db->row($sql, [":iphash" => $iphash]);
		if($row) {
			if($row['address']!=$address) {
				_log("Check ip failed: Address not valid, submitted: $address, in DB: ".$row['address']);
				return false;
			}
		}
		return true;
	}

	public static function insert($address, $height, $miner, $ip) {
		global $db;
		$sql = "select * from minepool where address=:address";
		$row=$db->row($sql, [":address" => $address]);
		if(!$row) {
			$iphash = self::calculateIpHash($ip);
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

	public static function calculateIpHash($ip) {
		global $_config;
		$options = HASHING_OPTIONS;
		$generator_address = Account::getAddress($_config['generator_public_key']);
		$options['salt'] = substr($generator_address, 0, 16);
		$iphash = @password_hash(
			$ip,
			HASHING_ALGO,
			$options
		);
		return $iphash;
	}


}
