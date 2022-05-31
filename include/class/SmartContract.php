<?php

class SmartContract
{

	public static function getSmartContract($address) {
		global $db;
		$sql = "select * from smart_contracts sc where sc.address = :address";
		$row = $db->row($sql, [":address"=>$address]);
		return $row;
	}



	public static function checkCreateSmartContractTransaction($height, Transaction $transaction, &$error, $verify)
	{
		try {

			if(!($height >= SC_START_HEIGHT)) {
				throw new Exception("Not allowed transaction type {$transaction->type} for height $height");
			}

			$dst = $transaction->dst;

			if(!$verify) {
				$smartContract = self::getSmartContract($dst);
				if($smartContract) {
					throw new Exception("Smart contract with address $dst already exists");
				}
			}

			$value = $transaction->val;
			if($value != 0) {
				throw new Exception("Invalid value for transaction");
			}

			$fee = $transaction->fee;
			if($fee != TX_SC_CREATE_FEE) {
				throw new Exception("Invalid fee for transaction");
			}

			$data = $transaction->data;
			$sc_signature = $transaction->msg;
			_log("Check SC signature data=$data signature=$sc_signature pk=".$transaction->publicKey);
			$res = ec_verify($data, $sc_signature, $transaction->publicKey);
			if(!$res) {
				throw new Exception("Invalid signature for smart contract");
			}

			return true;
		} catch (Exception $e) {
			$error = $e->getMessage();
			_log("Error in create smart contract tx: ".$error);
			return false;
		}
	}

	public static function checkExecSmartContractTransaction($height, Transaction $transaction, &$error, $verify) {
		return try_catch(function () use ($height, $transaction, $error) {
			if(!($height >= SC_START_HEIGHT)) {
				throw new Exception("Not allowed transaction type {$transaction->type} for height $height");
			}
			$dst = $transaction->dst;
			$dstPublicKey = Account::publicKey($dst);
//			if ($dstPublicKey) {
//				throw new Exception("Smart contract address $dst must be not verified!");
//			}
			$smartContract = self::getSmartContract($dst);
			if(!$smartContract) {
				throw new Exception("Smart contract with address $dst does not exists");
			}
			$fee = $transaction->fee;
			if($fee != TX_SC_EXEC_FEE) {
				throw new Exception("Invalid fee for transaction");
			}
			return true;
		}, $error);
	}

	public static function createSmartContract(Transaction &$transaction, $height, &$error = null, $test = false) {
		try {
			global $db;
			if($test) {
				$db->beginTransaction();
			}
			$sql="insert into smart_contracts (address, height, code, signature)
				values (:address, :height, :code, :signature)";

			$bind = [
				":address" => $transaction->dst,
				":height" => $height,
				":code" => $transaction->data,
				":signature" => $transaction->msg,
			];

			$res = $db->run($sql, $bind);
			_log("INSERT CS = $res");
			if(!$res) {
				throw new Exception("Error inserting smart contract: ".$db->errorInfo()[2]);
			}

			$res = SmartContractEngine::deploy($transaction, $height,  $err, $test);
			if(!$res) {
				throw new Exception("Error calling deploy method of smart contract: $err");
			}

			if($test) {
				$db->rollback();
			}

			return true;
		} catch (Exception $e) {
			$error = $e->getMessage();
			_log("createSmartContract error=$error");
			return false;
		}
	}

	public static function execSmartContract(Transaction $transaction, $height, &$error = null, $test=false) {
		return try_catch(function () use ($error, $transaction, $height, $test) {
			global $db;
			if($test) {
				$db->beginTransaction();
			}
			$message = $transaction->msg;
			$exec_params = json_decode(base64_decode($message), true);
			$method = $exec_params['method'];
			$params = $exec_params['params'];

			$res = SmartContractEngine::exec($transaction, $method, $height, $params, $err);
			if(!$res) {
				throw new Exception("Error calling method $method of smart contract: $err");
			}

			if($test) {
				$db->rollback();
			}

			return true;
		}, $error);
	}

	static function getAll() {
		global $db;
		$sql = "select * from smart_contracts";
		return $db->run($sql);
	}

	static function getById($id) {
		global $db;
		$sql = "select * from smart_contracts where address = :address";
		return $db->row($sql, [":address"=>$id]);
	}

	public static function reverse(Transaction $tx, &$error = null)
	{

		return try_catch(function () use ($tx, &$error) {

			global $db;

			$sql="delete from smart_contracts where address = :address";
			$res = $db->run($sql, [":address" => $tx->dst]);
			if($res === false) {
				$error = $db->errorInfo()[2];
				return false;
			}

			$sql="delete from smart_contract_state where sc_address = :address";
			$res = $db->run($sql, [":address" => $tx->dst]);
			if($res === false) {
				$error = $db->errorInfo()[2];
				return false;
			}

			return true;

		}, $error);

	}

	static function reverseState(Transaction $tx, $height, &$error = null) {
		return try_catch(function () use ($tx, $height, $error) {

			global $db;
			$sql="delete from smart_contract_state where sc_address = :address and height >= :height";
			$res = $db->run($sql, [":address" => $tx->dst, ":height"=>$height]);
			if($res === false) {
				$error = $db->errorInfo()[2];
				return false;
			}

			return true;
		}, $error);
	}

	static function compile($file, $phar_file, &$error = null)
	{
		return try_catch(function () use ($file, $phar_file) {
			if (!file_exists($file)) {
				throw new Exception("File or folder for deploy $file does not exists");
			}

			$cmd = "php --define phar.readonly=0 ".ROOT."/utils/sc_compile.php $file $phar_file 2>/dev/null";

			$output = shell_exec($cmd);

			if(file_exists($output)) {
				return true;
			} else {
				throw new Exception("Error compiling smart contract: $output");
			}

		}, $error);
	}


}
