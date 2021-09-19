<?php

use PHPCoin\Blacklist;

class Transaction
{
    // reverse and remove all transactions from a block
    public function reverse($block)
    {
        global $db;
        
        $acc = new Account();
        $r = $db->run("SELECT * FROM transactions WHERE block=:block ORDER by `type` DESC", [":block" => $block]);
        foreach ($r as $x) {
            _log("Reversing transaction $x[id]", 4);
            if (empty($x['src'])) {
                $x['src'] = Account::getAddress($x['public_key']);
            }

	        $type = $x['type'];
	        $res = true;
	        if($type == TX_TYPE_REWARD) {
		        $res = $res && $acc->addBalance($x['dst'], $x['val']*(-1));
	        } else if ($type == TX_TYPE_SEND) {
		        $res = $res && $acc->addBalance($x['dst'], $x['val']*(-1));
		        $res = $res && $acc->addBalance($x['src'], $x['val']);
		        $this->add_mempool($x);
	        }
	        if($res === false) {
		        _log("Update balance for reverse transaction failed", 3);
		        return false;
	        }
            $res = $db->run("DELETE FROM transactions WHERE id=:id", [":id" => $x['id']]);
            if ($res != 1) {
                _log("Delete transaction failed", 3);
                return false;
            }
        }
    }

    // clears the mempool
    public function clean_mempool()
    {
        global $db;
        $block = new Block();
        $current = $block->current();
        $height = $current['height'];
        $limit = $height - 1000;
        $db->run("DELETE FROM mempool WHERE height<:limit", [":limit" => $limit]);
    }

    // returns X  transactions from mempool
    public function mempool($max)
    {
        global $db;
        $block = new Block();
        $current = $block->current();
        $height = $current['height'] + 1;
        // only get the transactions that are not locked with a future height
        $r = $db->run(
            "SELECT * FROM mempool WHERE height<=:height ORDER by val/fee DESC LIMIT :max",
            [":height" => $height, ":max" => $max + 50]
        );
        $transactions = [];
        if (count($r) > 0) {
            $i = 0;
            $balance = [];
            foreach ($r as $x) {
                $trans = [
                    "id"         => $x['id'],
                    "dst"        => $x['dst'],
                    "src"        => $x['src'],
                    "val"        => num($x['val']),
                    "fee"        => num($x['fee']),
                    "signature"  => $x['signature'],
                    "message"    => $x['message'],
                    "type"    => intval($x['type']),
                    "date"       => intval($x['date']),
                    "public_key" => $x['public_key'],
                ];

                if ($i >= $max) {
                    break;
                }


                if (empty($x['public_key'])) {
                    _log("$x[id] - Transaction has empty public_key");
                    continue;
                }
                if (empty($x['src'])) {
                    _log("$x[id] - Transaction has empty src");
                    continue;
                }
                if (!$this->check($trans, $current['height'])) {
                    _log("$x[id] - Transaction Check Failed");
                    continue;
                }
  
                $balance[$x['src']] += $x['val'] + $x['fee'];
                if ($db->single("SELECT COUNT(1) FROM transactions WHERE id=:id", [":id" => $x['id']]) > 0) {
                    _log("$x[id] - Duplicate transaction");
                    continue; //duplicate transaction
                }

	                $res = $db->single(
	                    "SELECT COUNT(1) FROM accounts WHERE id=:id AND balance>=:balance",
	                    [":id" => $x['src'], ":balance" => $balance[$x['src']]]
	                );

	                if ($res == 0) {
	                    _log("$x[id] - Not enough funds in balance");
	                    continue; // not enough balance for the transactions
	                }
                $i++;
                ksort($trans);
                $transactions[$x['id']] = $trans;
            }
        }
        // always sort the array
        ksort($transactions);

        return $transactions;
    }

    // add a new transaction to mempool and lock it with the current height
    public function add_mempool($x, $peer = "")
    {
        global $db;
        global $_config;
        $block = new Block();

        $current = $block->current();
        $height = $current['height'];
        $x['id'] = san($x['id']);
        $bind = [
            ":peer"      => $peer,
            ":id"        => $x['id'],
            "public_key" => $x['public_key'],
            ":height"    => $height,
            ":src"       => $x['src'],
            ":dst"       => $x['dst'],
            ":val"       => $x['val'],
            ":fee"       => $x['fee'],
            ":signature" => $x['signature'],
            ":type"   => $x['type'],
            ":date"      => $x['date'],
            ":message"   => $x['message'],
        ];


        $db->run(
            "INSERT into mempool  
			    (peer, id, public_key, height, src, dst, val, fee, signature, type, message, `date`)
			    values (:peer, :id, :public_key, :height, :src, :dst, :val, :fee, :signature, :type, :message, :date)",
            $bind
        );
        return true;
    }

    // add a new transaction to the blockchain
    public function add($block, $height, $x)
    {
        global $db;
        $acc = new Account();
	    $public_key = $x['public_key'];
	    $address = Account::getAddress($public_key);
	    $res = $acc->checkAccount($address, $public_key, $block);
	    if($res === false) {
	    	_log("Error checking account address");
		    return false;
	    }
	    if ($x['type']==TX_TYPE_SEND){
		    $res = $acc->checkAccount($x['dst'], "", $block);
		    if($res === false) {
			    _log("Error checking account address for send");
			    return false;
		    }
	    }


        $x['id'] = san($x['id']);
        $bind = [
            ":id"         => $x['id'],
            ":public_key" => $x['public_key'],
            ":height"     => $height,
            ":block"      => $block,
            ":dst"        => $x['dst'],
            ":val"        => $x['val'],
            ":fee"        => $x['fee'],
            ":signature"  => $x['signature'],
            ":type"    => $x['type'],
            ":date"       => $x['date'],
            ":message"    => $x['message'],
        ];
        $res = Transaction::insert($bind);
        if ($res != 1) {
            return false;
        }

	    $type = $x['type'];
	    $res = true;
	    if($type == TX_TYPE_REWARD) {
		    $res = $res && $acc->addBalance($x['dst'], $x['val']);
	    } else if ($type == TX_TYPE_SEND) {
		    $res = $res && $acc->addBalance($x['src'], ($x['val'] + $x['fee'])*(-1));
		    $res = $res && $acc->addBalance($x['dst'], ($x['val']));
	    }
	    if($res === false) {
		    _log("Error updating balance for transaction ".$x['id']);
	    }

        $db->run("DELETE FROM mempool WHERE id=:id", [":id" => $x['id']]);
        return true;
    }

    // hash the transaction's most important fields and create the transaction ID
    public function hash($x)
    {
        $val = $x['val'];
        $fee = $x['fee'];
        $date = $x['date'];
        if(is_numeric($val)) {
	        $val = num($val);
        }
        if(is_numeric($fee)) {
	        $fee = num($fee);
        }
        if(!is_numeric($date)) {
        	$date = intval($date);
        }
    	$info = $val."-".$fee."-".$x['dst']."-".$x['message']."-".$x['type']."-".$x['public_key']."-".$date."-".$x['signature'];
        $hash = hash("sha256", $info);
        $hash = hex2coin($hash);
        _log("Transaction hash info=".$info." hash=$hash", 4);
        return $hash;
    }

    // check the transaction for validity
    public function check($x, $height = 0)
    {
        global $db;
        // if no specific block, use current
        if ($height === 0) {
            $block = new Block();
            $current = $block->current();
            $height = $current['height'];
        }
        $info = $this->getSignatureBase($x);


        // the value must be >=0
        if ($x['val'] < 0) {
            _log("$x[id] - Value below 0", 3);
            return false;
        }

	    if ($x['val'] == 0) {
		    _log("$x[id] - Value 0", 3);
		    return false;
	    }

        // the fee must be >=0
        if ($x['fee'] < 0) {
            _log("$x[id] - Fee below 0", 3);
            return false;
        }

	    //genesis transaction
	    $mining_segments = REWARD_SCHEME['mining']['segments'];
	    $mining_segment_block = REWARD_SCHEME['mining']['block_per_segment'];
	    $launch_blocks = REWARD_SCHEME['launch']['blocks'];
	    $mining_end_block = ($mining_segments * $mining_segment_block) + $launch_blocks;
	    if($x['public_key']==GENESIS_DATA['public_key'] && $x['type']==TX_TYPE_SEND && $height < $mining_end_block) {
		    _log("Genesis can not spend before locked height");
		    return false;
	    }

	    $fee = $x['val'] * TX_FEE;
	    $fee = num($fee);

        // added fee does not match
        if ($fee != $x['fee']) {
            _log("$x[id] - Fee not 0.25%", 3);
            _log(json_encode($x), 3);
            return false;
        }

        if ($x['type']==TX_TYPE_SEND) {
            // invalid destination address
            if (!Account::valid($x['dst'])) {
                _log("$x[id] - Invalid destination address", 3);
                return false;
            }
            $src = Account::getAddress($x['public_key']);
            if($src==$x['dst']) {
	            _log("$x[id] - Invalid destination address", 3);
	            return false;
            }
        }


        // public key must be at least 15 chars / probably should be replaced with the validator function
        if (strlen($x['public_key']) < 15) {
            _log("$x[id] - Invalid public key size", 3);
            return false;
        }
        // no transactions before the genesis
	    $block = new Block();
        if ($x['date'] < $block->genesis_date) {
            _log("$x[id] - Date before genesis", 3);
            return false;
        }
        // no future transactions
        if ($x['date'] > time() + 86400) {
            _log("$x[id] - Date in the future", 3);
            return false;
        }
        $id = $this->hash($x);
        // the hash does not match our regenerated hash
        if ($x['id'] != $id) {
                _log("$x[id] - $id - Invalid hash");
                return false;
        }




        
        //verify the ecdsa signature
        if (!Account::checkSignature($info, $x['signature'], $x['public_key'])) {
            _log("$x[id] - Invalid signature - $info");
            return false;
        }

        return true;
    }

    // sign a transaction
    public function sign($x, $private_key)
    {
        $info = $this->getSignatureBase($x);
        
        $signature = ec_sign($info, $private_key);

        return $signature;
    }

    public function getSignatureBase($x) {
    	$val = $x['val'];
    	$fee = $x['fee'];
    	$date = $x['date'];
    	if(is_numeric($val)) {
    		$val = num($val);
	    }
    	if(is_numeric($fee)) {
		    $fee = num($fee);
	    }
    	if(!is_numeric($date)) {
		    $date = intval($date);
	    }
	    $info = $val."-".$fee."-".$x['dst']."-".$x['message']."-".$x['type']."-".$x['public_key']."-".$date;
	    return $info;
    }

    //export a mempool transaction
    public function export($id)
    {
        global $db;
        $r = $db->row("SELECT * FROM mempool WHERE id=:id", [":id" => $id]);
	    $r['date']=intval($r['date']);
        return $r;
    }

    // get the transaction data as array
    public function get_transaction($id)
    {
        global $db;
        $acc = new Account();
        $block = new Block();
        $current = $block->current();

        $x = $db->row("SELECT * FROM transactions WHERE id=:id", [":id" => $id]);

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
        $trans['confirmations'] = $current['height'] - $x['height'];

        if ($x['type'] == TX_TYPE_REWARD) {
            $trans['type'] = "mining";
        } elseif ($x['type'] == TX_TYPE_SEND) {
            if ($x['dst'] == $id) {
                $trans['type'] = "credit";
            } else {
                $trans['type'] = "debit";
            }
        } else {
            $trans['type'] = "other";
        }
        ksort($trans);
        return $trans;
    }

    // return the transactions for a specific block id or height
    public function get_transactions($height = "", $id = "", $includeMiningRewards = false)
    {
        global $db;
        $block = new Block();
        $current = $block->current();
        $acc = new Account();
        $height = san($height);
        $id = san($id);
        if (empty($id) && empty($height)) {
            return false;
        }
        $typeLimit = $includeMiningRewards ? 0 : 1;
        if (!empty($id)) {
            $r = $db->run("SELECT * FROM transactions WHERE block=:id AND type >= :type", [":id" => $id, ":type" => $typeLimit]);
        } else {
            $r = $db->run("SELECT * FROM transactions WHERE height=:height AND type >= :type", [":height" => $height, ":type" => $typeLimit]);
        }
        $res = [];
        foreach ($r as $x) {
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
            $trans['confirmations'] = $current['height'] - $x['height'];

            if ($x['type'] == TX_TYPE_REWARD) {
                $trans['type'] = "mining";
            } elseif ($x['type'] == TX_TYPE_SEND) {
                if ($x['dst'] == $id) {
                    $trans['type'] = "credit";
                } else {
                    $trans['type'] = "debit";
                }
            } else {
                $trans['type'] = "other";
            }
            ksort($trans);
            $res[] = $trans;
        }
        return $res;
    }

    // get a specific mempool transaction as array
    public function get_mempool_transaction($id)
    {
        global $db;
        $x = $db->row("SELECT * FROM mempool WHERE id=:id", [":id" => $id]);
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

        $trans['type'] = "mempool";
        $trans['confirmations'] = -1;
        ksort($trans);
        return $trans;
    }

    function getRewardTransaction($dst,$date,$public_key,$private_key,$reward) {
	    $msg = '';
	    $acc = new Account();
	    $transaction = [
		    "dst"        => $dst,
		    "val"        => $reward,
		    "fee"        => "0.00000000",
		    "message"    => $msg,
		    "type"    => TX_TYPE_REWARD,
		    "date"       => $date,
		    "public_key" => $public_key,
		    "src"=>Account::getAddress($public_key)
	    ];
	    $signature = $this->sign($transaction, $private_key);
	    $transaction['signature'] = $signature;
	    $transaction['id'] = $this->hash($transaction);
	    ksort($transaction);
	    return $transaction;
    }

    static function getForBlock($height) {
		$tx = new Transaction();
		return $tx->get_transactions($height, "", true);
    }

    static function getById($id) {
    	$tx=new Transaction();
    	return $tx->get_transaction($id);
    }


	static function getMempoolById($id) {
		$tx=new Transaction();
		return $tx->get_mempool_transaction($id);
	}

    static function addToMemPool($transaction,$public_key, &$error) {
    	global $db;
	    $trx = new Transaction();
	    $hash = $trx->hash($transaction);
	    $transaction['id'] = $hash;

	    if (!$trx->check($transaction)) {
		    $error = "Transaction signature failed";
		    return false;
	    }
	    $res = $db->single("SELECT COUNT(1) FROM mempool WHERE id=:id", [":id" => $hash]);
	    if ($res != 0) {
		    $error="The transaction is already in mempool";
		    return false;
	    }

	    $res = $db->single("SELECT COUNT(1) FROM transactions WHERE id=:id", [":id" => $hash]);
	    if ($res != 0) {
		    $error= "The transaction is already in a block";
		    return false;
	    }

	    $src = Account::getAddress($public_key);
	    $transaction['src'] = $src;
	    $val = $transaction['val'];
	    $fee = $transaction['fee'];
	    $balance = $db->single("SELECT balance FROM accounts WHERE id=:id", [":id" => $src]);
	    if ($balance < $val + $fee) {
		    $error = "Not enough funds";
		    return false;
	    }

	    $memspent = $db->single("SELECT SUM(val+fee) FROM mempool WHERE src=:src", [":src" => $src]);
	    if ($balance - $memspent < $val + $fee) {
		    $error = "Not enough funds (mempool)";
		    return false;
	    }

	    $trx->add_mempool($transaction, "local");
	    $hashp=escapeshellarg(san($hash));
	    $dir = dirname(dirname(__DIR__)) . "/cli";
	    system("php $dir/propagate.php transaction $hashp > /dev/null 2>&1  &");
	    return $hash;
    }

    static function getCount() {
    	global $db;
    	$sql="select count(*) as cnt from transactions";
		$row = $db->row($sql);
		return $row['cnt'];
    }

    static function getMempoolCount() {
    	global $db;
    	$sql="select count(*) as cnt from mempool";
		$row = $db->row($sql);
		return $row['cnt'];
    }

    static function getByAddress($address, $limit) {
	    $acc = new Account();
	    $transactions = Account::getMempoolTransactions($address);
	    $transactions = array_merge($transactions, Account::getTransactions($address, $limit));
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
    			(id, public_key, block,  height, dst, val, fee, signature, type, message, `date`)
    			values (:id, :public_key, :block, :height, :dst, :val, :fee, :signature, :type, :message, :date)
    			",
		    $bind
	    );
	    return $res;
    }
}
