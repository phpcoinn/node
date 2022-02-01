<?php

class Masternode
{

	public $public_key;
	public $height;

	function __construct()
	{

	}


	static function existsPublicKey($publicKey) {
		global $db;
		$sql="select count(*) from masternode m where m.public_key =:public_key";
		$res = $db->single($sql, [":public_key"=>$publicKey]);
		return $res > 0;
	}

	function add() {
		global $db;
		$sql="insert into masternode (public_key, height) values (:public_key, :height)";
		$res = $db->run($sql, [":public_key" => $this->public_key, ":height"=>$this->height]);
		return $res;
	}

	static function getAll() {
		global $db;
		return $db->run("select * from masternode");
	}

	static function getCount() {
		global $db;
		$sql="select count(1) from masternode";
		return $db->single($sql);
	}

	static function delete($publicKey) {
		global $db;
		$sql="delete from masternode where public_key=:public_key";
		$res = $db->run($sql, [":public_key"=>$publicKey]);
		return $res;
	}

	static function get($publicKey) {
		global $db;
		$sql="select * from masternode where public_key=:public_key";
		$res=$db->run($sql, [":public_key"=>$publicKey]);
		return $res[0];
	}

	static function create($publicKey, $height) {
		$mn = new Masternode();
		$mn->public_key = $publicKey;
		$mn->height = $height;
		$res = $mn->add();
		return $res;
	}

	static function getMnCreateHeight($publicKey) {
		global $db;
		$dst = Account::getAddress($publicKey);
		$sql="select * from transactions where dst =:dst and type=:type order by height desc limit 1";
		$rows = $db->run($sql, [":dst"=>$dst, ":type"=>TX_TYPE_MN_CREATE]);
		return $rows[0]['height'];
	}

	static function allowedMasternodes($height) {
		return FEATURE_MN && $height >= Block::getMnStartHeight();
	}

	static function checkCreateMasternodeTransaction($height, Transaction $transaction, &$error, $verify=true) {

		try {
			//masternodes must be allowed and height after start height
			if (!self::allowedMasternodes($height)) {
				throw new Exception("Not allowed transaction type {$transaction->type} for height $height");
			}

			if(!$verify) {
				//source address can not be masternode
				//TODO: test this case
				$res = Masternode::existsPublicKey($transaction->publicKey);
				if ($res) {
					throw new Exception("Source public key {$transaction->publicKey} is already a masternode");
				}

				//destionation address must be verified
				$dst = $transaction->dst;
				$dstPublicKey = Account::publicKey($dst);
				if (!$dstPublicKey) {
					throw new Exception("Destination address $dst is not verified!");
				}
				//destionation address must not be masternode
				//TODO: test this case
				$res = Masternode::existsPublicKey($dstPublicKey);
				if ($res) {
					throw new Exception("Destination address $dst is already a masternode");
				}
			}
			//masternode collateral must be exact
			if($transaction->val != MN_COLLATERAL) {
				throw new Exception("Invalid masternode collateral {$transaction->val}, must be ".MN_COLLATERAL);
			}
			return true;
		} catch (Exception $e) {
			$error = $e->getMessage();
			_log("Error in masternode create tx: ".$error);
			return false;
		}
	}

	static function checkRemoveMasternodeTransaction($height, Transaction $transaction, &$error, $verify=true) {

		try {
			//masternodes must be allowed and height after start height
			if(!Masternode::allowedMasternodes($height)) {
				throw new Exception("Not allowed transaction type {$transaction->type} for height $height");
			}

			if(!$verify) {
				//masternode must exists in database
				$masternode = Masternode::get($transaction->publicKey);
				if(!$masternode) {
					throw new Exception("Can not find masternode with public key ".$transaction->publicKey);
				}
				//masternode must run minimal number of blocks
				if($height < $masternode['height'] + MN_MIN_RUN_BLOCKS ) {
					throw new Exception("Masternode must run at least ".MN_MIN_RUN_BLOCKS." blocks. Created at block ". $masternode['height']. " check block=".$height);
				}
				//destination address can not be masternode
				//TODO: test this case
				$dst = $transaction->dst;
				$dstPublicKey = Account::publicKey($dst);
				if($dstPublicKey) {
					$res = Masternode::existsPublicKey($dstPublicKey);
					if($res) {
						throw new Exception("Destination address $dst can not be masternode");
					}
				}
			}
			//masternode collateral must be exact
			if($transaction->val != MN_COLLATERAL) {
				throw new Exception("Invalid masternode collateral {$transaction->val}, must be ".MN_COLLATERAL);
			}
			return true;

		} catch (Exception $e) {
			$error = $e->getMessage();
			_log("Error in masternode remove tx: ".$error);
			return false;
		}



	}

	static function checkIsSendFromMasternode($height, Transaction $transaction, &$error, $verify) {
		try {
			if(!$verify) {
				//check if source address is masternode
				if (Masternode::allowedMasternodes($height)) {
					$masternode = Masternode::get($transaction->publicKey);
					if($masternode) {
						$balance = Account::getBalanceByPublicKey($transaction->publicKey);
						if(floatval($balance) - $transaction->val < MN_COLLATERAL) {
							throw new Exception("Can not spent more than collateral. Balance=$balance amount=".$transaction->val);
						}
					}
				}
			}
			return true;
		} catch (Exception $e) {
			$error = $e->getMessage();
			_log("Error in  send tx: ".$error);
			return false;
		}
	}

}
