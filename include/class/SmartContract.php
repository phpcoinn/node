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


			$fee = $transaction->fee;
			if($fee != TX_SC_CREATE_FEE) {
				throw new Exception("Invalid fee for transaction");
			}

			$data_encoded = $transaction->data;
			$sc_signature = $transaction->msg;
            $data = json_decode(base64_decode($data_encoded), true);
//			_log("Check SC signature data=$data_encoded signature=$sc_signature pk=".$transaction->publicKey, 3);
			$res = ec_verify($data_encoded, $sc_signature, $transaction->publicKey);
			if(!$res) {
				throw new Exception("Invalid signature for smart contract");
			}

            if(floatval($data['amount'])!=$transaction->val) {
                throw new Exception("Invalid transaction amount");
            }

            if(empty($data['interface'])) {
                throw new Exception("Missing smart contract interface");
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

	public static function checkSendSmartContractTransaction($height, Transaction $transaction, &$error, $verify) {
		return try_catch(function () use ($height, $transaction, $error) {
			if(!($height >= SC_START_HEIGHT)) {
				throw new Exception("Not allowed transaction type {$transaction->type} for height $height");
			}
			$dst = $transaction->dst;
			$src = $transaction->src;
			$smartContract = self::getSmartContract($src);
			if(!$smartContract) {
				throw new Exception("Smart contract with address $src does not exists");
			}
			$fee = $transaction->fee;
			if($fee != TX_SC_EXEC_FEE) {
				throw new Exception("Invalid fee for transaction");
			}
			return true;
		}, $error);
	}


	public static function processSmartContractTx(Transaction $transaction,$height, &$error = null, &$state_updates=null) {
		return try_catch(function () use ($error, $transaction,$height, &$state_updates) {
			$message = $transaction->msg;
			$type = $transaction->type;

            if($type == TX_TYPE_SC_EXEC || $type == TX_TYPE_SC_CREATE) {
                $sc_address = $transaction->dst;
            } else {
                $sc_address = $transaction->src;
            }

            if(in_array($sc_address, BLACKLISTED_SMART_CONTRACTS)) {
                throw new Exception("Calling smart contract $sc_address is blocked");
            }

            $mempool_txs = Transaction::mempool(Block::max_transactions(), false);
            $transactions = [];
            foreach ($mempool_txs as $mempool_tx) {
                $mempool_tx = Transaction::getFromArray($mempool_tx);
                if($mempool_tx->type === TX_TYPE_SC_CREATE && $mempool_tx->dst === $sc_address) {
                    $transactions[$mempool_tx->id]=$mempool_tx;
                } else if ( $mempool_tx->type === TX_TYPE_SC_EXEC && $mempool_tx->dst === $sc_address) {
                    $transactions[$mempool_tx->id]=$mempool_tx;
                } else if ($mempool_tx->type === TX_TYPE_SC_SEND && $mempool_tx->src == $sc_address) {
                    $transactions[$mempool_tx->id]=$mempool_tx;
                }
            }
            $transactions[$transaction->id]=$transaction;

            self::sortScTransactions($transactions, $height);

            $hash = SmartContractEngine::process($sc_address, $transactions, $height, true, $err, $state_updates);
            if(!$hash) {
                $exec_params = json_decode(base64_decode($message), true);
                $method = @$exec_params['method'];
                throw new Exception("Error calling method $method of smart contract: ".$err);
            }
            return $hash;

		}, $error);
	}

    static function sortScTransactions(&$transactions, $height) {
        if($height < UPDATE_16_SC_TXS_SORT) {
            ksort($transactions);
        } else {
            usort($transactions, function($a, $b) {
                $date1=$a->date;
                $date2=$b->date;
                if($date1 == $date2) {
                    return strcmp($a->id, $b->id);
                }
                return $date1 - $date2;
            });
        }
    }

    public static function process($smart_contracts, $height, $test, &$error = null, &$state_updates=null) {
        return try_catch(function () use ($smart_contracts, $height, $test, &$state_updates) {
            $schashes = [];
            foreach ($smart_contracts as $sc_address => $txs) {
                self::sortScTransactions($txs, $height);
                $schash = SmartContractEngine::process($sc_address, $txs, $height, $test, $error, $state_updates);
                if(!$schash) {
                    throw new Exception("Error processing smart contract $sc_address transactions: $error");
                }
                $schashes[$sc_address]=$schash;
            }
            ksort($schashes);
            $schash=hash("sha256", json_encode($schashes));
            return $schash;
        }, $error);
    }

	static function getAll() {
		global $db;
		$sql = "select * from smart_contracts order by height desc";
		return $db->run($sql);
	}

	static function getById($id, $virtual = false) {
        if($virtual) {
            return SmartContractEngine::$smartContract;
        }
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

	static function cleanState($height, &$error = null) {
		return try_catch(function () use ($height, &$error) {

			global $db;
			$sql="delete from smart_contract_state where height >= :height";
			$res = $db->run($sql, [":height"=>$height]);
			if($res === false) {
				$error = $db->errorInfo()[2];
				return false;
			}

            $sql="delete from smart_contracts where height >= :height";
            $res = $db->run($sql, [":height"=>$height]);
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

    static function getDeployedSmartContracts($address) {
        global $db;
        $sql = "select s.* from smart_contracts s
                where exists (select 1 from transactions t where t.src = :address and t.type = :sccreate)";
        return $db->run($sql,[":address"=>$address, ":sccreate"=>TX_TYPE_SC_CREATE]);
    }

    static function getState($address) {
        global $db;
        $state = [];
        $sql="select ss.variable, ss.var_key, ss.var_value
            from (select s.sc_address, s.variable, ifnull(s.var_key, 'null') as var_key, max(s.height) as height
                  from smart_contract_state s
                  where s.sc_address = :address
                  group by s.variable, s.var_key, s.sc_address) as last_vars
                     join smart_contract_state ss on (ss.sc_address = last_vars.sc_address and ss.variable = last_vars.variable
                and ifnull(ss.var_key, 'null') = last_vars.var_key and ss.height = last_vars.height);
            ";
        $rows = $db->run($sql, [":address"=> $address]);
        foreach ($rows as $row) {
            if($row['var_key']!==null) {
                $state[$row['variable']][$row['var_key']]=$row['var_value'];
            } else {
                $state[$row['variable']]=$row['var_value'];
            }
        }
        return $state;
    }

    static function getCount() {
        global $db;
        $sql="select count(*) from smart_contracts";
        return $db->single($sql);
    }

}
