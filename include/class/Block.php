<?php

class Block
{

    public $generator;
    public $miner;
    public $height;
    public $date;
    public $nonce;
    public $data;
    public $difficulty;
	public $version;
	public $argon;
	public $prevBlockId;

	public $signature;
	public $id;

	public $publicKey;
	public $transactions;

	public $masternode;
	public $mn_signature;

    public $schash;

	/**
	 * @param $generator
	 * @param $miner
	 * @param $height
	 * @param $date
	 * @param $nonce
	 * @param $data
	 * @param $difficulty
	 * @param $version
	 * @param $argon
	 * @param $prevBlockId
	 */
	public function __construct($generator, $miner, $height, $date, $nonce, $data, $difficulty, $version, $argon, $prevBlockId)
	{
		$this->generator = $generator;
		$this->miner = $miner;
		$this->height = $height;
		$this->date = $date;
		$this->nonce = $nonce;
		$this->data = $data;
		$this->difficulty = $difficulty;
		$this->version = $version;
		$this->argon = $argon;
		$this->prevBlockId = $prevBlockId;
	}

	public static function getAddressTypes($address)
	{
		global $db;
		$data = [];
		$sql="select 1 from blocks b where b.masternode = :masternode limit 1";
		$data['is_masternode']=$db->single($sql, [":masternode"=>$address]) == 1;
		$sql="select 1 from blocks b where b.generator = :generator limit 1";
		$data['is_generator']=$db->single($sql, [":generator"=>$address]) == 1;
		$sql="select 1 from blocks b where b.miner = :miner limit 1";
		$data['is_miner']=$db->single($sql, [":miner"=>$address]) == 1;
        $sql="select 1 from transactions t where t.dst = :address and t.type = 0 and t.message = 'stake' limit 1";
        $data['is_stake']=$db->single($sql, [":address"=>$address]) == 1;
        $sql="select 1 from smart_contracts s where s.address = :address";
        $data['is_smart_contract']=$db->single($sql, [":address"=>$address]) == 1;
		return $data;
	}

	public function add(&$error = null, $syncing=false)
    {
        $res = synchronized("block-lock", function () use (&$error, $syncing) {

            try {

                $t1=microtime(true);

                global $db;

                $block = Block::get($this->height);
                if($block && $block['height']=$this->height) {
                    throw new Exception("Block already added");
                }

                $currentHeight = Block::getHeight();
                _log("Checking block height currentHeight=$currentHeight height={$this->height}", 3);
                if($this->height - $currentHeight != 1) {
                    throw new Exception("Block height failed");
                }

                if($this->height > STOP_CHAIN_HEIGHT) {
                    throw new Exception("Blockchain stopped at height " .  STOP_CHAIN_HEIGHT . " - Can not add block");
                }

                if(Config::getVal("blockchain_invalid") == 1) {
                    throw new Exception("Blockchain is invalid - please reimport");
                }

                if(empty($this->generator)) {
                    $this->generator = Account::getAddress($this->publicKey);
                }

                if($this->height >= UPDATE_13_LIMIT_GENERATOR) {
                    $prevBlock = Block::current();
                    _log("GEN: Check generator: block=".$prevBlock['generator']. " new=".$this->generator, 5);
                    if($prevBlock['generator'] == $this->generator && !DEVELOPMENT) {
                        throw new Exception("Generator must nob be consecutive in blocks");
                    }
                }

                // the transactions are always sorted in the same way, on all nodes, as they are hashed as json
                ksort($this->data);

                if(count($this->data)==0 && $this->height>1) {
                    throw new Exception("No transactions");
                }

                if($this->version != Block::versionCode($this->height)) {
                    throw new Exception("Wrong version code");
                }

                // create the hash / block id
                $hash = $this->hash();

                // create the block data and check it against the signature
                $info = $this->getSignatureBase();
                // _log($info,3);

                if (!Account::checkSignature($info, $this->signature, $this->publicKey, $this->height)) {
                    throw new Exception("Block signature check failed info=$info signature={$this->signature} public_key={$this->publicKey}");
                }

                // lock table to avoid race conditions on blocks
                _log("LOCK: lock block add ".$this->height." " . $this->id, 4);
//	        $db->lockTables();
                $db->beginTransaction();
                $total = count($this->data);


                $bind = [
                    ":id"           => $this->id,
                    ":generator"    => $this->generator,
                    ":miner"        => $this->miner,
                    ":masternode"   => $this->masternode,
                    ":signature"    => $this->signature,
                    ":mn_signature" => $this->mn_signature,
                    ":height"       => $this->height,
                    ":date"         => $this->date,
                    ":nonce"        => $this->nonce,
                    ":difficulty"   => $this->difficulty,
                    ":argon"        => $this->argon,
                    ":version"        => $this->version,
                    ":transactions" => $total,
                    ":schash" => $this->schash,
                ];
                $res = Block::insert($bind);
                if ($res != 1) {
                    // rollback and exit if it fails
                    $db->rollback();
                    _log("LOCK: unlock 1 block add ".$this->height." ".$this->id . " - insert failed", 4);
//	            $db->unlockTables();
                    throw new Exception("Block DB insert failed");
                }


                // parse the block's transactions and insert them to db
                $res = $this->parse_block(false, $perr, $syncing);
                if ($res == false) {
                    throw new Exception("Parse block failed ".$this->height." : $perr");
                }

                if(FEATURE_SMART_CONTRACTS) {
                    $schash = $this->processSmartContractTxs($this->height);
                    _log("SCHASH: block=" . $this->schash . " calc=" . $schash, 5);
                    if ($schash === false) {
                        throw new Exception("Parse block failed " . $this->height . " Missing schash");
                    }
                    if (!empty($this->schash) && $this->schash != $schash) {
                        if (in_array($this->height, IGNORE_SC_HASH_HEIGHT)) {
                            _log("Ignore invalid hash");
                        } else {
                            throw new Exception("Invalid schash height=" . $this->height . " block=" . $this->schash . " calculated=" . $schash);
                        }
                    }
                }

                $db->commit();
                _log("LOCK: unlock 2 block add ".$this->height." ".$this->id. " - ok", 4);
//                $db->unlockTables();
                Cache::set("current", $this->toArray());
                Cache::set("height", $this->height);
                Cache::set("current_export", Block::export($hash));
                Cache::set("mineInfo", Blockchain::getMineInfo());

                Masternode::resetVerified();

                $t2=microtime(true);
                $diff=round($t2-$t1,2);
                _log("Inserted new block height={$this->height} date=".date("Y-m-d H:i:s",$this->date)." id=$hash time=$diff");
                return true;

            } catch (Exception $e) {
                $error = $e->getMessage();
                _log("LOCK: Block ".$this->height." ".$this->id." add error: $error", 4);
                if($db->inTransaction()) {
                    $db->rollback();
                    _log("LOCK: unlock 3 block ".$this->height."  add ".$this->id. " ".$error, 4);
//				$db->unlockTables();
                }
                if(FEATURE_SMART_CONTRACTS) {
                    SmartContract::cleanState($this->height);
                }
                _log($error);
                return false;
            }

        });

        return $res;

    }

    function processSmartContractTxs($height, $test = false, &$state_updates=null) {
        $smart_contracts = [];
        foreach ($this->data as $x) {
            $tx = Transaction::getFromArray($x);
            $type = $tx->type;
            if ($type == TX_TYPE_SC_CREATE) {
                $smart_contracts[$tx->dst][$tx->id]=$tx;
            }
            if ($type == TX_TYPE_SC_EXEC) {
                $smart_contracts[$tx->dst][$tx->id]=$tx;
            }
            if ($type == TX_TYPE_SC_SEND) {
                $smart_contracts[$tx->src][$tx->id]=$tx;
            }
        }
        if(empty($smart_contracts)){
            return null;
        }
        $process_schash = SmartContract::process($smart_contracts, $height, $test,  $err, $state_updates);
        if($height >= UPDATE_15_EXTENDED_SC_HASH_V2) {
            $res = Nodeutil::calculateSmartContractsHashV2($height);
            $current_state_hash = $res['hash'];
            $schash = hash("sha256", $current_state_hash."-".$process_schash);
            _log("Save extended schash V2 current_state_hash=$current_state_hash schash=$process_schash schash=$schash", 5);
        } else if($height >= UPDATE_14_EXTENDED_SC_HASH) {
            $res = Nodeutil::calculateSmartContractsHash($height - 100);
            $current_state_hash = $res['hash'];
            $schash = hash("sha256", $current_state_hash."-".$process_schash);
            _log("Save extended schash current_state_hash=$current_state_hash schash=$process_schash schash=$schash", 5);
        } else {
            $schash = $process_schash;
        }
        return $schash;
    }

    static function getFromArray($b) {
	    $block = new Block($b['generator'], $b['miner'], $b['height'], $b['date'], $b['nonce'], @$b['data'], $b['difficulty'],
		    $b['version'], $b['argon'], null);
	    $block->signature = $b['signature'];
	    $block->id = $b['id'];
	    $block->publicKey = @$b['public_key'];
	    $block->transactions = $b['transactions'];
	    $block->masternode = $b['masternode'];
	    $block->mn_signature = $b['mn_signature'];
	    $block->schash = $b['schash'];
	    return $block;
    }

	function toArray() {
		return (array) $this;
	}

    public static function current($object = false)
    {
        global $db;
        $current = $db->row("SELECT * FROM blocks ORDER by height DESC LIMIT 1");
        if (!$current) {
            Block::genesis();
            return self::current();
        }
        if($object) {
            return self::getFromArray($current);
        } else {
        	return $current;
        }
    }

	public static function difficulty($height = null)
	{
		global $db;

		// if no block height is specified, use the current block.
		if ($height === null) {
			$height = Block::getHeight();
		}

		$current = Block::getAtHeight($height);
		if($height < 10 + 2) {
			return BLOCK_START_DIFFICULTY;
		}


		$blk = $db->run("SELECT `date`, height FROM blocks WHERE height<=:h  ORDER by height DESC LIMIT 10", [":h"=>$height]);
		$first = $blk[count($blk)-1]['date'];
		$last = $blk[0]['date'];
		$total_time =  $last - $first;
		$blks=count($blk);

		$result=ceil($total_time/$blks);
		_log("Block time: $result", 4);
		if ($result > BLOCK_TIME * 1.05) {
			$dif = bcmul($current['difficulty'], 0.95);
		} elseif ($result < BLOCK_TIME * 0.95) {
			// if lower, decrease by 5%
			$dif = bcmul($current['difficulty'], 1.05);
		} else {
			// keep current difficulty
			$dif = $current['difficulty'];
		}

		if (strpos($dif, '.') !== false) {
			$dif = substr($dif, 0, strpos($dif, '.'));
		}

		//minimum and maximum diff
		if ($dif < BLOCK_START_DIFFICULTY / 10) {
			$dif = BLOCK_START_DIFFICULTY / 10;
		}
		if ($dif > PHP_INT_MAX) {
			$dif = PHP_INT_MAX;
		}
		_log("Difficulty: $dif", 4);
		return $dif;
	}

    // calculates the maximum block size and increase by 10% the number of transactions if > 100 on the last 100 blocks
    public static function max_transactions()
    {
        global $db;
        $current = Block::current();
        $limit = $current['height'] - 100;
        $avg = $db->single("SELECT AVG(transactions) FROM blocks WHERE height>:limit", [":limit" => $limit]);
        if ($avg < 100) {
            return 100;
        }
        return ceil($avg * 1.1);
    }


    public static function reward($height)
    {
        require_once ROOT . "/include/rewards.inc.php";
        foreach (REWARD_SCHEME as $line) {
            $start = $line[2];
            $end = $line[3];
            if($height>=$start && $height <=$end) {
                $miner = $line [4];
                $generator = $line [5];
                $mn_reward = $line [7];
                $staker = $line [6];
                $name = $line[0];
                $segment = $line[1];
                $total = $generator + $miner + $staker + $mn_reward;
                $out = [
                    'total'=>$total,
                    'miner'=>$miner,
                    'generator'=>$generator,
                    'masternode'=>$mn_reward,
                    'pos'=>$staker,
                    'key'=>"{$name}-{$segment}",
                    'phase'=>$name,
                    'segment'=>empty($segment) ? null : $segment,
                    'staker'=>$staker
                ];
                return $out;
            }
        }
    }

	public function check(&$err = null)
	{

		_log("Block check ".json_encode($this->toArray()),4);

		try {
			if ($this->date>time()+30) {
				throw new Exception("Future block - {$this->date} {$this->publicKey}");
			}

			// generator's public key must be valid
			if (!Account::validKey($this->publicKey)) {
				throw new Exception("Invalid public key - {$this->publicKey}");
			}

			$calcDifficulty = Block::difficulty($this->height-1);
			if ($this->difficulty != $calcDifficulty) {
				throw new Exception("Invalid difficulty - {$this->difficulty} - ".$calcDifficulty. " height=".$this->height. " current=".Block::getHeight());
			}

			$version = $this->version;
			$expected_version = Block::versionCode($this->height);
			if($expected_version != $version) {
				throw new Exception("Block check height ".$this->height.": invalid version - expected $expected_version got $version");
			}

			//check the argon hash and the nonce to produce a valid block
			if (!$this->mine()) {
				throw new Exception("Mine check failed");
			}

			return true;
		} catch (Exception $e) {
			$err = $e->getMessage();
			_log("Error check block: $err");
			return false;
		}


	}

    public function mine(&$err=null)
    {

		try {

			// invalid future blocks
			if ($this->date>time()+30) {
				throw new Exception("Future block - invalid block date=".$this->date . " diff=".(time() - $this->date));
			}

			$prev = Block::get($this->height-1);
			if($this->height > 1 && $prev === false) {
				throw new Exception("Can not get prev block for height {$this->height}");
			}
			_log("Prev block ".json_encode($prev), 4);
			$prev_date = $prev['date'];
			$elapsed = $this->date - $prev_date;
			_log("Current date = {$this->date} prev date = $prev_date elapsed = $elapsed", 4);

			if($elapsed <=0 && $this->height > UPDATE_1_BLOCK_ZERO_TIME) {
				throw new Exception("Block time zero");
			}

			//verify argon
			if(!$this->verifyArgon($prev_date, $elapsed)) {
				throw new Exception("Invalid argon={$this->argon}");
			}

	        $calcNonce = $this->calculateNonce($prev_date, $elapsed);
			$oldCalcNonce = $this->calculateNonce($prev_date, $elapsed, "");

			if($this->height < UPDATE_7_MINER_CHAIN_ID) {
				if($calcNonce == $this->nonce) {
					_log("Mine: accepted new nonce", 5);
				} else if ($oldCalcNonce == $this->nonce) {
					_log("Mine: accepted old nonce", 5);
				}
				if($calcNonce != $this->nonce && $oldCalcNonce != $this->nonce && $this->height > UPDATE_3_ARGON_HARD) {
					throw new Exception("Invalid nonce {$this->nonce} - {$prev_date}-{$elapsed} calcNonce=$calcNonce");
				}
			} else {
				if($calcNonce != $this->nonce) {
					throw new Exception("Invalid nonce {$this->nonce} - {$prev_date}-{$elapsed} calcNonce=$calcNonce - Invalid chainId");
				}
			}

			if(strlen($this->nonce) != 64) {
				throw new Exception("Invalid nonce {$this->nonce}");
			}


			$hit = $this->calculateHit();
			$target = $this->calculateTarget($elapsed);
			_log("Check hit= " . $hit. " target=" . $target . " current_height=".$this->height.
				" difficulty=".$this->difficulty." elapsed=".$elapsed, 5);
			$res = $this->checkHit($hit, $target, $this->height);
			if(!$res && $this->height > UPDATE_3_ARGON_HARD) {
				throw new Exception("invalid hit or target");
			}

	    return true;

		} catch (Exception $e) {
			$err = $e->getMessage();
			_log("Error mining block: $err");
			return false;
		}



    }

    // parse the block transactions
    public function parse_block($test = true, &$error = null, $syncing=false)
    {
        global $db;

		try {

	        // data must be array
	        if ($this->data === false) {
		        throw new Exception("Block data is false");
	        }
	        // no transactions means all are valid
	        if (count($this->data) == 0) {
		        return true;
	        }

	        // check if the number of transactions is not bigger than current block size
	        if ($this->height > 1) {
		        $max = Block::max_transactions();
				$count = 0;
		        foreach ($this->data as $x) {
					if($x['type']==TX_TYPE_SEND || $x['type']==TX_TYPE_BURN) {
						$count++;
					}
		        }
		        if ($count > $max) {
			        throw new Exception("Too many transactions in block count=".$count." max=$max");
		        }
	        }

	        $balance = [];
	        $mns = [];

	        foreach ($this->data as $x) {
		        //validate the transaction
		        $tx = Transaction::getFromArray($x);
		        if (!$tx->check($this, false, $tx_err)) {
			        throw new Exception("Transaction check failed - {$tx->id}: $tx_err");
		        }

		        // check if the transaction is already on the blockchain
		        if ($db->single("SELECT COUNT(1) FROM transactions WHERE id=:id", [":id" => $tx->id]) > 0) {
			        throw new Exception("Transaction already on the blockchain - {$tx->id}");
		        }

		        // prepare total balance
		        $type = $tx->type;
		        if ($type == TX_TYPE_SEND || $type == TX_TYPE_MN_CREATE || $type == TX_TYPE_MN_REMOVE  || $type == TX_TYPE_BURN) {
			        @$balance[$tx->src] += $tx->val + $tx->fee;
		        }

	        }

	        foreach ($balance as $id => $bal) {
		        $acc_balance = Account::getBalance($id);
		        if(round(floatval($acc_balance),8) < round($bal,8)) {
			        throw new Exception("Not enough balance for transaction - $id account_balance=$acc_balance transaction_balance=$bal");
		        }
	        }

	        $res = $this->checkFees($err);
	        if(!$res) {
		        throw new Exception("Block parse fees failed: $err");
	        }

	        // if the test argument is false, add the transactions to the blockchain
	        if ($test == false) {
	            foreach ($this->data as $d) {
		            $tx = Transaction::getFromArray($d);
	                $res = $tx->add($this->id, $this->height, $err);
	                if ($res == false) {
						throw new Exception("Not valid transaction: $err");
					}
                }

				$res = Account::checkBalances();
				if(!$res) {
					throw new Exception("Account balances not valid");
				}

		        if(Masternode::isLocalMasternode()) {
			        Masternode::processBlock($syncing);
		        }

	        }

	        return true;

		} catch (Exception $e) {
			$error = $e->getMessage();
			_log($error);
			return false;
		}
    }


    // initialize the blockchain, add the genesis block
	public $genesis_date = GENESIS_TIME;

	private static function genesis() {

		global $db;
		$height = 1;
		$data = [];

		//generated genesis data
		$signature=GENESIS_DATA['signature'];
		$public_key=GENESIS_DATA['public_key'];
		$argon=GENESIS_DATA['argon'];
		$difficulty=GENESIS_DATA['difficulty'];
		$nonce=GENESIS_DATA['nonce'];
		$date=GENESIS_TIME;
		$data = json_decode(GENESIS_DATA['reward_tx'],true);
		$miner = Account::getAddress($public_key);
		$generator = Account::getAddress($public_key);
		_log("genesis");

		$block = new Block($generator, $miner, $height, $date, $nonce, $data, $difficulty, Block::versionCode(), $argon, "");
		$block->signature = $signature;
		$block->publicKey = $public_key;
		$res = $block->add($err);
		if (!$res) {
			api_err("Could not add the genesis block. Error: $err");
		}
        if($block->id != GENESIS_DATA['block']) {
            api_err("Could not add the genesis block. Error: Invalid block id");
        }

	}


    // delete last X blocks
    public static function pop($no = 1)
    {
        $current = Block::current();
        return Block::delete($current['height'] - $no + 1);
    }

    // delete all blocks >= height
    public static function delete($height)
    {
        if ($height < 2) {
	        _log("Genesis blosk is invalid. Must clean db");
	        return false;
        }
        global $db;

	    Config::setSync(1);

	    global $checkpoints;
	    require_once ROOT . "/include/checkpoints.php";
		$min_height = array_keys($checkpoints)[count($checkpoints)-1];
		$current_height = Block::getHeight();
		if($height < $min_height && $current_height > $min_height) {
			$height = $min_height;
		}

        $blocks = $db->run("SELECT * FROM blocks WHERE height>=:height ORDER by height DESC", [":height" => $height]);

        if (count($blocks) == 0) {
	        Config::setSync(0);
            return true;
        }

        return synchronized("block-lock", function() use ($blocks) {

            _log("Lock delete blocks", 2);
            global $db;
            foreach ($blocks as $block) {
                try {

                    if(!$db->inTransaction()) {
                        $db->beginTransaction();
                    }

                    $t1=microtime(true);

                    $res = Transaction::reverse($block, $err);
                    if ($res === false) {
                        _log("A transaction could not be reversed. Delete block failed.");
                        throw new Exception("A transaction could not be reversed");
                    }

                    $res = Masternode::reverseBlock($block, $merr);
                    if(!$res) {
                        throw new Exception("Reverse masternode winner failed. Error: $merr");
                    }

                    if(FEATURE_SMART_CONTRACTS) {
                        $res = SmartContract::cleanState($block['height'], $cerr);
                        if (!$res) {
                            throw new Exception("Clear smart contract state failed. Error: $cerr");
                        }
                    }

                    $res = $db->run("DELETE FROM blocks WHERE id=:id", [":id" => $block['id']]);
                    if ($res != 1) {
                        throw new Exception("Delete block failed.");
                    } else {
                        $t2=microtime(true);
                        $diff = round($t2-$t1, 2);
                        _log("Deleted block id=".$block['id']." height=".$block['height']." time=$diff");
                    }

                    if($db->inTransaction()) {
                        $db->commit();
                    }

                    Masternode::resetVerified();
                    Cache::remove("current");
                    Cache::remove("mineInfo");
                    Cache::remove("height");
                    Cache::remove("current_export");

                } catch (Exception $e) {
                    _log("Error locking delete blocks ".$e->getMessage());
                    if($db->inTransaction()) {
                        $db->rollback();
                    }
                    Config::setSync(0);
                    return false;
                }
            }

            Config::setSync(0);
            _log("Unlock delete blocks", 2);
            return true;
        });
    }

    public function sign($key)
    {
        $info = $this->getSignatureBase();
        $signature = ec_sign($info, $key);
        $this->signature = $signature;
        _log("sign: $info | key={$key} | signature=$signature", 5);
        return $signature;
    }

	public function hash()
	{
		$hash_base = $this->getHashBase();
		$hash = hash("sha256", $hash_base);
		$hash = hex2coin($hash);
		$this->id = $hash;
		return $hash;
	}

	function getHashBase() {
		$base = $this->getSignatureBase();
		$hash_base = "{$base}-{$this->signature}";
		return $hash_base;
	}

    // exports the block data, to be used when submitting to other peers
    public static function export($id = "", $height = "")
    {
        if (empty($id) && empty($height)) {
            return false;
        }

        global $db;
        if (!empty($height)) {
            $block = $db->row("SELECT * FROM blocks WHERE height=:height", [":height" => $height]);
        } else {
            $block = $db->row("SELECT * FROM blocks WHERE id=:id", [":id" => $id]);
        }

        if (!$block) {
            return false;
        }
        $r = $db->run("SELECT * FROM transactions WHERE block=:block", [":block" => $block['id']]);
        $transactions = [];
        foreach ($r as $x) {
            $trans = [
                "id"         => $x['id'],
                "dst"        => $x['dst'],
                "val"        => num($x['val']),
                "fee"        => num($x['fee']),
                "signature"  => $x['signature'],
                "message"    => $x['message'],
                "type"    => intval($x['type']),
                "date"       => intval($x['date']),
                "public_key" => $x['public_key'],
                "src" => Account::getAddress($x['public_key'])
            ];
			if(!empty($x['data'])) {
				$trans['data']=$x['data'];
			}
            ksort($trans);
            $transactions[$x['id']] = $trans;
        }
        ksort($transactions);
        $block['data'] = $transactions;

        // the reward transaction always has version 0
//        $gen = $db->row(
//            "SELECT public_key, signature FROM transactions WHERE  type=0 AND block=:block AND message=''",
//            [":block" => $block['id']]
//        );
        $block['public_key'] = Account::publicKey($block['generator']);
		if($block['height'] > 1) {
		    $prev_block = Block::get($block['height']-1);
		    $prev_block_date = $prev_block['date'];
			$elapsed = $block['date'] - $prev_block_date;
		    $block['elapsed']=$elapsed;
			_log("ExportBlock id = ".$block['id']." height=".$block['height']." elapsed=$elapsed", 5);
		}
//        $bl = new Block();
//	    $prev = $bl->get($block['height']-1);
//	    $block['prev_block_id']=$prev['id'];
//	    $block['prev_block_date']=$prev['date'];
//        _log("Exporting block: ".print_r($block, 1));
        return $block;
    }

    //return a specific block as array
    public static function get($height)
    {
        global $db;
        if (empty($height)) {
            return false;
        }
        $block = $db->row("SELECT * FROM blocks WHERE height=:height", [":height" => $height]);
        return $block;
    }


	function calculateHit() {
		$base = $this->miner . "-" . $this->nonce . "-" . $this->height . "-" . $this->difficulty;
//	    _log("base=$base");
		$hash = hash("sha256", $base);
		$hash = hash("sha256", $hash);
		$hashPart = substr($hash, 0, 8);
		$value = gmp_hexdec($hashPart);
		$hit = gmp_div(gmp_mul(gmp_hexdec("ffffffff"), BLOCK_TARGET_MUL) , $value);
		_log("calculateHit base=$base hit=$hit", 5);
		return $hit;
	}

	function calculateTarget($elapsed) {
		global $_config;
		if($elapsed == 0) {
			return 0;
		}
		$target = gmp_div(gmp_mul($this->difficulty , BLOCK_TIME), $elapsed);
		if($target == 0 && DEVELOPMENT) {
			$target = 1;
		}
		if($target > 100 && DEVELOPMENT) {
			$target = 100;
		}
		return $target;
	}

	function getSignatureBase() {
    	$parts = [];
    	$parts[] = $this->generator;
    	$parts[] = $this->miner;
    	$parts[] = $this->height;
    	$parts[] = $this->date;
    	$parts[] = $this->nonce;
    	$data = $this->data;
    	ksort($data);
		Transaction::convertValidBurnDst($data, $this->height);
		$data = json_encode($data);
    	$parts[] = $data;
    	$parts[] = $this->difficulty;
    	$parts[] = $this->version;
    	$parts[] = $this->argon;
    	$parts[] = $this->prevBlockId;
		if($this->height >= Block::getMnStartHeight()) {
			$parts[]=$this->masternode;
			$parts[]=$this->mn_signature;
		}
        if($this->height >= SC_START_HEIGHT && !empty($this->schash)) {
            $parts[]=$this->schash;
        }
		$info = implode("-", $parts);
		_log("getSignatureBase=$info",5);
		return $info;
	}

    function calculateNonce($prev_block_date, $elapsed, $chain_id = CHAIN_ID) {
    	$nonceBase = "{$chain_id}{$this->miner}-{$prev_block_date}-{$elapsed}-{$this->argon}";
	    $calcNonce = hash("sha256", $nonceBase);
	    _log("calculateNonce nonceBase=$nonceBase argon={$this->argon} calcNonce=$calcNonce", 5);
	    return $calcNonce;
    }

	function calculateArgonHash($prev_block_date, $elapsed) {
		$base = "{$prev_block_date}-{$elapsed}";
		$options = self::hashingOptions($this->height);
		if($this->height < UPDATE_3_ARGON_HARD) {
			$options['salt']=substr($this->miner, 0, 16);
		}
		$argon = @password_hash(
			$base,
			HASHING_ALGO,
			$options
		);
		return $argon;
	}

    function verifyArgon($date, $elapsed) {
	    if($this->height >= UPDATE_3_ARGON_HARD) {
	    	$argonPrefix = Block::argonPrefix($this->height);
	    	if(substr($this->argon, 0, strlen($argonPrefix)) != $argonPrefix) {
			    _log("Verify argon: Argon prefix not OK argon={$this->argon}", 5);
			    return false;
		    }
	    }
	    $base = "{$date}-{$elapsed}";
    	$res =  password_verify($base, $this->argon);
    	if(!$res) {
		    _log("Verify argon base=$base argon={$this->argon} verify=$res", 5);
		    return false;
	    }
    	return true;
    }

    static function getAll($page, $limit) {
    	global $db;
	    $start = ($page-1)*$limit;
		if($start < 0) $start = 0;
    	$sql="select * from blocks order by height desc limit $start, $limit";
    	return $db->run($sql);
    }

    static function getHeight() {
    	global $db;
	    $sql = "select max(height) as height from blocks";
	    $row = $db->row($sql);
	    return $row['height'];
    }

    static function getAtHeight($height) {
    	return Block::get($height);
    }

    static function getById($id) {
    	global $db;
	    $block = $db->row("SELECT * FROM blocks WHERE id=:id", [":id" => $id]);
	    return $block;
    }

    static function insert($bind){
    	global $db;
	    $res = $db->run(
		    "INSERT into blocks 
				(id, generator, miner, height, `date`, nonce, signature, difficulty, argon, transactions, version, masternode, mn_signature, schash)	
				values (:id, :generator, :miner, :height, :date, :nonce, :signature, :difficulty, :argon, :transactions, :version, :masternode, :mn_signature, :schash)",
		    $bind
	    );
	    return $res;
    }

	public function verifyBlock(&$error = false) {

		$data = $this->data;
		$height = $this->height;

		$error = false;

		try {

			$public_key = Account::publicKey($this->generator);
			if(empty($public_key)) {
				throw new Exception("No public key for block address");
			}

			if(count($data)==0 && $height>1) {
				throw new Exception("No transactions");
			}

			if(count($data) != $this->transactions) {
				throw new Exception("Invalid number of transactions in block");
			}

			$version = $this->version;
			$expected_version = Block::versionCode($this->height);
			if($expected_version != $version) {
				throw new Exception("Block check: invalid version $version - expected $expected_version");
			}

			$difficulty = $this->difficulty;
			$calculated_difficulty = Block::difficulty($this->height-1);
			if($difficulty != $calculated_difficulty) {
				throw new Exception("Block check: invalid difficulty $difficulty - expected $calculated_difficulty");
			}

			$prev_block = Block::getAtHeight($height - 1);

			$elapsed = 0;
			if($prev_block) {
				$prev_date = $prev_block['date'];
				$elapsed = $this->date - $prev_date;
				$prev_block_date = $prev_block['date'];
				$prev_block_id = $prev_block['id'];
			} else {
				$prev_block_date = $this->date;
				$prev_block_id = "";
			}

			$res = $this->verifyArgon($prev_block_date, $elapsed);
			if(!$res) {
				throw new Exception("Check argon failed");
			}
			$nonce = $this->nonce;
			$calcNonce = $this->calculateNonce($prev_block_date, $elapsed);
			$oldCalcNonce = $this->calculateNonce($prev_block_date, $elapsed,"");
			if($height < UPDATE_7_MINER_CHAIN_ID) {
				if($calcNonce != $nonce && $oldCalcNonce != $nonce && $height > UPDATE_3_ARGON_HARD) {
					throw new Exception("Check nonce failed");
				}
			} else {
				if($calcNonce != $nonce) {
					throw new Exception("Check nonce failed - Invalid chainId");
				}
			}
			$hit = $this->calculateHit();
			$target = $this->calculateTarget($elapsed);
			$res =  $this->checkHit($hit, $target, $height);
			if(!$res && $height > UPDATE_3_ARGON_HARD) {
				throw new Exception("Mine check failed hit=$hit target=$target");
			}

            if($height >= UPDATE_13_LIMIT_GENERATOR) {
                _log("GEN: Verify generator: block=".$prev_block['generator']. " new=".$this->generator, 5);
                if($prev_block['generator'] == $this->generator && !DEVELOPMENT) {
                    throw new Exception("Generator must nob be consecutive in blocks");
                }
            }

			ksort($data);
			foreach ($data as $transaction) {

				$tx = Transaction::getFromArray($transaction);
				$res = $tx->verify($this, $tx_error);
				if(!$res) {
					throw new Exception("Transaction id=".$tx->id." check failed: ".$tx_error);
				}
			}

			$res = $this->checkFees($err);
			if(!$res) {
				throw new Exception("Block verify fees failed: $err");
			}

			$data = json_encode($data);
			$this->prevBlockId = $prev_block_id;
			$signature_base = $this->getSignatureBase();
			$res = ec_verify($signature_base, $this->signature, $public_key, Block::getChainId($this->height));
			if(!$res) {
				throw new Exception("Block signature check failed signature_base=$signature_base signature={$this->signature} public_key=$public_key");
			}

			$hash_base = $this->getHashBase();
			$hash = hash("sha256", $hash_base);
			$calcBlockId = hex2coin($hash);
			$id = $this->id;
			if($calcBlockId != $id) {
				throw new Exception("Invalid block id");
			}

			$res = Masternode::verifyBlock($this, $err);
			if(!$res) {
				throw new Exception("Not verified masternode reward ".$err);
			}

		} catch (Exception $e) {
			$error = $e->getMessage();
			_log($error);
			return false;
		}

		return true;
	}

	function checkFees(&$error = null) {

		try {
			$fee = 0;
			$fee_txs = [];
			foreach ($this->data as $transaction) {
				$tx = Transaction::getFromArray($transaction);
				$fee += $tx->fee;
				if($tx->type == TX_TYPE_FEE) {
					$fee_txs[]=$tx;
				}
			}
			$fee = round($fee, 8);
			if(count($fee_txs) == 0) {
				if ($fee > 0) {
					throw new Exception("Missing fee transaction");
				}
			} else if(count($fee_txs) == 1) {
				if ($fee <= 0) {
					throw new Exception("Invalid fee for transactions");
				} else {
					$fee_tx = $fee_txs[0];
					if ($fee_tx->val != $fee) {
						throw new Exception("Invalid value in fee transaction");
					}
				}
			} else {
				throw new Exception("Multiple fee transactions");
			}
			return true;
		} catch (Exception $e) {
			$error = $e->getMessage();
			return false;
		}
	}

	function checkHit($hit, $target, $height) {
		$res =  (($hit > 0
				&& ($target > 0 || ($target == 0 && $height < UPDATE_1_BLOCK_ZERO_TIME))
				&& $hit > $target) || $height==1);
		return $res;
	}

	static function versionCode($height=null) {
		if($height == null) {
			$height = self::getHeight();
		}
        if(CHAIN_ID == "01") {
            if ($height < UPDATE_1_BLOCK_ZERO_TIME) {
                return "010000";
            } else if ($height >= UPDATE_1_BLOCK_ZERO_TIME && $height < UPDATE_2_BLOCK_CHECK_IMPROVED) {
                return "010001";
            } else if ($height >= UPDATE_2_BLOCK_CHECK_IMPROVED && $height < UPDATE_3_ARGON_HARD) {
                return "010002";
            } else if ($height >= UPDATE_3_ARGON_HARD && $height < UPDATE_6_CHAIN_ID) {
                return "010003";
            } else {
                return CHAIN_ID . "0004";
            }
        } else {
            return CHAIN_ID . "0000";
        }
	}

	static function hashingOptions($height=null) {
		if($height == null) {
			$height = self::getHeight();
		}
		if($height < UPDATE_3_ARGON_HARD) {
			return ['memory_cost' => 2048, "time_cost" => 2, "threads" => 1];
		} else {
			return ['memory_cost' => 32768, "time_cost" => 2, "threads" => 1];
		}
	}

	static function argonPrefix($height=null) {
		if($height == null) {
			$height = self::getHeight();
		}
		if($height < UPDATE_3_ARGON_HARD) {
			return '$argon2i$v=19$m=2048,t=2,p=1';
		} else {
			return '$argon2i$v=19$m=32768,t=2,p=1';
		}
	}

	static function getMnStartHeight() {
		return MN_START_HEIGHT;
	}

	static function getChainId($height=null) {
		if(empty($height)) {
			return CHAIN_ID;
		} else {
			if($height < UPDATE_6_CHAIN_ID) {
				return "";
			} else {
				return CHAIN_ID;
			}
		}
	}

	static function getCachedHeight() {
		return Cache::get("height", function() {
			return Block::getHeight();
		});
	}

	static function getMasternodeCollateral($height, $next=false) {
        require_once ROOT . "/include/rewards.inc.php";
        foreach (REWARD_SCHEME as $i => $line) {
            $start = $line[2];
            $end = $line[3];
            if($height >= $start && $height <= $end) {
                $collateral = $line[8];
                if($next) {
                    for($j=$i+1; $j<count(REWARD_SCHEME); $j++) {
                        $line2 = REWARD_SCHEME[$j];
                        $next_collateral = $line2[8];
                        if($next_collateral != $collateral) {
                            $collateral = $next_collateral;
                            break;
                        }
                    }
                } else {
                    break;
                }
            }
        }
		return $collateral;
	}

	static function getNextCollateralHeight($height) {
        require_once ROOT . "/include/rewards.inc.php";
        $next_height = null;
        foreach (REWARD_SCHEME as $i => $line) {
            $start = $line[2];
            $end = $line[3];
            if($height >= $start && $height <= $end) {
                $collateral = $line[8];
                for($j=$i+1; $j<count(REWARD_SCHEME); $j++) {
                    $line2 = REWARD_SCHEME[$j];
                    $next_collateral = $line2[8];
                    if($next_collateral != $collateral) {
                        $collateral = $next_collateral;
                        $next_height = $line2[2];
                        break;
                    }
                }
            }
        }
        return $next_height;
	}
}
