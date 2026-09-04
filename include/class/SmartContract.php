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
			if($fee != Blockchain::getSmartContractCreateFee($height)) {
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
			if($fee != Blockchain::getSmartContractExecFee($height)) {
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
			if($fee != Blockchain::getSmartContractExecFee($height)) {
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
            // Sort by date with hash as tie-breaker for deterministic ordering
            // When dates are equal (same second), hash determines order (cannot be manipulated)
            usort($transactions, function($a, $b) {
                $date1=$a->date;
                $date2=$b->date;
                if($date1 == $date2) {
                    // Hash tie-breaker ensures deterministic ordering when dates are equal
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
            return SmartContractEngine::$smartContracts[$id];
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
            $res = self::reverseNativeTransfers($height, $error);
            if($res === false) {
                return false;
            }

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

    static function ensureTransfersTable()
    {
        global $db;
        $db->exec("CREATE TABLE IF NOT EXISTS `smart_contract_transfers` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `height` int(11) NOT NULL,
            `sc_address` varchar(128) NOT NULL,
            `to_address` varchar(128) NOT NULL,
            `amount` decimal(20,8) NOT NULL,
            `seq` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `smart_contract_transfers_height_index` (`height`),
            KEY `smart_contract_transfers_sc_height_index` (`sc_address`,`height`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    static function transfersTableExists()
    {
        global $db;
        $res = $db->single("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'smart_contract_transfers'");
        return !empty($res);
    }

    /**
     * Move native PHP from a contract after a successful sandbox run.
     * Skips persisting on mempool/mining dry-runs ($test) and virtual mode.
     */
    static function applyNativeTransfers($sc_address, $transfers, $height, $test, $virtual = false, $transactions = [])
    {
        if (empty($transfers) || $virtual) {
            return true;
        }
        if (!is_array($transfers)) {
            throw new Exception("Invalid smart contract transfers");
        }
        $total = "0";
        $normalized = [];
        foreach ($transfers as $index => $transfer) {
            $from = $transfer['from'] ?? null;
            $to = $transfer['to'] ?? null;
            $amount = bcadd((string)($transfer['amount'] ?? "0"), "0", 8);
            if ($from !== $sc_address) {
                throw new Exception("Invalid smart contract transfer source");
            }
            if (!Account::valid($to)) {
                throw new Exception("Invalid smart contract transfer destination");
            }
            if (bccomp($amount, "0", 8) <= 0) {
                throw new Exception("Invalid smart contract transfer amount");
            }
            $total = bcadd($total, $amount, 8);
            $normalized[] = [
                "from" => $from,
                "to" => $to,
                "amount" => $amount,
                "seq" => $index,
            ];
        }
        $available = SmartContractEngine::contractNativeBalance($sc_address, $transactions, $test, $virtual);
        if (bccomp($available, $total, 8) < 0) {
            throw new Exception("Smart contract $sc_address has insufficient balance for native transfers");
        }
        if ($test) {
            return true;
        }
        if (!self::transfersTableExists()) {
            throw new Exception("smart_contract_transfers table is missing; restart the node to create it");
        }

        $block = Block::get($height);
        $blockId = $block['id'] ?? null;
        if (empty($blockId)) {
            throw new Exception("Cannot apply smart contract transfers without block $height");
        }
        global $db;
        foreach ($normalized as $transfer) {
            $res = Account::checkAccount($transfer['to'], "", $blockId, $height);
            if ($res === false) {
                throw new Exception("Cannot create account for smart contract transfer");
            }
            $res = Account::addBalance($sc_address, floatval($transfer['amount']) * -1, $height);
            $res = $res && Account::addBalance($transfer['to'], floatval($transfer['amount']), $height);
            if ($res === false) {
                throw new Exception("Failed to apply smart contract native transfer");
            }
            $ins = $db->run(
                "insert into smart_contract_transfers (height, sc_address, to_address, amount, seq) values (:height, :sc, :to, :amount, :seq)",
                [
                    ":height" => $height,
                    ":sc" => $sc_address,
                    ":to" => $transfer['to'],
                    ":amount" => $transfer['amount'],
                    ":seq" => $transfer['seq'],
                ]
            );
            if ($ins === false) {
                throw new Exception("Failed to record smart contract native transfer");
            }
        }
        return true;
    }

    static function reverseNativeTransfers($height, &$error = null)
    {
        global $db;
        try {
            if (!self::transfersTableExists()) {
                return true;
            }
            $rows = $db->run(
                "select * from smart_contract_transfers where height >= :height order by id desc",
                [":height" => $height]
            );
            if ($rows === false) {
                $error = $db->errorInfo()[2];
                return false;
            }
            foreach ($rows as $row) {
                $amount = floatval($row['amount']);
                $res = Account::addBalance($row['sc_address'], $amount, $row['height']);
                $res = $res && Account::addBalance($row['to_address'], $amount * -1, $row['height']);
                if ($res === false) {
                    $error = "Failed to reverse smart contract native transfer";
                    return false;
                }
            }
            $del = $db->run("delete from smart_contract_transfers where height >= :height", [":height" => $height]);
            if ($del === false) {
                $error = $db->errorInfo()[2];
                return false;
            }
            return true;
        } catch (Exception $e) {
            $error = $e->getMessage();
            return false;
        }
    }

	static function compile($address, $file, $phar_file, &$error = null)
	{
		return try_catch(function () use ($file, $phar_file, $address) {
			if (!file_exists($file)) {
				throw new Exception("File or folder for deploy $file does not exists");
			}

            $debug_str="-dxdebug.start_with_request=1";
            $debug_str="";
			$cmd = "php $debug_str --define phar.readonly=0 ".ROOT."/utils/sc_compile.php $address $file $phar_file  2>/dev/null";
			$output = shell_exec($cmd);

			if(@file_exists($output)) {
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

    static function getTokenCount() {
        global $db;
        $sql = "select count(*) from smart_contracts
                where json_unquote(json_extract(metadata,'$.class')) = 'ERC-20'";
        $n = $db->single($sql);
        return $n === false ? 0 : intval($n);
    }

}
