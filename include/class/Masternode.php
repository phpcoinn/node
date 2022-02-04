<?php

class Masternode
{

	public $public_key;
	public $height;
	public $win_height;
	public $signature;

	function __construct()
	{

	}

	function sign($height, $private_key) {
		$base = self::getSignatureBase($this->public_key, $this->height, $height);
		$this->signature = ec_sign($base, $private_key);
		return $this->signature;
	}

	static function getSignatureBase($public_key, $height, $next_block_height) {
		$parts = [];
		$address = Account::getAddress($public_key);
		$parts[]=$address;
		$parts[]=$next_block_height;
		$parts[]=$height;
		return implode("-", $parts);
	}

	function storeSignature() {
		global $db;
		$sql = "update masternode set signature = :signature where public_key = :public_key";
		$res = $db->row($sql, [":signature", $this->signature, ":public_key"=>$this->public_key]);
		return $res;
	}

	static function fromDB($row) {
		$masternode = new Masternode();
		$masternode->public_key = $row['public_key'];
		$masternode->height = $row['height'];
		$masternode->win_height = $row['win_height'];
		$masternode->signature = $row['signature'];
		return $masternode;
	}

	static function existsPublicKey($publicKey) {
		global $db;
		$sql="select count(*) from masternode m where m.public_key =:public_key";
		$res = $db->single($sql, [":public_key"=>$publicKey]);
		return $res > 0;
	}

	function add() {
		global $db;
		$sql="insert into masternode (public_key, height, signature) values (:public_key, :height, :signature)";
		$res = $db->run($sql, [
			":public_key" => $this->public_key,
			":height"=>$this->height,
			":signature"=>$this->signature,
		]);
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

	static function create($publicKey, $height, $signature) {
		$mn = new Masternode();
		$mn->public_key = $publicKey;
		$mn->height = $height;
		$mn->signature = $signature;
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

	static function getWinner($height) {
		global $db;
		$sql = "select * from masternode where win_height = :height";
		$row = $db->row($sql, [":height"=>$height]);
		return $row;
	}

	static function updateWinner($height, $public_key) {
		global $db;
		$sql="update masternode set win_height = :height where public_key = :public_key";
		$res = $db->run($sql, [":height"=>$height, ":public_key" => $public_key]);
		return $res;
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

	static function isLocalMasternode() {
		if(isset($_config['masternode']) && $_config['masternode']==true && isset($_config['masternode_public_key']) && isset($_config['masternode_private_key'])) {
			return true;
		} else {
			return false;
		}
	}

	static function processBlock() {
		global $_config;
		$height = Block::getHeight();
		if(!Masternode::allowedMasternodes($height)) {
			_log("Masternode: not enabled", 5);
			return;
		}
		if(!Masternode::isLocalMasternode()) {
			_log("Masternode: Local node is not masternode", 5);
			return;
		}
		$masternode = self::get($_config['masternode_public_key']);
		if(!$masternode) {
			_log("Masternode: Not found local masternode in list", 3);
			return;
		}
		$masternode = Masternode::fromDB($masternode);
		$res = $masternode->sign($height+1, $_config['masternode_private_key']);
		if(!$res) {
			_log("Masternode: Error signing masternode row");
			return;
		}
		$res = $masternode->storeSignature();
		if(!$res) {
			_log("Masternode: Error saving masternode signature");
			return;
		}

		$dir = ROOT."/cli";
		system("php $dir/propagate.php masternode > /dev/null 2>&1  &");
	}

	static function propagate() {
		global $_config;
		$height = Block::getHeight();
		if(!Masternode::allowedMasternodes($height)) {
			return;
		}
		if(!Masternode::isLocalMasternode()) {
			return;
		}
		_log("Masternode: propagate masternode signature",3);
		$peers = Peer::getAll();
		if(count($peers)==0) {
			_log("Masternode: No peers to propagate", 5);
		} else {
			$masternode = Masternode::get($_config['masternode_public_key']);
			foreach ($peers as $peer) {
				$url = $peer['hostname']."/peer.php?q=updateMasternode";
				_log("Masternode: Propagating to peer: ".$url,5);
				$res = peer_post($url, $masternode);
			}
		}
	}

	static function updateMasternode($masternode) {
		$height = Block::getHeight();
		if(!Masternode::allowedMasternodes($height)) {
			return;
		}
		_log("Masternode: received request to update masternode");

		$base = Masternode::getSignatureBase($masternode['public_key'], $masternode['height'], $height+1);
		$res = ec_verify($base, $masternode['signature'], $masternode['public_key']);
		if(!$res) {
			_log("Masternode: remote masternode isgnature not verified");
			return;
		}

		$savedMasternode = self::get($masternode['public_key']);
		if(!$savedMasternode) {
			_log("Masternode: Not found remote masternode in list", 3);
			if(!$res) {
				_log("Masternode: can not insert new masternode");
				return;
			}
		} else {
			$savedMasternode = Masternode::fromDB($savedMasternode);
			$savedMasternode->signature = $masternode['signature'];
			$res = $savedMasternode->storeSignature();
			if(!$res) {
				_log("Masternode: can not save remote masternode signature");
				return;
			}
		}
		_log("Masternode: updated remote masternode signature", 3);

	}

	static function getRewardTx($generator, $new_block_date, $public_key, $private_key, $height, &$mn_signature) {
		if(!Masternode::allowedMasternodes($height)) {
			return false;
		}
		_log("Masternode: generating reward transaction");
		$winner = Masternode::getWinner($height);
		if(!$winner) {
			_log("Masternode: not found winner");
			$dst = $generator;
		} else {
			$base = Masternode::getSignatureBase($winner['public_key'], $winner['height'], $height);
			$res = ec_verify($base, $winner['signature'], $winner['public_key']);
			if($res) {
				$dst = Account::getAddress($winner['public_key']);
				$mn_signature = $winner['signature'];
			} else {
				$dst = $generator;
				_log("Masternode: signature not valid");
			}
		}
		$rewardinfo = Block::reward($height);
		$reward = $rewardinfo['masternode'];
		$reward_tx = Transaction::getRewardTransaction($dst, $new_block_date, $public_key, $private_key, $reward, "masternode");
		return $reward_tx;
	}

	static function checkTx(Transaction $transaction, $height, &$error) {
		if(!Masternode::allowedMasternodes($height)) {
			return true;
		}
		if($transaction->msg != "masternode") {
			return true;
		}

		try {
			$block = Block::current();
			if($block['masternode']) {
				if($transaction->dst != $block['masternode']) {
					throw new Exception("Transaction dst invalid. Must be masternode");
				}
				$mnPublicKey = Account::publicKey($block['masternode']);
				if(!$mnPublicKey) {
					throw new Exception("Not found public key for msternode");
				}
				$masternode = Masternode::get($mnPublicKey);
				if($masternode) {
					throw new Exception("Masternode not found in list");
				}
				$signatureBase = Masternode::getSignatureBase($mnPublicKey, $masternode['height'], $height);
				$res = ec_verify($signatureBase, $block['mn_signature'], $mnPublicKey);
				if(!$res) {
					throw new Exception("Masternode signature not valid");
				}
			} else {
				if($transaction->dst != $block['generator']) {
					throw new Exception("Transaction dst invalid. Must be generator");
				}
			}

			$rewardinfo = Block::reward($height);
			$reward = $rewardinfo['masternode'];
			if($transaction->val != $reward) {
				throw new Exception("Invalid transaction reward");
			}

			//TODO: other checks
			return true;

		} catch (Exception $e) {
			$error = $e->getMessage();
			_log($error);
			return false;
		}


	}

	static function processRewardTx(Transaction  $transaction, &$error=null) {
		$block = Block::current();
		$height = $block['height'];
		if(!Masternode::allowedMasternodes($height)) {
			return true;
		}
		if($transaction->msg != "masternode") {
			return true;
		}
		try {

			if($block['masternode']) {
				$mnPublicKey = Account::publicKey($block['masternode']);
				$res = Masternode::updateWinner($height, $mnPublicKey);
				if(!$res) {
					throw new Error("Error updating masternode winner");
				}
			}

			return true;
		} catch (Exception $e) {
			$error = $e->getMessage();
			_log($error);
			return false;
		}
	}

}
