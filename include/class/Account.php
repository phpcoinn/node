<?php

class Account
{

	// checks the ecdsa secp256k1 signature for a specific public key
	public static function checkSignature($data, $signature, $public_key) {
		$res = ec_verify($data, $signature, $public_key);
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

    static function getTransactions($id, $dm)
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
	        $offset = 0;
        }

        $current = Block::current();
        $public_key = Account::publicKey($id);

        $res = $db->run(
            "SELECT * FROM transactions 
				WHERE dst=:dst or (public_key=:src AND type != :rewardType)
				ORDER by height DESC LIMIT :offset, :limit",
            [":src" => $public_key, ":dst" => $id, ":rewardType"=>TX_TYPE_REWARD,
	            ":limit" => $limit, ":offset" => $offset]
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
		        ];
		        $trans['confirmations'] = $current['height'] - $x['height'];

		        // version 0 -> reward transaction, version 1 -> normal transaction
		        $sign="";
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
				WHERE dst=:dst or (public_key=:src AND type != :rewardType)",
		    [":src" => $public_key, ":dst" => $id, ":rewardType" => TX_TYPE_REWARD]
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
                "val"        => $x['val'],
                "fee"        => $x['fee'],
                "signature"  => $x['signature'],
                "message"    => $x['message'],
                "type"    => $x['type'],
                "date"       => $x['date'],
                "public_key" => $x['public_key'],
            ];
            $trans['type'] = "mempool";
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
			$sql="select * from accounts $sorting limit $start, $limit";
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
	public static function checkAccount($address, $public_key, $block) {
		global $db;
		$row = $db->row("select * from accounts where id=:id",[":id" => $address]);
		if(!$row) {
			$bind = [":id" => $address, ":block" => $block, ":public_key" => $public_key];
			$res = $db->run("INSERT INTO accounts 
		        (id, public_key, block, balance)
		        values (:id, :public_key, :block, 0)", $bind);
			return $res;
		} else {
			if(empty($row['public_key'])) {
				$res = $db->run("update accounts set public_key=:public_key where id=:id", [
					":id" => $address, ":public_key" => $public_key
				]);
				return $res;
			}
		}
		return true;
	}

	public static function addBalance($id, $val) {
		global $db;
		$res=$db->run(
			"UPDATE accounts SET balance=balance+:val WHERE id=:id",
			[":id" => $id, ":val" => $val]
		);
		return $res;
	}

	public static function getAddress($public_key) {
		if(empty($public_key)) return null;
		$hash1=hash('sha256', $public_key);
		$hash2=hash('ripemd160',$hash1);
		$baseAddress=NETWORK_PREFIX.$hash2;
		$checksumCalc1=hash('sha256', $baseAddress);
		$checksumCalc2=hash('sha256', $checksumCalc1);
		$checksumCalc3=hash('sha256', $checksumCalc2);
		$checksum=substr($checksumCalc3, 0, 8);
		$addressHex = $baseAddress.$checksum;
		$address = base58_encode(hex2bin($addressHex));
//    	_log("get_address: $public_key=$public_key address=$address");
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
		if(substr($baseAddress, 0, 2) != NETWORK_PREFIX) {
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

}
