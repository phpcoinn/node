<?php

class Block
{

    public function add($height, $public_key, $miner, $nonce, $data, $date, $signature,
                        $difficulty, $argon, $prev_block_id, $bootstrapping=false)
    {
        global $db;
        $acc = new Account();
        $trx = new Transaction();

        $generator = Account::getAddress($public_key);

        // the transactions are always sorted in the same way, on all nodes, as they are hashed as json
        ksort($data);

        if(count($data)==0 && $height>1) {
        	_log("No transactions");
        	return false;
        }

        // create the hash / block id
        $hash = $this->hash($generator, $miner, $height, $date, $nonce, $data, $signature, $difficulty, $argon, $prev_block_id);

        $json = json_encode($data);

        // create the block data and check it against the signature
        $info = Block::getSignatureBase($generator, $miner, $height, $date, $nonce, $json, $difficulty, VERSION_CODE, $argon, $prev_block_id);
	    // _log($info,3);
        if (!$bootstrapping) {
            if (!Account::checkSignature($info, $signature, $public_key)) {
                _log("Block signature check failed info=$info signature=$signature public_key=$public_key");
                return false;
            }

            if (!$this->parse_block($hash, $height, $data, true)) {
                _log("Parse block failed");
                return false;
            }

            $currentHeight = Block::getHeight();
            _log("Checking block height currentHeight=$currentHeight height=$height");
            if($height - $currentHeight != 1) {
	            _log("Block height failed");
	            return false;
            }
        }
        // lock table to avoid race conditions on blocks
        $db->lockTables();

        $msg = '';
        // insert the block into the db
        $db->beginTransaction();
        $total = count($data);


        $bind = [
            ":id"           => $hash,
            ":generator"    => $generator,
            ":miner"        => $miner,
            ":signature"    => $signature,
            ":height"       => $height,
            ":date"         => $date,
            ":nonce"        => $nonce,
            ":difficulty"   => $difficulty,
            ":argon"        => $argon,
            ":transactions" => $total,
        ];
        $res = Block::insert($bind);
        if ($res != 1) {
            // rollback and exit if it fails
            _log("Block DB insert failed");
            $db->rollback();
            $db->unlockTables();
            return false;
        } else {
        	_log("Inserted new block height=$height id=$hash ",1);
        }

        // parse the block's transactions and insert them to db
        $res = $this->parse_block($hash, $height, $data, false, $bootstrapping);

        // if any fails, rollback
        if ($res == false) {
            _log("Rollback block", 3);
            $db->rollback();
	        $db->unlockTables();
	        return false;
        } else {
//            _log("Commiting block", 3);
            $db->commit();
	        $db->unlockTables();
	        return true;
        }
    }


    // returns the current block, without the transactions
    public function current()
    {
        global $db;
        $current = $db->row("SELECT * FROM blocks ORDER by height DESC LIMIT 1");
        if (!$current) {
            $this->genesis();
            return $this->current(true);
        }
        return $current;
    }

    // returns the previous block
    public function prev()
    {
        global $db;
        $current = $db->row("SELECT * FROM blocks ORDER by height DESC LIMIT 1,1");

        return $current;
    }

    // calculates the difficulty / base target for a specific block. The higher the difficulty number, the easier it is to win a block.
    public function difficulty($height = 0)
    {
        global $db;

        // if no block height is specified, use the current block.
        if ($height == 0) {
            $current = $this->current();
        } else {
            $current = $this->get($height);
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
    public function max_transactions()
    {
        global $db;
        $current = $this->current();
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
    	$mining_decrease_per_segment = $base_reward;

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
	    } else if ($id <= $launch_blocks ) {
			//launch from 1
		    $total = $launch_reward;
		    $miner = $total * 0.9;
		    $generator = $total * 0.1;
		    $mn_reward = 0;
		    $pos_reward = 0;
	    } else if ($id <= $mining_end_block) {
		    //mining from 210000
		    $total = ($mining_segments - floor(($id -1 - $launch_blocks) / $mining_segment_block)) * $mining_decrease_per_segment;
		    $miner = $total * 0.9;
		    $generator = $total * 0.1;
		    $mn_reward = 0;
		    $pos_reward = 0;
	    } else if ($id <= $combined_end_block) {
			// combined
		    $total = $base_reward;
		    $miner_reward = ($combined_segmnets - 1 - floor(($id - 1 - $mining_end_block) / $combined_segment_block)) * $combined_decrease_per_segment;
		    $remain_reward = $base_reward - $miner_reward;
		    $miner = $miner_reward * 0.9;
		    $generator = $miner_reward * 0.1;
		    $pos_ratio = 0.2;
		    $pos_reward = $pos_ratio * $remain_reward;
		    $mn_reward = $remain_reward - $pos_reward;
		    if($miner == 0 && $_config['testnet']) {
			    $total = 1;
			    $miner = 0.9;
			    $generator = 0.1;
			    $mn_reward = 0;
			    $pos_reward = 0;
		    }
	    } else if ($id <= $deflation_end_block) {
	    	//deflation
		    $total = ($deflation_segments - 1 - floor(($id -1 - $combined_end_block) / $deflation_segment_block))*$deflation_decrease_per_segment;
		    $pos_ratio = 0.2;
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
	    }
	    $out = [
	    	'total'=>$total,
		    'miner'=>$miner,
		    'generator'=>$generator,
		    'masternode'=>$mn_reward,
		    'pos'=>$pos_reward,
		    'key'=>"$total-$miner-$generator-$mn_reward-$pos_reward"
	    ];
        //_log("Reward ", json_encode($out));
	    return $out;
    }

    // checks the validity of a block
    public function check($data, $new=true)
    {

	    _log("Block check ".json_encode($data),4);

        if ($data['date']>time()+30) {
            _log("Future block - $data[date] $data[public_key]", 2);
            return false;
        }

        // generator's public key must be valid
        if (!Account::validKey($data['public_key'])) {
            _log("Invalid public key - $data[public_key]",1);
            return false;
        }

        //difficulty should be the same as our calculation
	    if($new) {
	    	$calcDifficulty = $this->difficulty();
	    } else {
		    $calcDifficulty = $this->difficulty($data['height']-1);
	    }
        if ($data['difficulty'] != $calcDifficulty) {
            _log("Invalid difficulty - $data[difficulty] - ".$calcDifficulty,1);
            return false;
        }

        //check the argon hash and the nonce to produce a valid block
        if (!$this->mine($data['public_key'], $data['miner'], $data['nonce'], $data['argon'], $data['difficulty'], $data['id'], $data['height'], $data['date'])) {
            _log("Mine check failed",1);
            return false;
        }

        return true;
    }

    // check if the arguments are good for mining a specific block
    public function mine($public_key, $miner, $nonce, $argon, $difficulty = 0, $current_id = 0, $current_height = 0, $time=0)
    {
        global $_config;
   
        // invalid future blocks
        if ($time>time()+30) {
        	_log("Future block - invalid");
            return false;
        }

//        _log("Block mine current_id=$current_id nonce=$nonce current_height=$current_height time=$time");

        // if no id is specified, we use the current
        if ($current_id === 0 || $current_height === 0) {
            $current = $this->current();
            $current_id = $current['id'];
            $current_height = $current['height'];
        }
        _log("Block Timestamp $time", 4);
        if ($time == 0) {
            $time=time();
        }
	    $current_date = $time;
	    $prev = $this->get($current_height-1);
	    if($prev === false) {
	    	_log("Can not get prev block for height $current_height");
		    return false;
	    }
	    _log("Prev block ".json_encode($prev), 4);
		$prev_date = $prev['date'];
	    $elapsed = $current_date - $prev_date;
	    _log("Current date = $current_date prev date = $prev_date elapsed = $elapsed", 4);
        // get the current difficulty if empty
        if ($difficulty === 0) {
            $difficulty = $this->difficulty();
        }
        
        if (empty($public_key)) {
            _log("Empty public key", 1);
            return false;
        }

	    $generator = Account::getAddress($public_key);

	    //verify argon
	    if(!Block::verifyArgon($prev_date, $elapsed, $argon)) {
		    _log("Invalid argon=$argon", 1);
		    return false;
	    }

	    $calcNonce = Block::calculateNonce($miner, $prev_date, $elapsed, $argon);

//        $block_date = $time;
	    if($calcNonce != $nonce) {
		    _log("Invalid nonce $nonce - {$prev_date}-{$elapsed} calcNonce=$calcNonce", 1);
		    return false;
	    }

	    if(strlen($nonce) != 64) {
		    _log("Invalid nonce $nonce", 1);
		    return false;
	    }


	    $hit = $this->calculateHit($calcNonce, $miner, $current_height, $difficulty);
	    $target = $this->calculateTarget($difficulty, $elapsed);
	    _log("Check hit= " . $hit. " target=" . $target . " current_height=".$current_height.
		    " difficulty=".$difficulty." elapsed=".$elapsed, 4);
	    $res =  (($hit > 0 && $hit > $target) || $current_height==0);
	    if(!$res) {
	    	_log("invalid hit or target");
	    }
	    return $res;

    }


    // parse the block transactions
    public function parse_block($block, $height, $data, $test = true, $bootstrapping=false)
    {
        global $db;
        // data must be array
        if ($data === false) {
            _log("Block data is false", 3);
            return false;
        }
        $acc = new Account();
        $trx = new Transaction();
        // no transactions means all are valid
        if (count($data) == 0) {
            return true;
        }

        // check if the number of transactions is not bigger than current block size
	    if($height > 1) {
	        $max = $this->max_transactions();
	        if (count($data) > $max) {
	            _log("Too many transactions in block", 3);
	            return false;
	        }
	    }

        $balance = [];
        $mns = [];

        foreach ($data as &$x) {
            if (!$bootstrapping) {
                //validate the transaction
                if (!$trx->check($x, $height)) {
                    _log("Transaction check failed - $x[id]", 3);
                    return false;
                }

                // prepare total balance
                $balance[$x['src']] += $x['val'] + $x['fee'];

                // check if the transaction is already on the blockchain
                if ($db->single("SELECT COUNT(1) FROM transactions WHERE id=:id", [":id" => $x['id']]) > 0) {
                    _log("Transaction already on the blockchain - $x[id]", 3);
                    return false;
                }

	            $type = $x['type'];

	            if($type == TX_TYPE_SEND) {
		            if (!$bootstrapping) {

		            	if($x['src'] == $x['dst']) {
		            		_log("Transaction now allowed to itself");
				            return false;
			            }

			            // check if the account has enough balance to perform the transaction
			            foreach ($balance as $id => $bal) {
				            $res = $db->single(
					            "SELECT COUNT(1) FROM accounts WHERE id=:id AND balance>=:balance",
					            [":id" => $id, ":balance" => $bal]
				            );
				            if ($res == 0) {
					            _log("Not enough balance for transaction - $id", 3);
					            return false; // not enough balance for the transactions
				            }
			            }
		            }
	            }
            }
        }
        //only a single masternode transaction per block for any masternode
//        if (count($mns) != count(array_unique($mns))) {
//            _log("Too many masternode transactions", 3);
//            return false;
//        }



        // if the test argument is false, add the transactions to the blockchain
        if ($test == false) {
            foreach ($data as $d) {
                $res = $trx->add($block, $height, $d);
                if ($res == false) {
                    return false;
                }
            }
        }

        return true;
    }


    // initialize the blockchain, add the genesis block
	public $genesis_date = GENESIS_TIME;

    private function genesis()
    {
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
		_log("genesis");

        $res = $this->add(
            $height,
            $public_key,
            $miner,
            $nonce,
            $data,
            $date,
            $signature,
            $difficulty,
            $argon,
	        ""
        );
        if (!$res) {
            api_err("Could not add the genesis block.");
        }
    }

    // delete last X blocks
    public function pop($no = 1)
    {
        $current = $this->current();
        return $this->delete($current['height'] - $no + 1);
    }

    // delete all blocks >= height
    public function delete($height)
    {
        global $_config;
        if ($height < 2) {
	        _log("Genesis blosk is invalid. Must clean db");
	        return false;
        }
        global $db;
        $trx = new Transaction();

        $r = $db->run("SELECT * FROM blocks WHERE height>=:height ORDER by height DESC", [":height" => $height]);

        if (count($r) == 0) {
            return true;
        }
        $db->beginTransaction();
        $db->lockTables();

        foreach ($r as $x) {
            $res = $trx->reverse($x['id']);
            if ($res === false) {
                _log("A transaction could not be reversed. Delete block failed.", 1);
                $db->rollback();
                // the blockchain has some flaw, we should resync from scratch
           
                $current = $this->current();
                if (($current['date']<time()-(3600*48)) && $_config['auto_resync']!==false) {
                    _log("Blockchain corrupted. Resyncing from scratch.", 1);
                    $db->fkCheck(false);
                    $tables = ["accounts", "transactions", "mempool", "masternode","blocks"];
                    foreach ($tables as $table) {
                        $db->truncate($table);
                    }
                    $db->fkCheck(true);
                    $db->unlockTables();
                            
              
                    $db->run("UPDATE config SET val=0 WHERE cfg='sync'");
                    @unlink(SYNC_LOCK_PATH);
	                $dir = ROOT."/cli";
                    system("php $dir/sync.php  > /dev/null 2>&1  &");
                    exit;
                }
                $db->unlockTables();
                return false;
            }
            $res = $db->run("DELETE FROM blocks WHERE id=:id", [":id" => $x['id']]);
            if ($res != 1) {
                _log("Delete block failed.",1);
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


    // sign a new block, used when mining
    public function sign($generator, $miner, $height, $new_block_date, $nonce, $data, $key, $difficulty, $argon, $prev_block_id)
    {
        $json = json_encode($data);
        $info = Block::getSignatureBase($generator, $miner, $height, $new_block_date, $nonce, $json, $difficulty, VERSION_CODE, $argon, $prev_block_id);

        $signature = ec_sign($info, $key);
        _log("sign: $info | key=$key | signature=$signature", 4);
        return $signature;
    }

    // generate the sha512 hash of the block data and converts it to base58
    public function hash($generator, $miner, $height, $date, $nonce, $data, $signature, $difficulty, $argon, $prev_block_id)
    {
	    $json = json_encode($data);
    	$hash_base = Block::getHashBase($generator, $miner, $height, $date, $nonce, $json, $signature, $difficulty, $argon, $prev_block_id);
	    $hash = hash("sha256", $hash_base);
        return hex2coin($hash);
    }


    static function getHashBase($generator,$miner, $height, $date, $nonce, $json, $signature, $difficulty, $argon, $prev_block_id) {
	    $base = Block::getSignatureBase($generator, $miner, $height, $date, $nonce, $json, $difficulty, VERSION_CODE, $argon, $prev_block_id);
	    $hash_base = "{$base}-{$signature}";
	    return $hash_base;
    }

    // exports the block data, to be used when submitting to other peers
    public function export($id = "", $height = "")
    {
        if (empty($id) && empty($height)) {
            return false;
        }

        global $db;
        $trx = new Transaction();
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
    public function get($height)
    {
        global $db;
        if (empty($height)) {
            return false;
        }
        $block = $db->row("SELECT * FROM blocks WHERE height=:height", [":height" => $height]);
        return $block;
    }

    function calculateHit($nonce, $address, $height, $difficulty) {
	    $base = $address . "-" . $nonce . "-" . $height . "-" . $difficulty;
//	    _log("base=$base");
	    $hash = hash("sha256", $base);
	    $hash = hash("sha256", $hash);
	    $hashPart = substr($hash, 0, 8);
	    $value = gmp_hexdec($hashPart);
	    $hit = gmp_div(gmp_mul(gmp_hexdec("ffffffff"), BLOCK_TARGET_MUL) , $value);
	    _log("calculateHit base=$base hit=$hit", 4);
	    return $hit;
    }

    function calculateTarget($difficulty, $elapsed) {
    	if($elapsed == 0) {
    		return 0;
	    }
	    $target = gmp_div(gmp_mul($difficulty , BLOCK_TIME), $elapsed);
	    return $target;
    }

    static function getSignatureBase($generator,$miner,$height,$date,$nonce,$json,$difficulty,$version,$argon,$prev_block_id) {
	    $info = "{$generator}-{$miner}-{$height}-{$date}-{$nonce}-{$json}-{$difficulty}-{$version}-{$argon}-{$prev_block_id}";
	    _log("getSignatureBase=$info",5);
	    return $info;
    }

    static function calculateNonce($miner, $prev_block_date, $elapsed, &$argon = null) {
    	if(empty($argon)) {
		    $argon = Block::calculateArgonHash($miner, $prev_block_date, $elapsed);
	    }
    	$nonceBase = "{$miner}-{$prev_block_date}-{$elapsed}-{$argon}";
	    $calcNonce = hash("sha256", $nonceBase);
	    _log("calculateNonce nonceBase=$nonceBase argon=$argon calcNonce=$calcNonce", 3);
	    return $calcNonce;
    }

    static function calculateArgonHash($miner, $date, $elapsed) {
	    $base = "{$date}-{$elapsed}";
	    $options = HASHING_OPTIONS;
	    $options['salt']=substr($miner, 0, 16);
	    $argon = @password_hash(
		    $base,
		    HASHING_ALGO,
		    $options
	    );
//	    $argon = substr($argon, strlen(HASHING_OPTIONS_STRING));
	    _log("calculateArgonHash date=$date elapsed=$elapsed miner=$miner argon=$argon",4);
	    return $argon;
    }

    static function verifyArgon($date, $elapsed, $argon) {
	    $base = "{$date}-{$elapsed}";
//	    $argon = HASHING_OPTIONS_STRING . $argon;
    	$res =  password_verify($base, $argon);
	    _log("Verify argon base=$base argon=$argon verify=$res", 4);
	    return $res;
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
    	$block = new Block();
    	return $block->get($height);
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
				(id, generator, miner, height, `date`, nonce, signature, difficulty, argon, transactions)	
				values (:id, :generator, :miner, :height, :date, :nonce, :signature, :difficulty, :argon, :transactions)",
		    $bind
	    );
	    return $res;
    }

	static function verifyBlock($block) {

		$generator = $block['generator'];
		$data = $block['data'];
		$date = $block['date'];
		$signature = $block['signature'];
		$difficulty = $block['difficulty'];
		$argon = $block['argon'];
		$version = $block['version'];
		$nonce = $block['nonce'];
		$id =$block['id'];
		$height =$block['height'];
		$miner =$block['miner'];

		$public_key = Account::publicKey($generator);
		if(empty($public_key)) {
			_log("No public key for block address");
			return false;
		}

		if(count($data)==0 && $height>1) {
			_log("No transactions");
			return false;
		}

		$prev_block = Block::getAtHeight($height - 1);

		$elapsed = 0;
		if($prev_block) {
			$prev_date = $prev_block['date'];
			$elapsed = $block['date'] - $prev_date;
			$prev_block_date = $prev_block['date'];
			$prev_block_id = $prev_block['id'];
		} else {
			$prev_block_date = $block['date'];
			$prev_block_id = "";
		}

		$res = Block::verifyArgon($prev_block_date, $elapsed, $argon);
		if(!$res) {
			_log("Check argon failed");
			return false;
		}

		$calcNonce = Block::calculateNonce($miner, $prev_block_date, $elapsed);
		if($calcNonce != $nonce) {
			_log("Check nonce failed");
			return false;
		}
		$bl = new Block();
		$hit = $bl->calculateHit($nonce, $miner, $height, $difficulty);
		$target = $bl->calculateTarget($difficulty, $elapsed);
		$res = (($hit > 0 && $hit > $target));
		if(!$res) {
			_log("Mine check filed");
			return false;
		}

		ksort($data);
		foreach ($data as $transaction) {
			$tx_base = "{$transaction['val']}-{$transaction['fee']}-{$transaction['dst']}-".
				"{$transaction['message']}-{$transaction['type']}-{$transaction['public_key']}-{$transaction['date']}";
			$res = ec_verify($tx_base, $transaction['signature'], $transaction['public_key']);
			if(!$res) {
				_log("Transaction signature failed");
				_log("tx_base=$tx_base 
signature=".$transaction['signature']." 
public_key=".$transaction['public_key']);
//				if($height != 9) {
//					return false;
//				}
			}
		}

		$data = json_encode($data);
		$signature_base = Block::getSignatureBase($generator, $miner, $height, $date,
			$nonce, $data, $difficulty, $version, $argon, $prev_block_id);
		$res = ec_verify($signature_base, $signature, $public_key);
		if(!$res) {
			_log("Block signature check failed signature_base=$signature_base signature=$signature public_key=$public_key");
			return false;
		}

		$hash_base = Block::getHashBase($generator, $miner, $height, $date, $nonce, $data, $signature, $difficulty, $argon, $prev_block_id);
		$hash = hash("sha256", $hash_base);
		$calcBlockId = hex2coin($hash);
		if($calcBlockId != $id) {
			_log("Invalid block id");
			return false;
		}

		return true;
	}
}
