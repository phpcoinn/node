<?php

class Account
{

	// checks the ecdsa secp256k1 signature for a specific public key
	public static function checkSignature($data, $signature, $public_key, $height = null) {
		$chain_id = Block::getChainId($height);
		$res = ec_verify($data, $signature, $public_key, $chain_id);
//        _log("check_signature: $data | signature=$signature | pub_key=$public_key | res=$res");
		return $res;
	}

    // generates a new account and a public/private key pair
	public static function generateAcccount()
	{

		// using secp256k1 curve for ECDSA
		$args = [
			"curve_name"       => "secp256k1",
			"private_key_type" => OPENSSL_KEYTYPE_EC,
		];

        // generates a new key pair
        $key1 = openssl_pkey_new($args);

        // exports the private key encoded as PEM
        openssl_pkey_export($key1, $pvkey);

		if(PHP_VERSION_ID > 80000) {
			$in = sys_get_temp_dir() . "/phpcoin.in.pem";
			$out = sys_get_temp_dir() . "/phpcoin.out.pem";
			file_put_contents($in, $pvkey);
			$cmd = "openssl ec -in $in -out $out >/dev/null 2>&1";
			shell_exec($cmd);
			$pvkey = file_get_contents($out);
			unlink($in);
			unlink($out);
		}

        // converts the PEM to a base58 format
        $private_key = pem2coin($pvkey);

        // exports the private key encoded as PEM
        $pub = openssl_pkey_get_details($key1);

        // converts the PEM to a base58 format
        $public_key = pem2coin($pub['key']);

		// generates the account's address based on the public key
		$address = Account::getAddress($public_key);
		return ["address" => $address, "public_key" => $public_key, "private_key" => $private_key];
	}


    // returns the account balance - any pending debits from the mempool
	public static function pendingBalance($id)
    {
        global $db;
        _log("id=$id",5);
        $res = $db->single("SELECT balance FROM accounts WHERE id=:id", [":id" => $id]);
        if ($res === false) {
            $res = 0;
        }

        // if the original balance is 0, no mempool transactions are possible
        if ($res == 0) {
            return num($res);
        }
	    $mem = Mempool::getSourceMempoolBalance($id);
        $rez = $res - $mem;
        return num($rez);
    }

    static function getTransactions($id, $dm, $offset = 0, $filter = null)
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

        if($offset<0) {
            $offset = 0;
        }

        $current = Block::current();

		$cond = '';
		$params = [":src" => $id, ":dst" => $id,
			":limit" => $limit, ":offset" => $offset];
		if(isset($filter['address']) && !empty($filter['address'])) {
			$cond .= ' and (t.src = :address_src or t.dst = :address_dst) ';
			$params['address_src'] = $filter['address'];
			$params['address_dst'] = $filter['address'];
		}

	    if(isset($filter['type']) && strlen($filter['type']) > 0) {
			$cond .= ' and t.type = :type ';
		    $params['type']=$filter['type'];
	    }

	    if(isset($filter['dir']) && !empty($filter['dir'])) {
			if($filter['dir'] == 'send') {
				$cond .= ' and t.src = :send ';
				$params['send']=$id;
			} else if ($filter['dir'] == 'receive') {
				$cond .= ' and t.dst = :receive ';
				$params['receive']=$id;
			}
	    }

        $res = $db->run(
            "SELECT * FROM transactions t
				WHERE (t.dst=:dst or t.src=:src)
				$cond
				ORDER by t.height DESC LIMIT :offset, :limit", $params
        );

        $transactions = [];
        if(is_array($res)) {
	        foreach ($res as $x) {
		        $trans = [
			        "block" => $x['block'],
			        "height" => $x['height'],
			        "id" => $x['id'],
			        "dst" => $x['dst'],
			        "val" => $x['val'],
			        "fee" => $x['fee'],
			        "signature" => $x['signature'],
			        "message" => $x['message'],
			        "type" => $x['type'],
			        "date" => $x['date'],
			        "public_key" => $x['public_key'],
			        "src" => Account::getAddress($x['public_key']),
			        "data" => $x['data']
		        ];
		        $trans['confirmations'] = $current['height'] - $x['height'];

		        // version 0 -> reward transaction, version 1 -> normal transaction
		        $sign="";
		        $trans['type_value'] = $x['type'];
		        if ($x['type'] == TX_TYPE_REWARD) {
			        $trans['type_label'] = "mining";
			        $sign="+";
		        } elseif ($x['type'] == TX_TYPE_SEND) {
			        if ($x['dst'] == $id) {
				        $trans['type_label'] = "credit";
				        $sign="+";
			        } else {
				        $trans['type_label'] = "debit";
				        $sign="-";
			        }
		        } elseif ($x['type'] == TX_TYPE_MN_CREATE) {
			        if ($x['dst'] == $id) {
				        $trans['type_label'] = "credit";
				        $sign="+";
			        } else {
				        $trans['type_label'] = "debit";
				        $sign="-";
			        }
		        } elseif ($x['type'] == TX_TYPE_MN_REMOVE) {
			        if ($x['dst'] == $id) {
				        $trans['type_label'] = "credit";
				        $sign="+";
			        } else {
				        $trans['type_label'] = "debit";
				        $sign="-";
			        }
		        } elseif ($x['type'] == TX_TYPE_FEE) {
			        $sign="+";
			        $trans['type_label'] = "fee";
		        } elseif ($x['type'] == TX_TYPE_BURN) {
			        $sign="-";
			        $trans['type_label'] = "burn";
                } elseif ($x['type'] == TX_TYPE_SC_CREATE || $x['type'] == TX_TYPE_SC_EXEC) {
                    if ($x['dst'] == $id) {
                        $sign="+";
                        $trans['type_label'] = "credit";
                    } else {
                        $sign = "-";
                        $trans['type_label'] = "debit";
                    }
		        } else {
			        $trans['type_label'] = "other";
		        }
		        $trans['sign'] = $sign;
		        ksort($trans);
		        $transactions[] = $trans;
	        }
        }

        return $transactions;
    }

    static function getCountByAddress($public_key, $id) {
		global $db;
	    $res = $db->single(
		    "SELECT count(*) as cnt FROM transactions 
				WHERE dst=:dst or src=:src",
		    [":src" => $id, ":dst" => $id]
	    );
		return $res;
    }

    static function getMempoolTransactions($id) {
        global $db;
        $transactions = [];
        $res = $db->run(
            "SELECT * FROM mempool WHERE src=:src OR dst =:dst ORDER by height DESC LIMIT 100",
            [":src" => $id, ":dst" => $id]
        );
        foreach ($res as $x) {
            $trans = [
                "block"      => $x['block'],
                "height"     => $x['height'],
                "id"         => $x['id'],
                "dst"        => $x['dst'],
                "src"        => $x['src'],
                "val"        => $x['val'],
                "fee"        => $x['fee'],
                "signature"  => $x['signature'],
                "message"    => $x['message'],
                "type"    => $x['type'],
                "date"       => $x['date'],
                "public_key" => $x['public_key'],
            ];
            $trans['type'] = "mempool";
	        $trans['type_value'] = $x['type'];
            $trans['type_label'] = "mempool";
            // they are unconfirmed, so they will have -1 confirmations.
            $trans['confirmations'] = -1;
	        $sign="";
	        if ($x['type'] == TX_TYPE_SEND) {
		        if ($x['src'] == $id) {
			        $sign = "-";
		        } else {
			        $sign = "+";
		        }
	        } else if ($x['type'] == TX_TYPE_BURN) {
		        $sign = "-";
	        }
	        $trans['sign']=$sign;
            ksort($trans);
            $transactions[] = $trans;
        }
        return $transactions;
    }

    static function publicKey($id) {
        global $db;
        $res = $db->single("SELECT public_key FROM accounts WHERE id=:id", [":id" => $id]);
        return $res;
    }

	static function exists($id) {
		global $db;
		$res = $db->single("SELECT 1 FROM accounts WHERE id=:id", [":id" => $id]);
		return $res == 1;
	}

    static function getCount() {
	    global $db;
	    $sql="select count(*) as cnt from accounts";
	    $row = $db->row($sql);
	    return $row['cnt'];
    }

	static function getCirculation() {
		global $db;
		$sql="select sum(balance) as balance from accounts";
		$row = $db->row($sql);
		return $row['balance'];
	}

	static function getAccounts($dm = null) {
		global $db;
		if($dm == null) {
    	    $sql="select * from accounts limit 100";
			return $db->run($sql);
		} else {
			$page = $dm['page'];
			$limit = $dm['limit'];
			$start = ($page-1)*$limit;
			$sorting = '';
			if(isset($dm['sort'])) {
				$sorting = ' order by '.$dm['sort'];
				if(isset($dm['order'])){
					$sorting.= ' ' . $dm['order'];
				}
			}

            //print_r($dm);
            $where = "";
            if(!empty($dm['search'])) {
                $search = $dm['search'];
                $search = str_replace("*", "%", $search);
                $where = " and a.id like '$search'";
            }

			$height = Block::getHeight();
			$maturity = Blockchain::getStakingMaturity($height);
			$min_balance = Blockchain::getStakingMinBalance($height);
			$sql="select *, ($height - a.height) as maturity,
       			case when $height - a.height >= $maturity and a.balance >= $min_balance then ($height - a.height)*a.balance else 0 end as weight
				from accounts a where 1 $where $sorting limit $start, $limit";
			return $db->run($sql);
		}
	}

	/**
	 *
	 * Checks if exists recorded address in accounts
	 * If address is never accessed, it is without public key
	 *
	 * @param $address
	 * @param $public_key
	 * @param $block
	 * @return array|bool|int
	 */
	public static function checkAccount($address, $public_key, $block, $height) {
		global $db;
		$row = $db->row("select * from accounts where id=:id",[":id" => $address]);
		if(!$row) {
			$bind = [":id" => $address, ":block" => $block, ":public_key" => $public_key, ":height"=>$height];
			$res = $db->run("INSERT INTO accounts 
		        (id, public_key, block, balance, height)
		        values (:id, :public_key, :block, 0, :height)", $bind);
			return $res;
		} else {
			if(empty($row['public_key'])) {
				$res = $db->run("update accounts set public_key=:public_key, height=:height where id=:id", [
					":id" => $address, ":public_key" => $public_key, ":height"=>$height
				]);
				return $res;
			}
		}
		return true;
	}

	public static function addBalance($id, $val, $height) {
		global $db;
		if($height < STAKING_START_HEIGHT) {
			$height = null;
		}
		$res=$db->run(
			"UPDATE accounts SET balance=round(balance+:val,8), height=:height WHERE id=:id",
			[":id" => $id, ":val" => $val, ":height"=>$height]
		);
		return $res !== false;
	}

	public static function getAddress($public_key) {
		if(empty($public_key)) return null;
		$hash1=hash('sha256', $public_key);
		$hash2=hash('ripemd160',$hash1);
		$baseAddress=CHAIN_PREFIX.$hash2;
		$checksumCalc1=hash('sha256', $baseAddress);
		$checksumCalc2=hash('sha256', $checksumCalc1);
		$checksumCalc3=hash('sha256', $checksumCalc2);
		$checksum=substr($checksumCalc3, 0, 8);
		$addressHex = $baseAddress.$checksum;
		$address = base58_encode(hex2bin($addressHex));
		return $address;
	}

	// check the validity of a base58 encoded key. At the moment, it checks only the characters to be base58.
	static function validKey($id) {
		$chars = str_split("123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz");
		for ($i = 0; $i < strlen($id);
		     $i++) {
			if (!in_array($id[$i], $chars)) {
				return false;
			}
		}
		return true;
	}

	public static function valid($address)
	{
		$addressBin=base58_decode($address);
		$addressHex=bin2hex($addressBin);
		$addressChecksum=substr($addressHex, -8);
		$baseAddress = substr($addressHex, 0, -8);
		if(substr($baseAddress, 0, 2) != CHAIN_PREFIX) {
			return false;
		}
		$checksumCalc1=hash('sha256', $baseAddress);
		$checksumCalc2=hash('sha256', $checksumCalc1);
		$checksumCalc3=hash('sha256', $checksumCalc2);
		$checksum=substr($checksumCalc3, 0, 8);
		$valid = $addressChecksum == $checksum;
		return $valid;
	}

	public static function getBalance($id)
	{
		global $db;
		$res = $db->single("SELECT balance FROM accounts WHERE id=:id", [":id" => $id]);
		if ($res === false) {
			$res = 0;
		}

		return num($res);
	}

    public static function getBalances($addresses) {
        global $db;
        $params = [];
        foreach ($addresses as $index=>$address) {
            $name=":p".$index;
            $params[$name]=$address;
        }
        $in_params=implode(",", array_keys($params));
        $sql="select a.id, a.balance from accounts a where a.id in ($in_params)";
        return $db->run($sql, $params);
    }

	public static function getBalanceByPublicKey($publicKey)
	{
		global $db;
		$res = $db->single("SELECT balance FROM accounts WHERE public_key=:public_key", [":public_key" => $publicKey]);
		if ($res === false) {
			$res = 0;
		}

		return num($res);
	}

	static function checkBalances() {
		global $db;
		$sql="select count(*) as cnt from accounts a where a.balance < 0";
		$res =$db->single($sql);
		return $res == 0;
	}

	static function getByPublicKey($public_key) {
		global $db;
		$sql="select * from accounts a where a.public_key = :public_key";
		return $db->row($sql,[":public_key" => $public_key]);
	}

	static function getStakeWinner($height) {
		global $db;
		$maturity = Blockchain::getStakingMaturity($height);
		$min_balance = Blockchain::getStakingMinBalance($height);
		$sql = "select a.id, a.height, a.balance,
		       ($height - a.height) as maturity,
		       if(($height - a.height) > $maturity and a.balance >= $min_balance, ($height - a.height)*a.balance,0) as weight
		from accounts a where a.height is not null and a.id != :genesis
		having weight > 0
		order by weight desc, a.id limit 1";
		$row = $db->row($sql, [":genesis"=>Account::getAddress(GENESIS_DATA['public_key'])]);
		if($row) {
			return $row['id'];
		}
	}

	static function getLastTxHeight($address, $height) {
		global $db;
		$sql= "select max(t.height)
			from transactions t where t.height < :height
    		and (t.src = :src or t.dst=:dst)";
		return $db->single($sql, [":height"=>$height, ":src"=>$address, ":dst"=>$address]);
	}

	static function getBalanceAtHeight($address, $height) {

        global $db;
        $sql= "select sum(val) from (select sum(t.val) * (-1) as val
                      from transactions t
                      where t.src = :address
                        and t.height < :height
                      union
                      select sum(t.val) as val
                      from transactions t
                      where t.dst = :address2
                        and t.height < :height2) as vals;";
        return $db->single($sql, [":height"=>$height, ":height2"=>$height, ":address"=>$address, ":address2"=>$address]);

	}

    static function getMasternode($address) {
        global $db;
        $sql="select a.id, m.height, t.src, t.dst, t.message, t.val as collateral, t.block
            from accounts a
            join transactions t on (t.type = :mncreate and (t.dst = a.id or t.message = a.id))
            join masternode m  on (m.height = t.height and m.id = a.id)
            where a.id = :address";
        $res = $db->row($sql, [":address"=>$address, ":mncreate"=>TX_TYPE_MN_CREATE]);
        return $res;
    }

    static function getMasternodeRewardAddress($address, $height = null) {
        global $db;
        $sql="select a.id, t.message, t.height, t.src, t.val as collateral, t.dst, m.id as masternode, m.public_key
            from accounts a
                     join transactions t on (t.type = :mncreate and t.dst = a.id)
            join masternode m on (m.height = t.height and m.id = t.message)
            where a.id= :address";
        $params = [":address"=>$address, ":mncreate"=>TX_TYPE_MN_CREATE];
        if(!empty($height)) {
            $sql.=" and t.height <= :height";
            $params[":height"]=$height;
        }
        $res = $db->run($sql, $params);
        return $res;
    }

    static function getMasternodes($address) {
        global $db;
        $sql="select addr_mns.*, a.balance as masternode_balance
            from (
            select t.val                                                          as collateral,
                   t.dst                                                          as reward_address,
                   case when t.message = 'mncreate' then t.dst else t.message end as masternode_address
            from accounts a
                     join transactions t on (t.type = :mncreate and t.src = a.id)
            where a.id = :address) as addr_mns
                              join accounts a on (addr_mns.reward_address = a.id)
            where exists (select 1 from masternode m where m.id = addr_mns.masternode_address and addr_mns.collateral = m.collateral );";

        return $db->run($sql, [":mncreate" => TX_TYPE_MN_CREATE, ":address"=>$address]);
    }

    static function getAddressInfo($address) {
        $out['address']=$address;
        $masternode=Account::getMasternode($address);
        $masternodes=[$masternode];
        if(empty($masternode)) {
            $masternodes=Account::getMasternodeRewardAddress($address);
            if(!empty($masternodes)) {
                $type = "masternode_reward";
            } else {
                $type = "no_masternode";
            }
        } else {
            if($masternode['dst']==$address) {
                $type = "hot_masternode";
            } else if ($masternode['message']==$address) {
                $type = "cold_masternode";
            } else {
                $type = "unknown";
            }
        }
        $out['type']=$type;
        $out['masternodes']=$masternodes;
        $height=Block::getHeight();
        $out['cold_masternode_enabled']=$height > MN_COLD_START_HEIGHT;
        return $out;
    }

}
