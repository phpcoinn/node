<?php

class NodeMiner extends Task {

	static $name = "miner";
	static $title = "Miner";

	static $run_interval = 60;

	public $public_key;
	public $private_key;
	public $miningStat;
	public $cnt = 0;
	public $blocks = 0;
    public $address;

	private $running = true;
	private $minerid;
	private $cpu = 25;

    private $hashing_time = 0;
    private $hashing_cnt = 0;
    private $speed;
    private $sleep_time;
    private $attempt;

	function __construct()
	{
		global $_config;
		$this->public_key = $_config['miner_public_key'];
		$this->private_key = $_config['miner_private_key'];
        $this->address = Account::getAddress($this->public_key);
        $this->minerid = time() . uniqid();
        $this->cpu = isset($_config['miner_cpu']) ? $_config['miner_cpu'] : 0;
	}

	function getMiningInfo() {

		$info = Blockchain::getMineInfo();
		return $info;
	}

    function measureSpeed($t1, $th) {
        $t2 = microtime(true);
        $this->hashing_cnt++;
        $this->hashing_time = $this->hashing_time + ($t2-$th);

        $diff = $t2 - $t1;
        $this->speed = round($this->attempt / $diff,2);

        $calc_cnt = round($this->speed * 60);

        if($this->hashing_cnt % $calc_cnt == 0) {
            $this->sleep_time = $this->cpu == 0 ? INF : round((($this->hashing_time/$this->hashing_cnt)*1000)*(100-$this->cpu)/$this->cpu);
            if($this->sleep_time < 0) {
                $this->sleep_time = 0;
            }
        }

        $this->miningStat['speed']=$this->speed;
        $this->miningStat['cpu']=$this->cpu;

//        $rem = (floor($this->hashing_cnt / $calc_cnt) +1) * $calc_cnt - $this->hashing_cnt;
//        $l= "measure speed: t1=$t1 t2=$t2 diff=$diff speed=$this->speed hashing_cnt=$this->hashing_cnt calc_cnt=$calc_cnt rem=$rem sleep_time=$this->sleep_time".PHP_EOL;
//        _log($l);
    }

	function start($mine_blocks = null, $sleep = 3) {

        set_time_limit(60*60);
		$this->loadMiningStats();
        $start_time = time();
        $prev_hashes = $this->miningStat['hashes'];

        $this->sleep_time=(100-$this->cpu)*5;

		while($this->running) {
			$this->cnt++;

			$_config = Nodeutil::getConfig();

			if (Config::isSync()) {
				_log("Sync in process - stop miner");
				return false;
			}

			$peersCount = Peer::getCount();
			$minPeersCount = DEVELOPMENT ? 0 : 3;
			if($peersCount < $minPeersCount) {
				_log("Not enough node peers $peersCount min=$minPeersCount");
				return false;
			}

			$info = $this->getMiningInfo();
			if($info === false) {
				_log("Can not get mining info");
				return false;
			}

			$nodeScore = $_config['node_score'];
			if($nodeScore < MIN_NODE_SCORE && !DEVELOPMENT) {
				_log("Node score not ok");
				return false;
			}

			$generator = Account::getAddress($this->public_key);
			$height = $info['height']+1;
			$block_date = $info['date'];
			$difficulty = $info['difficulty'];
			$data = Transaction::mempool(Block::max_transactions(), false);
			$prev_block_id = $info['block'];
			$blockFound = false;

			$this->attempt = 0;

			$bl = new Block($generator, $generator, $height, null, null, $data, $difficulty, Block::versionCode($height), null, $prev_block_id);
			$bl->publicKey = $this->public_key;

			$t1 = microtime(true);
			while (!$blockFound) {
                pcntl_signal_dispatch();
				$this->attempt++;
                if($this->sleep_time == INF) {
                    $this->running = false;
                    break;
                }

                $lock_dir = ROOT."/tmp/cli-miner.lock";
                if(!file_exists($lock_dir) && !DEVELOPMENT) {
                    $this->running = false;
                    break;
                }

				$this->saveMiningStats();

                usleep($this->sleep_time * 1000);
				$now = time();
				$elapsed = $now - $block_date;
				$new_block_date = $block_date + $elapsed;
                $th = microtime(true);
				$bl->argon = $bl->calculateArgonHash($block_date, $elapsed);
				$bl->nonce=$bl->calculateNonce($block_date, $elapsed);
				$bl->date = $new_block_date;
				$hit = $bl->calculateHit();
				$target = $bl->calculateTarget($elapsed);
				$blockFound = ($hit > 0 && $target > 0 &&  $hit > $target);

                $this->measureSpeed($t1, $th);

				_log("Mining attempt={$this->attempt} height=$height difficulty=$difficulty elapsed=$elapsed hit=$hit target=$target speed={$this->speed} blockFound=$blockFound", 3);
				$this->miningStat['hashes']++;
				$mod = 10+$this->cpu;
				if($this->attempt % $mod == 0) {
					$info = $this->getMiningInfo();
					if($info!==false) {
						_log("Checking new block from server ".$info['block']. " with our block $prev_block_id", 4);
						if($info['block']!= $prev_block_id) {
							_log("New block received", 3);
							$this->miningStat['dropped']++;
							break;
						}
					}
				}

                $send_interval = 60;
                $t=time();
                $send_elapsed = $t - $start_time;
//                _log("elapsed=$elapsed hashes=".$this->miningStat['hashes']." prev_hashes=$prev_hashes");
                if($send_elapsed >= $send_interval) {
                    $start_time = time();
                    $hashes = $this->miningStat['hashes'] - $prev_hashes;
                    $this->storeStat($hashes, $height, $send_interval);
                    $prev_hashes = $this->miningStat['hashes'];
                }
			}


			if(!$blockFound) {
				continue;
			}

			$nodeScore = $_config['node_score'];
			if($nodeScore < MIN_NODE_SCORE && !DEVELOPMENT) {
				_log("Node score not ok - mining dropped");
				$this->miningStat['dropped']++;
				break;
			}

			//add reward transaction
			$rewardinfo = Block::reward($height);
			$reward = $rewardinfo['miner'] + $rewardinfo['generator'];
			$reward = num($reward);
			$reward_tx = Transaction::getRewardTransaction($generator, $new_block_date, $this->public_key, $this->private_key, $reward, "nodeminer");
			$data[$reward_tx['id']]=$reward_tx;
			if(Masternode::allowedMasternodes($height)) {
				$mn_reward_tx = Masternode::getRewardTx($generator, $new_block_date, $this->public_key, $this->private_key, $height, $mn_signature, $block_masternode);
				if (!$mn_reward_tx) {
					_log("No masternode winner - mining dropped");
					$this->miningStat['rejected']++;
					break;
				}
				$data[$mn_reward_tx['id']]=$mn_reward_tx;
				$bl->masternode = $mn_signature ? $block_masternode : null;
				$bl->mn_signature = $mn_signature;
				$fee_dst = $mn_reward_tx['dst'];
			} else{
				$fee_dst = $generator;
			}


			if($height >= STAKING_START_HEIGHT) {
				$reward = num($rewardinfo['staker']);
				$stake_reward_tx = Transaction::getStakeRewardTx($height, $generator, $this->public_key, $this->private_key, $reward, $new_block_date);
				if(!$stake_reward_tx) {
					_log("No stake winner - mining dropped");
					$this->miningStat['rejected']++;
					break;
				}
				$data[$stake_reward_tx['id']]=$stake_reward_tx;
			}

			Transaction::processFee($data, $this->public_key, $this->private_key, $fee_dst, $new_block_date, $height);
			ksort($data);

			$prev_block = Block::get($height-1);

			$bl->data = $data;
			$bl->prevBlockId = $prev_block['id'];

            if(FEATURE_SMART_CONTRACTS) {
                $schash = $bl->processSmartContractTxs($bl->height, true);
                if ($schash === false) {
                    throw new Exception("Parse block failed " . $bl->height . " Missing schash");
                }
                $bl->schash = $schash;
            }

			$bl->sign($this->private_key);
			$bl->transactions = count($bl->data);
			$this->miningStat['submits']++;


			$result = $bl->mine($err);


			if ($result) {
				$res = $bl->add($err);

				if ($res) {
					Propagate::blockToAll('current');
					_log("Block confirmed", 1);
					$this->miningStat['accepted']++;
				} else {
					_log("Block not confirmed: " . $err, 1);
					$this->miningStat['rejected']++;
				}
			} else {
				_log("Block not mined: " . $err, 1);
				$this->miningStat['rejected']++;
			}

			sleep($sleep);

			$this->saveMiningStats();

			$this->blocks++;
			if(!empty($mine_blocks) && $this->blocks >= $mine_blocks) {
				$this->running = false;
			}

		}

		_log("Miner stopped");
	}

    function storeStat($hashes, $height, $interval) {
        $data = [
            "address"=>$this->address,
            "minerid"=>$this->minerid,
            "cpu"=>$this->cpu,
            "hashes"=>$hashes,
            "height"=>$height,
            "interval"=>$interval,
            "miner_type"=>"nodeminer",
            "version"=>VERSION
        ];
        _log("Nodeminer: processMiningStat ".json_encode($data), 3);
        Nodeutil::processMiningStat($data);
    }

	function saveMiningStats() {
		if($this->miningStat['hashes'] % 100 === 0) {
			_log("Mining stats: ".json_encode($this->miningStat), 3);
			$minerStatFile = self::getStatFile();
			file_put_contents($minerStatFile, json_encode($this->miningStat));
		}
	}

	static function getStatFile() {
		$file = ROOT . "/tmp/miner_stat.json";
		return $file;
	}

	static function process() {
		global $_config;
		$peers = Peer::getCount(true);
		$force = static::hasArg("force");
		if(empty($peers) && !$force) {
			_log("No peers for miner");
			return;
		}
		if(!$_config['miner']&& !$force) {
			_log("Miner not enabled");
			return;
		}
		if(!$_config['miner_public_key']) {
			_log("Miner public key not defined");
			return;
		}
		if(!$_config['miner_private_key']) {
			_log("Miner private key not defined");
			return;
		}
		_log("Starting miner", 5);
		$miner = new NodeMiner();
		$mine_blocks = static::getArg("blocks", null);
		$res = $miner->start($mine_blocks);
		if($res === false) {
			_log("Miner failed to start");
			return;
		}
	}

	function loadMiningStats() {
		$minerStatFile = NodeMiner::getStatFile();
		if(file_exists($minerStatFile)) {
			$minerStat = file_get_contents($minerStatFile);
			$this->miningStat = json_decode($minerStat, true);
		} else {
			$this->miningStat = [
				'started'=>time(),
				'hashes'=>0,
				'submits'=>0,
				'accepted'=>0,
				'rejected'=>0,
				'dropped'=>0,
			];
		}
	}

	static function getStatus() {
		global $_config;
		$minerStatFile = NodeMiner::getStatFile();
		if(file_exists($minerStatFile)) {
			$minerStat = file_get_contents($minerStatFile);
			$minerStat = json_decode($minerStat, true);
		}
		$minerStat['address']=Account::getAddress($_config['miner_public_key']);
		return $minerStat;
	}

}
