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
	public $id;

	public $src;

	public function __construct($publicKey=null,$dst=null,$val=null,$type=null,$date = null,$msg = null)
	{
		$this->val = $val;
		$this->fee = 0;
		$this->dst = $dst;
		$this->msg = $msg;
		$this->type = $type;
		$this->publicKey = $publicKey;
		$this->date = empty($date) ? time() : $date;
		$this->src = Account::getAddress($this->publicKey);
	}


	// reverse and remove all transactions from a block
    public static function reverse($block)
    {
        global $db;
        
        $r = $db->run("SELECT * FROM transactions WHERE block=:block ORDER by `type` DESC", [":block" => $block]);
        foreach ($r as $x) {
        	$tx = Transaction::getFromDbRecord($x);
            _log("Reversing transaction {$tx->id}", 4);
            if (empty($tx->src)) {
	            $tx->src = Account::getAddress($tx->publicKey);
            }

	        $type = $tx->type;
	        $res = true;
	        if($type == TX_TYPE_REWARD) {
		        $res = $res && Account::addBalance($tx->dst, $tx->val*(-1));
	        } else if ($type == TX_TYPE_SEND) {
		        $res = $res && Account::addBalance($tx->dst, $tx->val*(-1));
		        $res = $res && Account::addBalance($tx->src, $tx->val);
		        $tx->_add_mempool();
	        }
	        if($res === false) {
		        _log("Update balance for reverse transaction failed", 3);
		        return false;
	        }
            $res = $db->run("DELETE FROM transactions WHERE id=:id", [":id" => $tx->id]);
            if ($res != 1) {
                _log("Delete transaction failed", 3);
                return false;
            }
        }
    }

    // clears the mempool
    public static function clean_mempool()
    {
        global $db;
        $current = Block::_current();
        $height = $current['height'];
        $limit = $height - 1000;
        $db->run("DELETE FROM mempool WHERE height<:limit", [":limit" => $limit]);
    }

    public static function getFromDbRecord($x) {
	    $trans = new Transaction($x['public_key'],$x['dst'],$x['val'],intval($x['type']),intval($x['date']),$x['message']);
	    $trans->id = $x['id'];
	    $trans->src = $x['src'];
	    $trans->fee = $x['fee'];
	    $trans->signature = $x['signature'];
	    return $trans;
    }

	public static function getFromArray($x) {
		$trans = new Transaction($x['public_key'],$x['dst'],floatval($x['val']),$x['type'],$x['date'],$x['message']);
		$trans->id = $x['id'];
		$trans->src = $x['src'];
		$trans->fee = floatval($x['fee']);
		$trans->signature = $x['signature'];
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
	    ksort($trans);
	    return $trans;
    }

    // returns X  transactions from mempool
    public static function mempool($max)
    {
        global $db;
        $current = Block::_current();
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
                $trans = self::getFromDbRecord($x);

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
                if (!$trans->_check($current['height'])) {
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
                $transactions[$x['id']] = $trans->toArray();
            }
        }
        // always sort the array
        ksort($transactions);

        return $transactions;
    }

	// add a new transaction to mempool and lock it with the current height
	public function _add_mempool($peer = "")
	{
		global $db;

		$current = Block::_current();
		$height = $current['height'];
		$bind = [
			":peer"      => $peer,
			":id"        => $this->id,
			"public_key" => $this->publicKey,
			":height"    => $height,
			":src"       => $this->src,
			":dst"       => $this->dst,
			":val"       => $this->val,
			":fee"       => $this->fee,
			":signature" => $this->signature,
			":type"   => $this->type,
			":date"      => $this->date,
			":message"   => $this->msg,
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
	public function _add($block, $height)
	{
		global $db;
		$public_key = $this->publicKey;
		$address = Account::getAddress($public_key);
		$res = Account::checkAccount($address, $public_key, $block);
		if($res === false) {
			_log("Error checking account address");
			return false;
		}
		if ($this->type==TX_TYPE_SEND){
			$res = Account::checkAccount($this->dst, "", $block);
			if($res === false) {
				_log("Error checking account address for send");
				return false;
			}
		}


		$bind = [
			":id"         => $this->id,
			":public_key" => $this->publicKey,
			":height"     => $height,
			":block"      => $block,
			":dst"        => $this->dst,
			":val"        => $this->val,
			":fee"        => $this->fee,
			":signature"  => $this->signature,
			":type"    => $this->type,
			":date"       => $this->date,
			":message"    => $this->msg,
		];
		$res = Transaction::insert($bind);
		if ($res != 1) {
			return false;
		}

		$type = $this->type;
		$res = true;
		if($type == TX_TYPE_REWARD) {
			$res = $res && Account::addBalance($this->dst, $this->val);
		} else if ($type == TX_TYPE_SEND) {
			$res = $res && Account::addBalance($this->src, ($this->val + $this->fee)*(-1));
			$res = $res && Account::addBalance($this->dst, ($this->val));
		}
		if($res === false) {
			_log("Error updating balance for transaction ".$this->id);
		}

		$db->run("DELETE FROM mempool WHERE id=:id", [":id" => $this->id]);
		return true;
	}

    // hash the transaction's most important fields and create the transaction ID
	public function _hash()
	{

		$base = $this->_getSignatureBase();
		$base = $base ."-" . $this->signature;

		$hash = hash("sha256", $base);
		$hash = hex2coin($hash);
		_log("Transaction hash info=".$base." hash=$hash", 4);
		$this->id = $hash;
		return $hash;
	}

    // check the transaction for validity
    public function _check($height = 0)
    {
        global $db;
        // if no specific block, use current
        if ($height === 0) {
            $current = Block::_current();
            $height = $current['height'];
        }
        $base = $this->_getSignatureBase();


        // the value must be >=0
        if ($this->val <= 0) {
            _log("{$this->val} - Value <= 0", 3);
            return false;
        }

        // the fee must be >=0
        if ($this->fee < 0) {
            _log("{$this->fee} - Fee below 0", 3);
            return false;
        }

	    //genesis transaction
	    $mining_segments = REWARD_SCHEME['mining']['segments'];
	    $mining_segment_block = REWARD_SCHEME['mining']['block_per_segment'];
	    $launch_blocks = REWARD_SCHEME['launch']['blocks'];
	    $mining_end_block = ($mining_segments * $mining_segment_block) + $launch_blocks;
	    if($this->publicKey==GENESIS_DATA['public_key'] && $this->type==TX_TYPE_SEND && $height < $mining_end_block) {
		    _log("Genesis can not spend before locked height");
		    return false;
	    }

	    $fee = $this->val * TX_FEE;
	    $fee = num($fee);

        // added fee does not match
        if ($fee != $this->fee) {
            _log("{$this->id} - Fee not 0.25%", 3);
            _log(json_encode($this), 3);
            return false;
        }

        if ($this->type==TX_TYPE_SEND) {
            // invalid destination address
            if (!Account::valid($this->dst)) {
                _log("{$this->id} - Invalid destination address", 3);
                return false;
            }
            $src = Account::getAddress($this->publicKey);
            if($src==$this->dst) {
	            _log("{$this->id} - Invalid destination address", 3);
	            return false;
            }
        }


        // public key must be at least 15 chars / probably should be replaced with the validator function
        if (strlen($this->publicKey) < 15) {
            _log("{$this->id} - Invalid public key size", 3);
            return false;
        }
        // no transactions before the genesis
        if ($this->date < GENESIS_TIME) {
            _log("{$this->id} - Date before genesis", 3);
            return false;
        }
        // no future transactions
        if ($this->date > time() + 86400) {
            _log("{$this->id} - Date in the future", 3);
            return false;
        }
        $thisId = $this->id;
        $id = $this->_hash();
        // the hash does not match our regenerated hash
        if ($thisId != $id) {
                _log("{$this->id} - $id - Invalid hash");
                return false;
        }

        //verify the ecdsa signature
        if (!Account::checkSignature($base, $this->signature, $this->publicKey)) {
            _log("{$this->id} - Invalid signature - $base");
            return false;
        }

        return true;
    }

	public function _sign($private_key)
	{
		$base = $this->_getSignatureBase();
		$signature = ec_sign($base, $private_key);
		$this->signature = $signature;
		return $signature;
	}

    public function _getSignatureBase() {
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
    	$parts[]=$this->dst;
    	$parts[]=$this->msg;
    	$parts[]=$this->type;
    	$parts[]=$this->publicKey;
    	$parts[]=$date;
	    $base = implode("-", $parts);
	    return $base;
    }

	public static function _export($id)
	{
		global $db;
		$r = $db->row("SELECT * FROM mempool WHERE id=:id", [":id" => $id]);
		$r['date']=intval($r['date']);
		return $r;
	}

    // get the transaction data as array
    public static function get_transaction($id)
    {
        global $db;
        $current = Block::_current();

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
	    ];
	    $trans['confirmations'] = $height - $x['height'];

	    if ($x['type'] == TX_TYPE_REWARD) {
		    $trans['type_label'] = "mining";
	    } elseif ($x['type'] == TX_TYPE_SEND) {
		    $trans['type_label'] = "transfer";
	    } else {
		    $trans['type_label'] = "other";
	    }
	    return $trans;
    }

    // return the transactions for a specific block id or height
    public static function get_transactions($height = "", $id = "", $includeMiningRewards = false)
    {
        global $db;
        $current = Block::_current();
        $height = san($height);
        $id = san($id);
        if (empty($id) && empty($height)) {
            return false;
        }
        $typeLimit = $includeMiningRewards ? TX_TYPE_REWARD : TX_TYPE_SEND;
        if (!empty($id)) {
            $r = $db->run("SELECT * FROM transactions WHERE block=:id AND type >= :type", [":id" => $id, ":type" => $typeLimit]);
        } else {
            $r = $db->run("SELECT * FROM transactions WHERE height=:height AND type >= :type", [":height" => $height, ":type" => $typeLimit]);
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

        $trans['type_label'] = "mempool";
        $trans['confirmations'] = -1;
        ksort($trans);
        return $trans;
    }

    static function getRewardTransaction($dst,$date,$public_key,$private_key,$reward) {
	    $msg = '';
	    $transaction = new Transaction($public_key,$dst,$reward,TX_TYPE_REWARD,$date,$msg);
	    $signature = $transaction->_sign($private_key);
	    $transaction->_hash();
	    return $transaction->toArray();
    }

    static function getForBlock($height) {
		return Transaction::get_transactions($height, "", true);
    }

	static function _getById($id) {
		global $db;
		$x = $db->row("SELECT * FROM transactions WHERE id=:id", [":id" => $id]);
		return Transaction::getFromDbRecord($x);
	}


	static function getMempoolById($id) {
		return Transaction::get_mempool_transaction($id);
	}


    function _addToMemPool(&$error) {
    	global $db;
	    $hash = $this->_hash();

	    if (!$this->_check()) {
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

	    $balance = $db->single("SELECT balance FROM accounts WHERE id=:id", [":id" => $this->src]);
	    if ($balance < $this->val + $this->fee) {
		    $error = "Not enough funds";
		    return false;
	    }

	    $memspent = $db->single("SELECT SUM(val+fee) FROM mempool WHERE src=:src", [":src" => $this->src]);
	    if ($balance - $memspent < $this->val + $this->fee) {
		    $error = "Not enough funds (mempool)";
		    return false;
	    }

	    $this->_add_mempool("local");
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
