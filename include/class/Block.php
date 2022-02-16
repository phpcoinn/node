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

	public function add($bootstrapping=false, &$error = null)
    {
        global $db;

        _log("Block insert height=".$this->height, 3);

		try {

		    if (!$bootstrapping) {

		        if(empty($this->generator)) {
			        $this->generator = Account::getAddress($this->publicKey);
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

	            if (!Account::checkSignature($info, $this->signature, $this->publicKey)) {
		            throw new Exception("Block signature check failed info=$info signature={$this->signature} public_key={$this->publicKey}");
	            }

	            if (!$this->parse_block(true, false, $bl_error)) {
		            throw new Exception("Parse block failed: ".$bl_error);
	            }

	            $currentHeight = Block::getHeight();
	            _log("Checking block height currentHeight=$currentHeight height={$this->height}", 3);
	            if($this->height - $currentHeight != 1) {
		            throw new Exception("Block height failed");
	            }
	        }
	        // lock table to avoid race conditions on blocks
	        $db->lockTables();
	        $db->beginTransaction();
	        $total = count($this->data);


	        $bind = [
	            ":id"           => $this->id,
	            ":generator"    => $this->generator,
	            ":miner"        => $this->miner,
	            ":signature"    => $this->signature,
	            ":height"       => $this->height,
	            ":date"         => $this->date,
	            ":nonce"        => $this->nonce,
	            ":difficulty"   => $this->difficulty,
	            ":argon"        => $this->argon,
	            ":version"        => $this->version,
	            ":transactions" => $total,
	        ];
	        $res = Block::insert($bind);
	        if ($res != 1) {
	            // rollback and exit if it fails
	            $db->rollback();
	            $db->unlockTables();
		        throw new Exception("Block DB insert failed");
	        } else {
	            _log("Inserted new block height={$this->height} id=$hash ",1);
	        }

	        // parse the block's transactions and insert them to db
	        $res = $this->parse_block(false, $bootstrapping, $perr);
			if ($res == false) {
				throw new Exception("Parse block failed: $perr");
			}

            _log("Committing block height=".$this->height, 4);
            $db->commit();
	        $db->unlockTables();
	        return true;

		} catch (Exception $e) {
			$error = $e->getMessage();
			if($db->inTransaction()) {
				$db->rollback();
				$db->unlockTables();
			}
			_log($error);
			return false;
		}
    }

    static function getFromArray($b) {
	    $block = new Block($b['generator'], $b['miner'], $b['height'], $b['date'], $b['nonce'], $b['data'], $b['difficulty'],
		    $b['version'], $b['argon'], null);
	    $block->signature = $b['signature'];
	    $block->id = $b['id'];
	    $block->publicKey = $b['public_key'];
	    $block->transactions = $b['transactions'];
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

	public static function difficulty($height = 0)
	{
		global $db;

		// if no block height is specified, use the current block.
		if ($height == 0) {
			$current = Block::current();
		} else {
			$current = Block::getAtHeight($height);
		}


		$height = $current['height'];

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

    // calculate the reward for each block
    public static function reward($id, $data = [])
    {

	    global $_config;

    	$launch_blocks = REWARD_SCHEME['launch']['blocks'];
	    $launch_reward = REWARD_SCHEME['launch']['reward'];
	    $base_reward = BASE_REWARD;

    	$mining_segments = REWARD_SCHEME['mining']['segments'];
    	$mining_segment_block = REWARD_SCHEME['mining']['block_per_segment'];
    	$mining_decrease_per_segment = $base_reward / $mining_segments;

    	$combined_segmnets = REWARD_SCHEME['combined']['segments'];
    	$combined_segment_block = REWARD_SCHEME['combined']['block_per_segment'];
    	$combined_decrease_per_segment = BASE_REWARD / $combined_segmnets;

    	$deflation_segments = REWARD_SCHEME['deflation']['segments'];
    	$deflation_segment_block = REWARD_SCHEME['deflation']['block_per_segment'];
    	$deflation_decrease_per_segment = BASE_REWARD / $deflation_segments;

    	$mining_end_block = ($mining_segments * $mining_segment_block) + $launch_blocks;
    	$combined_end_block = ($combined_segmnets * $combined_segment_block) + $mining_end_block;
    	$deflation_end_block = ($deflation_segments * $deflation_segment_block) + $combined_end_block;

	    $total = 0;
	    $miner = 0;
	    $mn_reward = 0;
	    $generator = 0;
	    $pos_reward = 0;
	    if($id == 1) {
	    	//genesis
		    $total = GENESIS_REWARD;
		    $miner = $total;
		    $mn_reward = 0;
		    $generator = 0;
		    $pos_reward = 0;
		    $phase = "genesis";
	    } else if ($id <= $launch_blocks ) {
			//launch from 1
		    $total = $launch_reward;
		    $miner = $total * 0.9;
		    $generator = $total * 0.1;
		    $mn_reward = 0;
		    $pos_reward = 0;
		    $phase = "launch";
	    } else if ($id <= $mining_end_block) {
		    //mining from 210000
		    $total = (floor(($id - $launch_blocks - 1) / $mining_segment_block) + 1) * $mining_decrease_per_segment;
		    $miner = $total * 0.9;
		    $generator = $total * 0.1;
		    $mn_reward = 0;
		    $pos_reward = 0;
		    $phase = "mining";
	    } else if ($id <= $combined_end_block) {
			// combined
		    $total = $base_reward;
		    $miner_reward = ($combined_segmnets -1 - floor(($id - 1 - $mining_end_block) / $combined_segment_block)) * $combined_decrease_per_segment;
		    $remain_reward = $base_reward - $miner_reward;
		    $miner = $miner_reward * 0.9;
		    $generator = $miner_reward * 0.1;
		    $pos_ratio = 0;
		    $pos_reward = $pos_ratio * $remain_reward;
		    $mn_reward = $remain_reward - $pos_reward;
		    if($miner == 0 && $_config['testnet']) {
			    $total = 1;
			    $miner = 0.9;
			    $generator = 0.1;
			    $mn_reward = 0;
			    $pos_reward = 0;
		    }
		    $phase = "combined";
	    } else if ($id <= $deflation_end_block) {
	    	//deflation
		    $total = ($deflation_segments - 1 - floor(($id -1 - $combined_end_block) / $deflation_segment_block))*$deflation_decrease_per_segment;
		    $pos_ratio = 0;
		    $pos_reward = $pos_ratio * $total;
		    $mn_reward = $total - $pos_reward;
		    $miner = 0;
		    $generator = 0;
		    if($miner == 0 && $_config['testnet']) {
			    $total = 1;
			    $miner = 0.9;
			    $generator = 0.1;
			    $mn_reward = 0;
			    $pos_reward = 0;
		    }
		    $phase = "deflation";
	    } else {
		    $total = 0;
		    $miner = 0;
		    $mn_reward = 0;
		    $pos_reward = 0;
		    $generator = 0;
		    if($miner == 0 && $_config['testnet']) {
			    $total = 1;
			    $miner = 0.9;
			    $generator = 0.1;
			    $mn_reward = 0;
			    $pos_reward = 0;
		    }
		    $phase = "final";
	    }
	    $out = [
	    	'total'=>$total,
		    'miner'=>$miner,
		    'generator'=>$generator,
		    'masternode'=>$mn_reward,
		    'pos'=>$pos_reward,
		    'key'=>"$phase-$total-$miner-$generator-$mn_reward-$pos_reward",
		    'phase'=>$phase
	    ];
        //_log("Reward ", json_encode($out));
	    return $out;
    }

	public function check($new=true)
	{

		_log("Block check ".json_encode($this->toArray()),4);

		if ($this->date>time()+30) {
			_log("Future block - {$this->date} {$this->publicKey}");
			return false;
		}

		// generator's public key must be valid
		if (!Account::validKey($this->publicKey)) {
			_log("Invalid public key - {$this->publicKey}");
			return false;
		}

		//difficulty should be the same as our calculation
		if($new) {
			$calcDifficulty = Block::difficulty();
		} else {
			$calcDifficulty = Block::difficulty($this->height-1);
		}
		if ($this->difficulty != $calcDifficulty) {
			_log("Invalid difficulty - {$this->difficulty} - ".$calcDifficulty);
			return false;
		}

		$version = $this->version;
		$expected_version = Block::versionCode($this->height);
		if($expected_version != $version) {
			_log("Block check height ".$this->height.": invalid version - expected $expected_version got $version");
			return false;
		}

		//check the argon hash and the nonce to produce a valid block
		if (!$this->mine()) {
			_log("Mine check failed");
			return false;
		}

		return true;
	}

    public function mine()
    {
        global $_config;

        // invalid future blocks
        if ($this->date>time()+30) {
        	_log("Future block - invalid");
            return false;
        }

	    $prev = Block::get($this->height-1);
	    if($this->height > 1 && $prev === false) {
	    	_log("Can not get prev block for height {$this->height}");
		    return false;
	    }
	    _log("Prev block ".json_encode($prev), 4);
		$prev_date = $prev['date'];
	    $elapsed = $this->date - $prev_date;
	    _log("Current date = {$this->date} prev date = $prev_date elapsed = $elapsed", 4);
        // get the current difficulty if empty
        if (empty($this->difficulty)) {
            $this->difficulty = Block::difficulty();
        }

        if($elapsed <=0 && $this->height > UPDATE_1_BLOCK_ZERO_TIME) {
	        _log("Block time zero");
	        return false;
        }

	    //verify argon
	    if(!$this->verifyArgon($prev_date, $elapsed)) {
		    _log("Invalid argon={$this->argon}");
		    return false;
	    }

	    $calcNonce = $this->calculateNonce($prev_date, $elapsed);

//        $block_date = $time;
	    if($calcNonce != $this->nonce && $this->height > UPDATE_3_ARGON_HARD) {
		    _log("Invalid nonce {$this->nonce} - {$prev_date}-{$elapsed} calcNonce=$calcNonce");
		    return false;
	    }

	    if(strlen($this->nonce) != 64) {
		    _log("Invalid nonce {$this->nonce}");
		    return false;
	    }


	    $hit = $this->calculateHit();
	    $target = $this->calculateTarget($elapsed);
	    _log("Check hit= " . $hit. " target=" . $target . " current_height=".$this->height.
		    " difficulty=".$this->difficulty." elapsed=".$elapsed, 5);
	    $res = $this->checkHit($hit, $target, $this->height);
	    if(!$res && $this->height > UPDATE_3_ARGON_HARD) {
		    _log("invalid hit or target");
		    return false;
	    }

	    return true;

    }

    // parse the block transactions
    public function parse_block($test = true, $bootstrapping=false, &$error = null)
    {
        global $db;

		try {

	        if(!$bootstrapping) {


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
						if($x['type']==TX_TYPE_SEND) {
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
			        if (!$tx->check($this->height)) {
				        throw new Exception("Transaction check failed - {$tx->id}");
			        }

			        // prepare total balance
			        $balance[$tx->src] += $tx->val + $tx->fee;

			        // check if the transaction is already on the blockchain
			        if ($db->single("SELECT COUNT(1) FROM transactions WHERE id=:id", [":id" => $tx->id]) > 0) {
				        throw new Exception("Transaction already on the blockchain - {$tx->id}", 2);
			        }

			        $type = $tx->type;

			        if ($type == TX_TYPE_SEND || $type == TX_TYPE_MN_CREATE || $type == TX_TYPE_MN_REMOVE) {
				        // check if the account has enough balance to perform the transaction
				        foreach ($balance as $id => $bal) {
				            $acc_balance = Account::getBalance($id);
				            if(round(floatval($acc_balance),8) < round($bal,8)) {
					            throw new Exception("Not enough balance for transaction - $id balance=$acc_balance bal=$bal");
					        }
				        }
			        }
		        }

	        }

	        // if the test argument is false, add the transactions to the blockchain
	        if ($test == false) {
	            foreach ($this->data as $d) {
		            $tx = Transaction::getFromArray($d);
	                $res = $tx->add($this->id, $this->height, $bootstrapping);
	                if ($res == false) {
		                throw new Exception("Not valid transaction");
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
		$res = $block->add();
		if (!$res) {
			api_err("Could not add the genesis block.");
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
        global $_config;
        if ($height < 2) {
	        _log("Genesis blosk is invalid. Must clean db");
	        return false;
        }
        global $db;

        $r = $db->run("SELECT * FROM blocks WHERE height>=:height ORDER by height DESC", [":height" => $height]);

        if (count($r) == 0) {
            return true;
        }
        $db->beginTransaction();
        $db->lockTables();

	    $current = Block::current();

        foreach ($r as $x) {
            $res = Transaction::reverse($x['id']);
            if ($res === false) {
                _log("A transaction could not be reversed. Delete block failed.");
                $db->rollback();
                // the blockchain has some flaw, we should resync from scratch
           
                if (($current['date']<time()-(3600*48)) && $_config['auto_resync']!==false) {
                    _log("Blockchain corrupted. Resyncing from scratch.");
                    $db->fkCheck(false);
                    $tables = ["accounts", "transactions", "mempool", "masternode","blocks"];
                    foreach ($tables as $table) {
                        $db->truncate($table);
                    }
                    $db->fkCheck(true);
                    $db->unlockTables();
                            
              
					Config::setSync(0);
                    @rmdir(SYNC_LOCK_PATH);
	                $dir = ROOT."/cli";
                    system("php $dir/sync.php  > /dev/null 2>&1  &");
                    exit;
                }
                $db->unlockTables();
                return false;
            }
            $res = $db->run("DELETE FROM blocks WHERE id=:id", [":id" => $x['id']]);
            if ($res != 1) {
                _log("Delete block failed.");
                $db->rollback();
                $db->unlockTables();
                return false;
            } else {
            	_log("Deleted block id=".$x['id']." height=".$x['height'],1);
            }
//            $this->reverse_log($x['id']);
        }

      

        $db->commit();
        $db->unlockTables();
        return true;
    }

    public function sign($key)
    {
        $json = json_encode($this->data);
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
		if($target == 0 && $_config['testnet']) {
			$target = 1;
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
		$info = implode("-", $parts);
		_log("getSignatureBase=$info",5);
		return $info;
	}

    function calculateNonce($prev_block_date, $elapsed) {
    	$nonceBase = "{$this->miner}-{$prev_block_date}-{$elapsed}-{$this->argon}";
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
				(id, generator, miner, height, `date`, nonce, signature, difficulty, argon, transactions, version)	
				values (:id, :generator, :miner, :height, :date, :nonce, :signature, :difficulty, :argon, :transactions, :version)",
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

			$version = $this->version;
			$expected_version = Block::versionCode($this->height);
			if($expected_version != $version) {
				throw new Exception("Block check: invalid version $version - expected $expected_version");
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
			if($calcNonce != $nonce && $height > UPDATE_3_ARGON_HARD) {
				throw new Exception("Check nonce failed");
			}
			$hit = $this->calculateHit();
			$target = $this->calculateTarget($elapsed);
			$res =  $this->checkHit($hit, $target, $height);
			if(!$res && $height > UPDATE_3_ARGON_HARD) {
				throw new Exception("Mine check failed hit=$hit target=$target");
			}

			ksort($data);
			foreach ($data as $transaction) {

				$tx = Transaction::getFromArray($transaction);
				$res = $tx->verify($height, $tx_error);
				if(!$res) {
					throw new Exception("Transaction id=".$tx->id." check failed: ".$tx_error);
				}
			}

			$data = json_encode($data);
			$this->prevBlockId = $prev_block_id;
			$signature_base = $this->getSignatureBase();
			$res = ec_verify($signature_base, $this->signature, $public_key);
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

		} catch (Exception $e) {
			$error = $e->getMessage();
			_log($error);
			return false;
		}

		return true;
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
		if($height < UPDATE_1_BLOCK_ZERO_TIME) {
			return "010000";
		} else if ($height >= UPDATE_1_BLOCK_ZERO_TIME && $height < UPDATE_2_BLOCK_CHECK_IMPROVED) {
			return "010001";
		} else if ($height >= UPDATE_2_BLOCK_CHECK_IMPROVED && $height < UPDATE_3_ARGON_HARD) {
			return "010002";	
		} else {
			return "010003";
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
		$mining_segments = REWARD_SCHEME['mining']['segments'];
		$mining_segment_block = REWARD_SCHEME['mining']['block_per_segment'];
		$launch_blocks = REWARD_SCHEME['launch']['blocks'];
		$mining_end_block = ($mining_segments * $mining_segment_block) + $launch_blocks;
		return $mining_end_block +1;
	}
}
