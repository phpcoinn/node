<?php

class Transaction
{


	public $val;
	public $fee;
	public $dst;
	public $msg;
	public $type;
	public $publicKey;
	public $date;
	public $signature;
	public $height;
	public $peer;
	public $id;
	public $data;

	public $src;

	public $mempool = false;

	public function __construct($publicKey=null,$dst=null,$val=null,$type=null,$date = null,$msg = null, $fee=0)
	{
		$this->val = $val;
		$this->dst = $dst;
		$this->msg = $msg;
		$this->type = $type;
		$this->publicKey = $publicKey;
		$this->date = empty($date) ? time() : $date;
		$this->src = Account::getAddress($this->publicKey);
		$this->fee = $fee;
	}

	function calculateFee($block_height) {
		$fee = 0;
		if($this->type == TX_TYPE_SEND) {
			$fee_ratio = Blockchain::getFee($block_height);
			$fee = round($fee_ratio * $this->val, 8);
		} else if($this->type == TX_TYPE_SC_CREATE) {
			$fee = TX_SC_CREATE_FEE;
		} else if($this->type == TX_TYPE_SC_EXEC) {
			$fee = TX_SC_EXEC_FEE;
		} else if($this->type == TX_TYPE_SC_SEND) {
			$fee = TX_SC_EXEC_FEE;
		} else if($this->type == TX_TYPE_BURN) {
			$fee = 0;
		}
		return $fee;
	}

	function checkFee(&$err=null) {
		//TODO: error handling
		if ($this->fee < 0) {
			$err = "{$this->fee} - Fee below 0";
			return false;
		}

		if($this->mempool) {
			$fee = $this->calculateFee($this->height);
			if ($fee != $this->fee) {
				$err = "Invalid fee fee={$this->fee} calc=$fee";
				return false;
			}
		}
		return true;
	}


	// reverse and remove all transactions from a block
    public static function reverse($block, &$error = null)
    {
        global $db;

		try {
			$r = $db->run("SELECT * FROM transactions WHERE block=:block ORDER by `type` DESC", [":block" => $block['id']]);
			foreach ($r as $x) {
				$tx = Transaction::getFromDbRecord($x);
				_log("Reversing transaction {$tx->id}", 3);

				if(!empty($tx->dst)) {
					$dst_height = Transaction::getLastHeight($tx->dst, $block['height']);
				}
				if(!empty($tx->src)) {
					$src_height = Transaction::getLastHeight($tx->src, $block['height']);
				}

		        $type = $tx->type;
		        $res = true;
		        if($type == TX_TYPE_REWARD) {
			        $res = $res && Account::addBalance($tx->dst, floatval($tx->val)*(-1),$dst_height);
		        }
				if ($type == TX_TYPE_SEND) {
			        $res = $res && Account::addBalance($tx->dst, floatval($tx->val)*(-1),$dst_height);
			        $res = $res && Account::addBalance($tx->src, floatval($tx->val) + floatval($tx->fee),$src_height);
					$res = $res && $tx->add_mempool();
		        }

				if ($type == TX_TYPE_BURN) {
					$res = $res && Account::addBalance($tx->src, floatval($tx->val) + floatval($tx->fee),$src_height);
					$res = $res && $tx->add_mempool();
				}

		        if ($type == TX_TYPE_FEE) {
			        $res = $res && Account::addBalance($tx->dst, floatval($tx->val)*(-1),$dst_height);
		        }

				if ($type == TX_TYPE_MN_CREATE) {
					$res = $res && Account::addBalance($tx->dst, floatval($tx->val)*(-1),$dst_height);
					$res = $res && Account::addBalance($tx->src, floatval($tx->val),$src_height);
					$res = $res && $tx->add_mempool();
					if($res === false) {
						throw new Exception("Update balance for reverse transaction failed");
					}
					$publicKey = Account::publicKey($tx->dst);
					$res = Masternode::delete($publicKey);
					if(!$res) {
						throw new Exception("Error deleting masternode");
					}
				}

				if ($type == TX_TYPE_MN_REMOVE) {
					$res = $res && Account::addBalance($tx->dst, floatval($tx->val)*(-1),$dst_height);
					$res = $res && Account::addBalance($tx->src, floatval($tx->val),$src_height);
					$res = $res && $tx->add_mempool();
					if($res === false) {
						throw new Exception("Update balance for reverse transaction failed");
					}
					$height = Masternode::getMnCreateHeight($tx->publicKey);
					if(!$height) {
						throw new Exception("Can not find mn create tx height");
					}
					$res = Masternode::create($tx->publicKey, $height);
					if(!$res) {
						throw new Exception("Can not reverse create masternode");
					}
				}

				if ($type == TX_TYPE_SC_CREATE) {

					$res = Account::addBalance($tx->dst, floatval($tx->val)*(-1),$dst_height);
					$res = $res && Account::addBalance($tx->src, floatval($tx->val) + floatval($tx->fee),$src_height);
					$res = $res && $tx->add_mempool();

					$res = $res && SmartContract::reverse($tx, $err);
					if(!$res) {
						throw new Exception("Can not reverse create smart contract: $err");
					}
				}

				if ($type == TX_TYPE_SC_EXEC) {
					$res = Account::addBalance($tx->dst, floatval($tx->val)*(-1),$dst_height);
					$res = $res && Account::addBalance($tx->src, floatval($tx->val) + floatval($tx->fee),$src_height);
					$res = $res && $tx->add_mempool();

					$height = $tx->height;
					$res = $res && SmartContract::reverseState($tx, $height, $err);
					if(!$res) {
						throw new Exception("Can not reverse exec smart contract: $err");
					}
				}

				if ($type == TX_TYPE_SC_SEND) {
					$res = Account::addBalance($tx->dst, floatval($tx->val)*(-1), $dst_height);
					$res = $res && Account::addBalance($tx->src, floatval($tx->val) + floatval($tx->fee), $src_height);
					$res = $res && $tx->add_mempool();

					$height = $tx->height;
					$res = $res && SmartContract::reverseState($tx, $height, $err);
					if(!$res) {
						throw new Exception("Can not reverse exec smart contract: $err");
					}
				}

				if($res === false) {
					throw new Exception("Update balance for reverse transaction failed");
				}

				$res = $db->run("DELETE FROM transactions WHERE id=:id", [":id" => $tx->id]);
				if ($res != 1) {
					throw new Exception("Delete transaction failed");
				}
			}
			return true;
		} catch (Exception $e) {
			$err = $e->getMessage();
			_log($err);
			return false;
		}
        

    }

    // clears the mempool
    public static function clean_mempool()
    {
        global $db;
        $current = Block::current();
        $height = $current['height'];
        $limit = $height - 1000;
		Mempool::deleteToeight($limit);
    }

    public static function empty_mempool()
    {
        Mempool::empty_mempool();
    }

    public static function getFromDbRecord($x) {
	    $trans = new Transaction($x['public_key'],$x['dst'],$x['val'],intval($x['type']),intval($x['date']),$x['message']);
	    $trans->id = $x['id'];
	    $trans->src = $x['src'];
	    $trans->fee = $x['fee'];
	    $trans->signature = $x['signature'];
	    $trans->height = $x['height'];
	    $trans->peer = $x['peer'];
	    $trans->data = $x['data'];
	    return $trans;
    }

	public static function getFromArray($x) {
		$trans = new Transaction($x['public_key'],$x['dst'],floatval($x['val']),$x['type'],$x['date'],$x['message']);
		$trans->id = $x['id'];
		$trans->src = $x['src'];
		$trans->fee = floatval($x['fee']);
		$trans->signature = $x['signature'];
		$trans->data = @$x['data'];
		$trans->height = @$x['height'];
		return $trans;
	}

    public function toArray() {
	    $trans = [
		    "id"         => $this->id,
		    "dst"        => $this->dst,
		    "src"        => $this->src,
		    "val"        => num($this->val),
		    "fee"        => num($this->fee),
		    "signature"  => $this->signature,
		    "message"    => $this->msg,
		    "type"    => intval($this->type),
		    "date"       => intval($this->date),
		    "public_key" => $this->publicKey,
	    ];
		if(!empty($this->data)) {
			$trans['data']=$this->data;
		}
	    ksort($trans);
	    return $trans;
    }

    // returns X  transactions from mempool
    public static function mempool($max, $with_errors=false, $as_mine_data=true)
    {
        global $db;
        $current = Block::current();
        $height = $current['height'] + 1;
		if($as_mine_data) {
	        $r = Mempool::getTxs($height, $max);
		} else {
			$r = Mempool::getAll();
		}
        $transactions = [];
		$mncreate = [];
		$mnremove = [];
        if (count($r) > 0) {
            $i = 0;
            $balance = [];
            foreach ($r as $x) {

                $trans = self::getFromDbRecord($x);
	            $trans->mempool = true;

                if ($i >= $max) {
                    break;
                }

				$tx_error = false;
                if (empty($x['public_key'])) {
	                $tx_error = "Transaction has empty public_key";
					if(!$with_errors) {
                        continue;
					}
                }
                if (empty($x['src'])) {
	                $tx_error = "Transaction has empty src";
	                if(!$with_errors) {
		                continue;
	                }
                }
                if (!$trans->check($current['height'], false, $error)) {
	                $tx_error = "Transaction Check Failed: ".$error;
	                if(!$with_errors) {
		                continue;
	                }
                }

                $balance[$x['src']] += $x['val'] + $x['fee'];
                if ($db->single("SELECT COUNT(1) FROM transactions WHERE id=:id", [":id" => $x['id']]) > 0) {
	                $tx_error = "Duplicate transaction";
	                if(!$with_errors) {
		                continue;
	                }
                }

                $res = $db->single(
                    "SELECT COUNT(1) FROM accounts WHERE id=:id AND balance>=:balance",
                    [":id" => $x['src'], ":balance" => $balance[$x['src']]]
                );

                if ($res == 0) {
	                $tx_error = "Not enough funds in balance";
	                if(!$with_errors) {
		                continue;
	                }
                }

				$type=$trans->type;
				if($type == TX_TYPE_MN_CREATE) {
					//check same transaction in mempool
					$key=$trans->dst;
					if(isset($mncreate[$key])) {
						$tx_error = "Similar transaction already in mempool";
						if(!$with_errors) {
							continue;
						}
					}
					$mncreate[$key]=$key;

					$res = Masternode::checkCreateMasternodeTransaction($height, $trans, $error, false);
					if(!$res) {
						$tx_error = $error;
						if(!$with_errors) {
							continue;
						}
					}

				}

	            if($type==TX_TYPE_MN_REMOVE) {

		            //check same transaction in mempool
		            $key=$trans->publicKey;
		            if(isset($mnremove[$key])) {
			            $tx_error = "Similar transaction already in mempool";
			            if(!$with_errors) {
				            continue;
			            }
		            }
		            $mnremove[$key]=$key;

					$res = Masternode::checkRemoveMasternodeTransaction($height, $trans, $error, false);
					if(!$res) {
						$tx_error = $error;
						if(!$with_errors) {
							continue;
						}
					}
	            }

                $i++;
				if($tx_error) {
					_log("$x[id] - $tx_error");
				}
	            $tx_arr = $trans->toArray();
				if($tx_error && $with_errors) {
					$tx_arr['error']=$tx_error;
				}
				if(!$as_mine_data) {
					$tx_arr['height']=$x['height'];
					$tx_arr['peer']=$trans->peer;
				}
                $transactions[$x['id']] = $tx_arr;
            }
        }
        // always sort the array
	    if(!$as_mine_data) {
            ksort($transactions);
	    }
        return $transactions;
    }

	// add a new transaction to mempool and lock it with the current height
	public function add_mempool($peer = "")
	{
		global $db;

		$current = Block::current();
		$height = $current['height'];
		$bind = [
			":peer"      => $peer,
			":id"        => $this->id,
			"public_key" => $this->publicKey,
			":height"    => $height,
			":src"       => $this->src,
			":dst"       => empty($this->dst) ?  null : $this->dst,
			":val"       => $this->val,
			":fee"       => $this->fee,
			":signature" => $this->signature,
			":type"   => $this->type,
			":date"      => $this->date,
			":message"   => $this->msg,
			":data"   => $this->data,
		];


		$res = $db->run(
			"INSERT into mempool  
			    (peer, id, public_key, height, src, dst, val, fee, signature, type, message, `date`, data)
			    values (:peer, :id, :public_key, :height, :src, :dst, :val, :fee, :signature, :type, :message, :date, :data)",
			$bind
		);
		if($res === false) {
			return false;
		}
		return true;
	}

	public static function getTxStatByType($address, $type)
	{
		global $db;
		$sql="select sum(t.val) as total, count(t.id) as tx_cnt from transactions t where t.dst = :address and t.type = 0
            and t.message = :type
			and exists (select 1 from blocks b where b.$type = t.dst)";
		$res = $db->row($sql, [":address"=>$address, ":type"=>$type]);
		return $res;
	}

	public static function getByAddressType($address, $type, $dm)
	{
		global $db;

		if(is_array($dm)) {
			$page = $dm['page'];
			$limit = $dm['limit'];
			$offset = ($page-1)*$limit;
		} else {
			$limit = intval($dm);
			if ($limit > 100 || $limit < 1) {
				$limit = 100;
			}
		}

		$res = $db->run(
			"SELECT * FROM transactions t
				WHERE t.dst = :address
				  and t.message = :message
				and exists (select 1 from blocks b where b.$type = t.dst)
				ORDER by t.height DESC LIMIT :offset, :limit", [":address"=>$address, ":offset"=>$offset, ":limit"=>$limit, ":message"=>$type]
		);
		return $res;
	}

	public static function getRewardsStat($address, $type)
	{
		global $db;
		$sql="select min(t.date) as min_date, max(t.date) as max_date, sum(t.val) as total 
			from transactions t where t.dst = :address and t.type = 0
			and t.message = :type";
		$data = [];
		$row = $db->row($sql, [":address"=>$address, ":type"=>$type]);
		$data['total']['start']=$row['min_date'];
		$data['total']['start']=$row['max_date'];
		$data['total']['elapsed']=$row['max_date'] - $row['min_date'];
		$data['total']['days']=$data['total']['elapsed'] / 86400;
		$data['total']['daily']=$row['total'] / $data['total']['days'];
		$data['total']['weekly']=$data['total']['daily'] * 7;
		$data['total']['monthly']=$data['total']['daily'] * 30;
		$data['total']['yearly']=$data['total']['monthly'] * 12;
		return $data;
	}

	public function add($block, $height, &$error = null)
	{

		return try_catch(function () use ($block, $height, &$error) {
			$public_key = $this->publicKey;
			$address = Account::getAddress($public_key);
			$res = Account::checkAccount($address, $public_key, $block, $height);
			if ($res === false) {
				throw new Exception("Error checking account address");
			}
			//allow receive on unverified address
			if ($this->type == TX_TYPE_SEND || $this->type == TX_TYPE_SC_EXEC || $this->type == TX_TYPE_SC_SEND) {
				$res = Account::checkAccount($this->dst, "", $block, $height);
				if ($res === false) {
					throw new Exception("Error checking account address for send");
				}
			}

			$src = $this->type == TX_TYPE_REWARD || $this->type == TX_TYPE_FEE ? null : Account::getAddress($this->publicKey);

			$bind = [
				":id"         => $this->id,
				":public_key" => $this->publicKey,
				":height"     => $height,
				":block"      => $block,
				":dst"        => empty($this->dst) ? null : $this->dst,
				":val"        => $this->val,
				":fee"        => $this->fee,
				":signature"  => $this->signature,
				":type"    => $this->type,
				":date"       => $this->date,
				":message"    => $this->msg,
				":src"        => $src,
				":data"    => $this->data,
			];
			$res = Transaction::insert($bind);
			if ($res != 1) {
				throw new Exception("Can not insert transaction");
			}

			$type = $this->type;
			$res = true;
			if($type == TX_TYPE_REWARD && $this->val > 0) {
				$res = $res && Account::addBalance($this->dst, $this->val,$height);
			} else if ($type == TX_TYPE_SEND || $type == TX_TYPE_MN_CREATE) {
				$res = $res && Account::addBalance($this->src, ($this->val + $this->fee)*(-1),$height);
				$res = $res && Account::addBalance($this->dst, ($this->val),$height);
			} else if ($type == TX_TYPE_FEE) {
				$res = $res && Account::addBalance($this->dst, $this->val,$height);
			} else if ($type == TX_TYPE_SC_CREATE) {
				$res = $res && Account::addBalance($this->src, ($this->val + $this->fee)*(-1),$height);
				$res = $res && Account::addBalance($this->dst, ($this->val),$height);
			} else if ($type == TX_TYPE_SC_EXEC) {
				$res = $res && Account::addBalance($this->src, ($this->val + $this->fee)*(-1),$height);
				$res = $res && Account::addBalance($this->dst, ($this->val),$height);
			} else if ($type == TX_TYPE_SC_SEND) {
				$res = $res && Account::addBalance($this->src, ($this->val + $this->fee)*(-1),$height);
				$res = $res && Account::addBalance($this->dst, ($this->val),$height);
			} else if ($type == TX_TYPE_BURN) {
				$res = $res && Account::addBalance($this->src, ($this->val + $this->fee)*(-1),$height);
			}
			if($res === false) {
				throw new Exception("Error updating balance for transaction ".$this->id." type=$type");
			}

			if ($type == TX_TYPE_MN_CREATE) {
				$dstPublicKey = Account::publicKey($this->dst);
				$res = Masternode::create($dstPublicKey, $height);
				if(!$res) {
					throw new Exception("Can not create masternode");
				}
			}

			if ($type == TX_TYPE_MN_REMOVE) {
				$res = true;
				$res = $res && Account::addBalance($this->src, ($this->val + $this->fee)*(-1),$height);
				$res = $res && Account::addBalance($this->dst, ($this->val),$height);
				if($res === false) {
					throw new Exception("Error updating balance for transaction ".$this->id);
				}
				$mn = Masternode::get($this->publicKey);
				if(!$mn) {
					throw new Exception("Masternode with public key {$this->publicKey} does not exists");
				}
				$res = Masternode::delete($this->publicKey);
				if(!$res) {
					throw new Exception("Can not delete masternode with public key: ".$this->publicKey);
				}
			}

			if ($type == TX_TYPE_REWARD) {
				$res = Masternode::processRewardTx($this, $error);
				if(!$res) {
					throw new Exception("Can not process masternode reward:$error");
				}
			}

			if ($type == TX_TYPE_SC_CREATE) {
				$res = SmartContract::createSmartContract($this, $height, $error);
				if(!$res) {
					throw new Exception("Can not process create smart contract: $error");
				}
			}

			if ($type == TX_TYPE_SC_EXEC) {
				$res = SmartContract::execSmartContract($this, $height, $error);
				if(!$res) {
					throw new Exception("Can not process exec smart contract: $error");
				}
			}

			if ($type == TX_TYPE_SC_SEND) {
				$res = SmartContract::sendSmartContract($this, $height, $error);
				if(!$res) {
					throw new Exception("Can not process send smart contract: $error");
				}
			}

			Mempool::delete($this->id);
			return true;
		}, $error);


	}

    // hash the transaction's most important fields and create the transaction ID
	public function hash()
	{

		$base = $this->getSignatureBase();
		$base = $base ."-" . $this->signature;

		$hash = hash("sha256", $base);
		$hash = hex2coin($hash);
		_log("Transaction hash info=".$base." hash=$hash", 4);
		$this->id = $hash;
		return $hash;
	}

    // check the transaction for validity
    public function check($block_height = 0, $verify = false, &$error = null)
    {
        global $db, $_config;
        // if no specific block, use current
        if (empty($block_height)) {
	        $current = Block::current();
            $height = $current['height'];
        } else {
	        $height = $block_height;
        }
        $base = $this->getSignatureBase();

		$reward = Block::reward($height);
	    $phase = $reward['phase'];

		try {

			if ($this->val < 0) {
				throw new Exception("{$this->val} - Value < 0", 3);
			}

	        // the value must be >=0
	        if ($this->val <= 0 && in_array($phase, ["genesis","launch","mining"])) {
				if($this->type != TX_TYPE_SC_CREATE && $this->type != TX_TYPE_SC_EXEC) {
		            throw new Exception("Transaction type {$this->val} - Value <= 0", 3);
		        }
	        }

		    //genesis transaction
		    $mining_end_block = Block::getMnStartHeight();
		    if($this->publicKey==GENESIS_DATA['public_key'] && $this->type==TX_TYPE_SEND && $height <= $mining_end_block) {
			    throw new Exception("Genesis can not spend before locked height");
		    }

			// added fee does not match
			if (!$this->checkFee($err)) {
				throw new Exception("{$this->id} - Check fee failed: $err");
			}

			//check types
		    $type = $this->type;
			$allowedTypes = [TX_TYPE_REWARD, TX_TYPE_SEND];
			if(Masternode::allowedMasternodes($height)) {
				$allowedTypes[]=TX_TYPE_MN_CREATE;
				$allowedTypes[]=TX_TYPE_MN_REMOVE;
			}
			if($height >= FEE_START_HEIGHT) {
				$allowedTypes[]=TX_TYPE_FEE;
			}
			if($height >= SC_START_HEIGHT) {
				$allowedTypes[]=TX_TYPE_SC_CREATE;
				$allowedTypes[]=TX_TYPE_SC_EXEC;
				$allowedTypes[]=TX_TYPE_SC_SEND;
			}
			if($height >= TX_TYPE_BURN_START_HEIGHT) {
				$allowedTypes[]=TX_TYPE_BURN;
			}
			if(!in_array($type, $allowedTypes)) {
				$error = "Invalid transaction type $type for height $height";
				_log($error);
				return false;
			}

	        if($this->type==TX_TYPE_REWARD) {
	            $res = $this->checkRewards($height, $verify,$err);
	            if(!$res) {
		            if($height > UPDATE_2_BLOCK_CHECK_IMPROVED) {
			            throw new Exception("Transaction rewards check failed: $err");
		            }
		        }
	        }

			if(empty($this->publicKey)) {
				throw new Exception("Empty public key");
			}

			if (!Account::validKey($this->publicKey)) {
				throw new Exception("Invalid public key");
			}

			if(!$verify) {
				if ($_config['use_official_blacklist']!==false) {
					if (Blacklist::checkPublicKey($this->publicKey)) {
						throw new Exception("Blacklisted account");
					}
				}
			}

			if (!Account::validKey($this->signature)) {
				throw new Exception("Invalid signature");
			}

			if (strlen($this->msg) > 255) {
				throw new Exception("The message must be less than 255 chars");
			}

			if ($this->val < 0) {
				throw new Exception("Invalid value");
			}


            if ($this->type==TX_TYPE_SEND || $this->type == TX_TYPE_MN_CREATE || $this->type == TX_TYPE_MN_REMOVE) {
	            // invalid destination address
	            if (!Account::valid($this->dst)) {
		            throw new Exception("{$this->id} - Invalid destination address");
	            }
	            $src = Account::getAddress($this->publicKey);
	            if($src==$this->dst) {
		            throw new Exception("{$this->id} - Invalid source address");
	            }
	        }

			if($this->type == TX_TYPE_BURN) {
				if(!empty($this->dst)) {
					throw new Exception("{$this->id} - Destination address must be empty");
				}
			}

	        // public key must be at least 15 chars / probably should be replaced with the validator function
	        if (strlen($this->publicKey) < 15) {
		        throw new Exception("{$this->id} - Invalid public key size");
	        }
	        // no transactions before the genesis
	        if ($this->date < GENESIS_TIME) {
		        throw new Exception("{$this->id} - Date before genesis");
	        }
	        // no future transactions
	        if ($this->date > time() + 86400) {
		        throw new Exception("{$this->id} - Date in the future");
	        }
	        $thisId = $this->id;
	        $id = $this->hash();
	        // the hash does not match our regenerated hash
	        if ($thisId != $id) {
		        throw new Exception("{$this->id} - $id - Invalid hash");
	        }

	        //verify the ecdsa signature
	        if (!Account::checkSignature($base, $this->signature, $this->publicKey, $height)) {
		        throw new Exception("{$this->id} - Invalid signature - $base");
	        }

			if($this->type==TX_TYPE_SEND || $this->type == TX_TYPE_BURN) {
				$res = Masternode::checkIsSendFromMasternode($height, $this, $error, $verify);
				if(!$res) {
					throw new Exception("Invalid transaction for send: $error");
				}
			}

			if($this->type==TX_TYPE_MN_CREATE) {
				$res = Masternode::checkCreateMasternodeTransaction($height, $this, $error, $verify);
				if(!$res) {
					throw new Exception("Invalid transaction for masternde create: $error");
				}
			}

			if($this->type==TX_TYPE_MN_REMOVE) {
				$res = Masternode::checkRemoveMasternodeTransaction($height, $this, $error, $verify);
				if(!$res) {
					throw new Exception("Invalid transaction for masternde remove: $error");
				}
			}

			if($this->type==TX_TYPE_SC_CREATE) {
				$res = SmartContract::checkCreateSmartContractTransaction($height, $this, $error, $verify);
				if(!$res) {
					throw new Exception("Invalid transaction for create smart contract: $error");
				}
			}

			if($this->type==TX_TYPE_SC_EXEC) {
				$res = SmartContract::checkExecSmartContractTransaction($height, $this, $error, $verify);
				if(!$res) {
					throw new Exception("Invalid transaction for exec smart contract: $error");
				}
			}

			if($this->type==TX_TYPE_SC_SEND) {
				$res = SmartContract::checkSendSmartContractTransaction($height, $this, $error, $verify);
				if(!$res) {
					throw new Exception("Invalid transaction for send smart contract: $error");
				}
			}

			if($verify) {
				if ($this->type == TX_TYPE_REWARD) {
					$res = Masternode::checkTx($this, $height, $error);
					if (!$res) {
						throw new Exception("Invalid transaction for masternde reward: $error");
					}
				}
			}

		} catch (Exception $e) {
			$error = $e->getMessage();
			_log("Transaction {$this->id} check failed: ".$error);
			return false;
		}

        return true;
    }


    public function verify($height = 0, &$error = null)
    {
		return $this->check($height, true, $error);
    }

    function checkRewards($height, $verify=false, &$error = null) {
		$reward = Block::reward($height);
		$msg = $this->msg;

		try {

			if(empty($msg) && $height > UPDATE_2_BLOCK_CHECK_IMPROVED) {
				throw new Exception("Reward transaction message missing");
			}
			if(!in_array($msg, ["nodeminer", "miner", "generator","masternode","stake"]) &&
				!(substr($msg, 0, strlen("pool|")) == "pool|" && $height < UPDATE_4_NO_POOL_MINING)
				&& $height > UPDATE_2_BLOCK_CHECK_IMPROVED) {
				throw new Exception("Reward transaction invalid message: $msg");
			}
			$miner = $reward['miner'];
			$generator = $reward['generator'];
			$masternode = $reward['masternode'];
			if($msg == "nodeminer") {
				$val_check = num($miner + $generator);
			} else if ($msg == "miner") {
				$val_check = num($miner);
			} else if ($msg == "generator") {
				$val_check = num($generator);
			} else if ($msg == "masternode") {
				$val_check = num($masternode);
			} else if (substr($msg, 0, strlen("pool|")) == "pool|" && $height < UPDATE_4_NO_POOL_MINING) {
				$val_check = num($miner);
			} else if ($msg == "stake") {
				if($height < STAKING_START_HEIGHT) {
					throw new Exception("Invalid staking transaction before start height " . STAKING_START_HEIGHT);
				}
				$val_check = num($reward['staker']);
			}
			if(empty($val_check) && $height > UPDATE_2_BLOCK_CHECK_IMPROVED) {
				throw new Exception("Reward transaction no value id=".$this->id, 5);
			}
			if(!empty($val_check) && num($this->val) != $val_check) {
				throw new Exception("Reward transaction id=".$this->id." not valid: val=".$this->val." val_check=$val_check");
			}
			if (substr($msg, 0, strlen("pool|")) == "pool|" && $height < UPDATE_4_NO_POOL_MINING) {
				$arr = explode("|", $msg);
				$poolMinerAddress=$arr[1];
				$poolMinerAddressSignature=$arr[2];
				$poolMinerPublicKey = Account::publicKey($poolMinerAddress);
				if(empty($poolMinerPublicKey)) {
					throw new Exception("Reward transaction not valid: not found public key for address $poolMinerAddress");
				}
				$res = Account::checkSignature($poolMinerAddress, $poolMinerAddressSignature, $poolMinerPublicKey, $height);
				if(!$res) {
					throw new Exception("Reward transaction not valid: address signature failed poolMinerAddress=$poolMinerAddress poolMinerAddressSignature=$poolMinerAddressSignature");
				}
			}

			if($msg == "stake" && $height >= STAKING_START_HEIGHT) {

				if($height == Block::getHeight() + 1) {
					$winner = Account::getStakeWinner($height);
					if(!empty($winner) && $winner != $this->dst) {
						throw new Exception("Staking winner not found");
					} else if (empty($winner) && $this->dst != Account::getAddress($this->publicKey)) {
						//temporary disabled
						//throw new Exception("Staking winner not valid - must be generator");
					}
				} else {
					$last_height = Account::getLastTxHeight($this->dst, $height);
					if(!$last_height) {
						throw new Exception("Staking winner check failed: Can not found last height for address ".$this->dst);
					}

					$block = Block::get($height);
					$winner_is_generator = $block['generator']==$this->dst;

					$maturity = $height - $last_height;
					_log("Check stake address=".$this->dst. " height=$height last_height=$last_height verify=$verify winner_is_generator=$winner_is_generator maturity=$maturity", 5);
					if($maturity < STAKING_COIN_MATURITY && !$winner_is_generator) {
						throw new Exception("Staking winner check failed: Staking maturity not valid ".$maturity);
					}

					$balance = Account::getBalanceAtHeight($this->dst, $height);
					if(floatval($balance) < STAKING_MIN_BALANCE && !$winner_is_generator) {
						throw new Exception("Staking winner check failed: Staking balance not valid ".$balance);
					}
				}
			}

			return true;

		} catch (Exception $e) {
			$error = $e->getMessage();
			_log($error);
			return false;
		}

    }

	public function sign($private_key)
	{
		$base = $this->getSignatureBase();
		$signature = ec_sign($base, $private_key);
		$this->signature = $signature;
		return $signature;
	}

    public function getSignatureBase() {
    	$val = $this->val;
    	$fee = $this->fee;
    	$date = $this->date;
    	if(is_numeric($val)) {
    		$val = num($val);
	    }
    	if(is_numeric($fee)) {
		    $fee = num($fee);
	    }
    	if(!is_numeric($date)) {
		    $date = intval($date);
	    }
    	$parts = [];
    	$parts[]=$val;
    	$parts[]=$fee;
    	$parts[]=empty($this->dst) ? "" : $this->dst;
    	$parts[]=$this->msg;
    	$parts[]=$this->type;
    	$parts[]=$this->publicKey;
    	$parts[]=$date;
	    $base = implode("-", $parts);
	    return $base;
    }

//	private function get_check_height() {
//		if(empty($this->height) || $this->mempool) {
//			$height = Block::getHeight()+1;
//		} else {
//			$height = $this->height;
//		}
//		return $height;
//	}

	public static function export($id)
	{
		global $db;
		$r = Mempool::getById($id);
		if(empty($r)) {
			return null;
		}
		$r['date']=intval($r['date']);
		return $r;
	}

    // get the transaction data as array
    public static function get_transaction($id)
    {
        global $db;
        $current = Block::current();

        $x = $db->row("SELECT * FROM transactions WHERE id=:id", [":id" => $id]);

        if (!$x) {
            return false;
        }
	    $trans = self::process_tx($x, $current['height']);
        return $trans;
    }

    private static function process_tx($x, $height) {
	    $trans = [
		    "block"      => $x['block'],
		    "height"     => $x['height'],
		    "id"         => $x['id'],
		    "dst"        => $x['dst'],
		    "val"        => $x['val'],
		    "fee"        => $x['fee'],
		    "signature"  => $x['signature'],
		    "message"    => $x['message'],
		    "type"    => $x['type'],
		    "date"       => $x['date'],
		    "public_key" => $x['public_key'],
		    "data" => $x['data'],
	    ];
	    $trans['confirmations'] = $height - $x['height'];

	    if ($x['type'] == TX_TYPE_REWARD) {
		    $trans['type_label'] = "mining";
	    } elseif ($x['type'] == TX_TYPE_SEND) {
		    $trans['type_label'] = "transfer";
	    } elseif ($x['type'] == TX_TYPE_BURN) {
		    $trans['type_label'] = "burn";
	    } elseif ($x['type'] == TX_TYPE_MN_CREATE) {
		    $trans['type_label'] = "masternode create";
	    } elseif ($x['type'] == TX_TYPE_MN_REMOVE) {
		    $trans['type_label'] = "masternode remove";
	    } elseif ($x['type'] == TX_TYPE_FEE) {
		    $trans['type_label'] = "fee";
	    } else {
		    $trans['type_label'] = "other";
	    }
	    return $trans;
    }

    // return the transactions for a specific block id or height
    public static function get_transactions($height = "", $id = "")
    {
        global $db;
        $current = Block::current();
        $height = san($height);
        $id = san($id);
        if (empty($id) && empty($height)) {
            return false;
        }
        if (!empty($id)) {
            $r = $db->run("SELECT * FROM transactions WHERE block=:id", [":id" => $id]);
        } else {
            $r = $db->run("SELECT * FROM transactions WHERE height=:height", [":height" => $height]);
        }
        $res = [];
        foreach ($r as $x) {
	        $trans = self::process_tx($x, $current['height']);
            ksort($trans);
            $res[] = $trans;
        }
        return $res;
    }

    // get a specific mempool transaction as array
    public static function get_mempool_transaction($id)
    {
        global $db;
		$x = Mempool::getById($id);
        if (!$x) {
            return false;
        }
        $trans = [
            "block"      => $x['block'],
            "height"     => $x['height'],
            "id"         => $x['id'],
            "dst"        => $x['dst'],
            "val"        => $x['val'],
            "fee"        => $x['fee'],
            "signature"  => $x['signature'],
            "message"    => $x['message'],
            "type"    => $x['type'],
            "date"       => $x['date'],
            "public_key" => $x['public_key'],
        ];
        $trans['src'] = $x['src'];
		if(!empty($x['data'])) {
			$trans['data']=$x['data'];
		}

        $trans['type_label'] = "mempool";
        $trans['confirmations'] = -1;
        ksort($trans);
        return $trans;
    }

    static function getRewardTransaction($dst,$date,$public_key,$private_key,$reward, $msg) {
	    $transaction = new Transaction($public_key,$dst,$reward,TX_TYPE_REWARD,$date,$msg);
	    $transaction->sign($private_key);
	    $transaction->hash();
	    return $transaction->toArray();
    }

	static function getStakeRewardTx($height,$generator,$public_key,$private_key,$reward,$date) {
		$winner = Account::getStakeWinner($height);
		if(!$winner) {
			$winner = $generator;
		}
		$transaction = new Transaction($public_key,$winner,$reward,TX_TYPE_REWARD,$date,"stake");
		$transaction->sign($private_key);
		$transaction->hash();
		return $transaction->toArray();
	}

    static function getForBlock($height) {
		return Transaction::get_transactions($height, "");
    }

	static function getById($id) {
		global $db;
		$x = $db->row("SELECT * FROM transactions WHERE id=:id", [":id" => $id]);
		if($x) {
			return Transaction::getFromDbRecord($x);
		} else {
			return null;
		}
	}


	static function getMempoolById($id) {
		return Transaction::get_mempool_transaction($id);
	}


    function addToMemPool(&$error) {
    	global $db;
		$this->mempool = true;
	    $hash = $this->hash();

	    $max_txs = Block::max_transactions();
	    $mempool_size = Transaction::getMempoolCount();

		$db->beginTransaction();

		try {

			if ($this->date < time() - (3600 * 24 * 48)) {
				throw new Exception("The date is too old");
			}
			if ($this->date > time() + 86400) {
				throw new Exception("Invalid Date");
			}

		    if($mempool_size + 1 > $max_txs) {
			    throw new Exception("Not added transaction to mempool because is full: max_txs=$max_txs mempool_size=$mempool_size");
		    }
		    if (!$this->check(0, false, $err)) {
			    throw new Exception("Transaction check failed. Error: $err");
		    }
			$res = Mempool::existsTx($hash);
		    if ($res != 0) {
			    throw new Exception("The transaction $hash is already in mempool");
		    }

		    $res = $db->single("SELECT COUNT(1) FROM transactions WHERE id=:id", [":id" => $hash]);
		    if ($res != 0) {
			    throw new Exception("The $hash transaction is already in a block");
		    }

		    $balance = $db->single("SELECT balance FROM accounts WHERE id=:id", [":id" => $this->src]);
		    if (floatval($balance) < $this->val + $this->fee) {
			    throw new Exception("Not enough funds, expected=".($this->val + $this->fee)  . " balance=$balance");
		    }

			$memspent = Mempool::getSourceMempoolBalance($this->src);
		    if (floatval($balance) - floatval($memspent) < $this->val + $this->fee) {
			    throw new Exception("Not enough funds (mempool) expected=".($this->val + $this->fee). " balance=$balance mempool=$memspent");
		    }

			if($this->type == TX_TYPE_REWARD) {
				throw new Exception("Not allowed type in mempool");
			}
			if($this->type == TX_TYPE_FEE) {
				throw new Exception("Not allowed type in mempool");
			}

			if($this->type == TX_TYPE_MN_CREATE) {
				$cnt = Mempool::getByDstAndType($this->dst, $this->type);
				if($cnt > 0) {
					throw new Exception("Similar transaction already in mempool: create masternode ".$this->dst);
				}
			}

		    if($this->type == TX_TYPE_MN_REMOVE) {
			    $cnt = Mempool::getBySrcAndType($this->src, $this->type);
			    if($cnt > 0) {
				    throw new Exception("Similar transaction already in mempool: remove masternode ".$this->src);
			    }
		    }

			if($this->type == TX_TYPE_SC_CREATE) {
				$cnt = Mempool::getByDstAndType($this->dst, $this->type);
				if($cnt > 0) {
					throw new Exception("Similar transaction already in mempool: smart-contract-create ".$this->dst);
				}
				$res = SmartContract::createSmartContract($this, Block::getHeight()+1, $error, true);
				if(!$res) {
					throw new Exception("Create smart contract transaction failed: ".$error);
				}
			}

			if($this->type == TX_TYPE_SC_EXEC) {
				$res = SmartContract::execSmartContract($this, Block::getHeight()+1, $error, true);
				if(!$res) {
					throw new Exception("Execute smart contract transaction failed: ".$error);
				}
			}

			if($this->type == TX_TYPE_SC_SEND) {
				$res = SmartContract::sendSmartContract($this, Block::getHeight()+1, $error, true);
				if(!$res) {
					throw new Exception("Execute smart contract transaction failed: ".$error);
				}
			}

			if($this->type == TX_TYPE_SEND || $this->type == TX_TYPE_BURN) {
				Masternode::checkSend($this);
			}

			$res = $this->add_mempool("local");
			if(!$res) {
				throw new Exception("Error adding tansaction to mempool");
			}

			$db->commit();

			$hashp=escapeshellarg(san($hash));
			Propagate::transactionToAll($hashp);
			return $hash;

		} catch (Exception $e) {
			$error = $e->getMessage();
			$db->rollBack();
			_log($error);
			return false;
		}


    }

    static function getCount() {
    	global $db;
    	$sql="select count(*) as cnt from transactions";
		$row = $db->row($sql);
		return $row['cnt'];
    }

    static function getMempoolCount() {
		return Mempool::getSize();
    }

    static function getByAddress($address, $limit, $offset, $filter = null) {
	    $transactions = Account::getMempoolTransactions($address);
	    $transactions = array_merge($transactions, Account::getTransactions($address, $limit, $offset, $filter));
	    return $transactions;
    }

	static function getWalletTransactions($address, $dm) {
		return [
			'mempool'=>Account::getMempoolTransactions($address),
			'completed'=>Account::getTransactions($address, $dm)
		];
	}

    static function insert($bind) {
    	global $db;
	    $res = $db->run(
		    "INSERT into transactions 
    			(id, public_key, block,  height, dst, val, fee, signature, type, message, `date`, src, data)
    			values (:id, :public_key, :block, :height, :dst, :val, :fee, :signature, :type, :message, :date, :src, :data)
    			",
		    $bind
	    );
	    return $res;
    }

	static function getAddressStat($address) {
		global $db;
		$res = $db->row(
			"select sum(if(t.src=a.id, t.val + t.fee, 0)) as total_sent,
			       sum(if(t.dst = a.id , t.val, 0)) as total_received,
			       sum(if(t.src= a.id, 1, 0)) as count_sent,
			       sum(if(t.dst = a.id , 1, 0)) as count_received
				from accounts a
				left join transactions t on (a.id = t.src or a.id = t.dst)
				where a.id = :address;",
			[":address" => $address]
		);
		return $res;
	}

	static function processFee(&$transactions, $public_key, $private_key, $miner, $date, $height=null) {
		$fee = 0;
		foreach($transactions as $tx_id => $transaction) {
			$tx_fee = floatval($transaction['fee']);
			if(!empty($tx_fee)) {
				$fee += $tx_fee;
			}
		}
		$fee = round($fee, 8);
		if($fee > 0) {
			$tx = new Transaction($public_key,$miner,$fee,TX_TYPE_FEE,$date,"fee");
			$tx->sign($private_key);
			$hash = $tx->hash();
			$transactions[$hash] = $tx->toArray();
		}
	}

	static function typeLabel($type) {
		switch ($type) {
			case TX_TYPE_REWARD:
				return "Reward";
			case TX_TYPE_SEND:
				return "Transfer";
			case TX_TYPE_BURN:
				return "Burn";
			case TX_TYPE_MN_CREATE:
				return "Create masternode";
			case TX_TYPE_MN_REMOVE:
				return "Remove masternode";
			case TX_TYPE_FEE:
				return "Fee";
			case TX_TYPE_SC_CREATE:
				return "Create smart contract";
			case TX_TYPE_SC_EXEC:
				return "Execute smart contract";
			case TX_TYPE_SC_SEND:
				return "Send smart contract";
		}
	}

	public static function getSmartContractCreateTransaction($scAddress)
	{
		global $db;
		$sql="select * from transactions t where t.dst = :dst and t.type = :type";
		$row = $db->row($sql, [":dst"=>$scAddress, ":type"=>TX_TYPE_SC_CREATE]);
		return $row;
	}


	public static function getBurnedAmount() {
		global $db;
		$sql = "select sum(t.val) as sum from transactions t where t.type = :type";
		$res = $db->single($sql, [":type"=>TX_TYPE_BURN]);
		return $res;
	}

	static function getLastHeight($address, $height) {
		global $db;
		$sql = "select max(t.height) from transactions t
			where (t.src = :src or t.dst = :dst)
			and t.height < :height";
		return $db->single($sql, [":src"=>$address, ":dst"=>$address, ":height"=>$height]);
	}
}
