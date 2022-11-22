<?php

class NodeMiner extends Daemon {

	static $name = "miner";
	static $title = "Miner";

	static $max_run_time = 60 * 60;
	static $run_interval = 5;

	public $public_key;
	public $private_key;
	public $miningStat;
	public $cnt = 0;
	public $blocks = 0;

	private $running = true;

	function __construct()
	{
		global $_config;
		$this->public_key = $_config['miner_public_key'];
		$this->private_key = $_config['miner_private_key'];
	}

	function getMiningInfo() {

		$info = Blockchain::getMineInfo();
		return $info;
	}

	function start($mine_blocks = null, $sleep = 3) {

		$this->loadMiningStats();

		while($this->running) {
			$this->cnt++;
//			_log("Mining cnt: ".$this->cnt);

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

			$attempt = 0;

			$bl = new Block($generator, $generator, $height, null, null, $data, $difficulty, Block::versionCode($height), null, $prev_block_id);
			$bl->publicKey = $this->public_key;

			$t1 = microtime(true);
			$cpu = isset($_config['miner_cpu']) ? $_config['miner_cpu'] : 0;
			while (!$blockFound) {
				$attempt++;

				$this->saveMiningStats();

				usleep((100-$cpu) * 5 * 1000);
				$this->checkRunning();
				if(!$this->running) {
					_log("Stop miner because missing lock file");
					break;
				}
				$now = time();
				$elapsed = $now - $block_date;
				$new_block_date = $block_date + $elapsed;

				$bl->argon = $bl->calculateArgonHash($block_date, $elapsed);
				$bl->nonce=$bl->calculateNonce($block_date, $elapsed);
				$bl->date = $new_block_date;
				$hit = $bl->calculateHit();
				$target = $bl->calculateTarget($elapsed);
				$blockFound = ($hit > 0 && $target > 0 &&  $hit > $target);

				$t2 = microtime(true);
				$diff = $t2 - $t1;
				$speed = round($attempt / $diff,2);

				_log("Mining attempt=$attempt height=$height difficulty=$difficulty elapsed=$elapsed hit=$hit target=$target speed=$speed blockFound=$blockFound", 3);
				$this->miningStat['hashes']++;
				$mod = 10+$cpu;
				if($attempt % $mod == 0) {
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
				$mn_reward_tx = Masternode::getRewardTx($generator, $new_block_date, $this->public_key, $this->private_key, $height, $mn_signature);
				if (!$mn_reward_tx) {
					_log("No masternode winner - mining dropped");
					$this->miningStat['rejected']++;
					break;
				}
				$data[$mn_reward_tx['id']]=$mn_reward_tx;
				$bl->masternode = $mn_signature ? $mn_reward_tx['dst'] : null;
				$bl->mn_signature = $mn_signature;
				$fee_dst = $mn_reward_tx['dst'];
			} else{
				$fee_dst = $generator;
			}


			Transaction::processFee($data, $this->public_key, $this->private_key, $fee_dst, $new_block_date, $height);
			ksort($data);

			$prev_block = Block::get($height-1);

			$bl->data = $data;
			$bl->prevBlockId = $prev_block['id'];

			$bl->sign($this->private_key);
			$bl->transactions = count($bl->data);
			$this->miningStat['submits']++;


			$result = $bl->mine($err);


			if ($result) {
				$res = $bl->add($err);

				if ($res) {
					$current = Block::current();
					Propagate::blockToAll($current['id']);
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

	function saveMiningStats() {
		if($this->miningStat['hashes'] % 100 === 0) {
			_log("Mining stats: ".json_encode($this->miningStat), 3);
			$minerStatFile = self::getStatFile();
			file_put_contents($minerStatFile, json_encode($this->miningStat));
		}
	}

	function checkRunning() {
		$this->running = file_exists(static::getLockFile());
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
