<?php

class NodeMiner {


	public $public_key;
	public $private_key;
	public $miningStat;
	public $cnt = 0;

	private $running = true;

	function __construct()
	{
		global $_config;
		$this->public_key = $_config['miner_public_key'];
		$this->private_key = $_config['miner_private_key'];
	}

	function getMiningInfo() {

		$peers = Peer::getCount(true);
		_log("Getting peers count = ".$peers, 3);
		if($peers === 0 && false) {
			_log("No connected peers");
			return false;
		}
		$info = Blockchain::getMineInfo();
		return $info;
	}

	function start() {
		$this->miningStat = [
			'started'=>time(),
			'hashes'=>0,
			'submits'=>0,
			'accepted'=>0,
			'rejected'=>0,
			'dropped'=>0,
		];
		while($this->running) {
			$this->cnt++;
//			_log("Mining cnt: ".$this->cnt);

			$info = $this->getMiningInfo();
			if($info === false) {
				_log("Can not get mining info");
				return false;
			}

			$_config = Nodeutil::getConfig();

			if (Config::isSync()) {
				_log("Sync in process - stop miner");
				return false;
			}

			$peersCount = Peer::getCount();
			if($peersCount < 3 && !DEVELOPMENT) {
				_log("Not enough node peers");
				return false;
			}

			$nodeScore = $_config['node_score'];
			if($nodeScore < MIN_NODE_SCORE) {
				_log("Node score not ok");
				return false;
			}

			$generator = Account::getAddress($this->public_key);
			$height = $info['height']+1;
			$block_date = $info['date'];
			$difficulty = $info['difficulty'];
			$data = $info['data'];
			$prev_block_id = $info['block'];
			$blockFound = false;

			$attempt = 0;

			$bl = new Block($generator, $generator, $height, null, null, $data, $difficulty, Block::versionCode($height), null, $prev_block_id);
			$bl->publicKey = $this->public_key;

			$t1 = microtime(true);
			$cpu = isset($_config['miner_cpu']) ? $_config['miner_cpu'] : 0;
			while (!$blockFound) {
				$attempt++;
				usleep((100-$cpu) * 5 * 1000);
				$this->checkRunning();
				if(!$this->running) {
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
			if($nodeScore < MIN_NODE_SCORE) {
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
			$mn_reward_tx = Masternode::getRewardTx($generator, $new_block_date, $this->public_key, $this->private_key, $height, $mn_signature);
			if($mn_reward_tx) {
				$data[$mn_reward_tx['id']]=$mn_reward_tx;
				if($mn_reward_tx['dst']!=$generator) {
					$bl->masternode = $mn_reward_tx['dst'];
					$bl->mn_signature = $mn_signature;
				}
			}

			ksort($data);

			$prev_block = Block::get($height-1);

			$bl->data = $data;
			$bl->prevBlockId = $prev_block['id'];

			$bl->sign($this->private_key);

			$this->miningStat['submits']++;


			$result = $bl->mine();


			if ($result) {
				$res = $bl->add();

				if ($res) {
					$current = Block::current();
					$current['id']=escapeshellarg(san($current['id']));
					$dir = ROOT."/cli";
					$cmd = "php ".XDEBUG_CLI." $dir/propagate.php block {$current['id']}  > /dev/null 2>&1  &";
					_log("Call propagate " . $cmd, 4);
					shell_exec($cmd);
					_log("Block confirmed", 1);
					$this->miningStat['accepted']++;
				} else {
					_log("Block not confirmed: " . $res, 1);
					$this->miningStat['rejected']++;
				}
			}

			sleep(3);

			_log("Mining stats: ".json_encode($this->miningStat), 3);
			$minerStatFile = self::getStatFile();
			file_put_contents($minerStatFile, json_encode($this->miningStat));

		}

		_log("Miner stopped");
	}

	function checkRunning() {
		$this->running = file_exists(MINER_LOCK_PATH);
	}

	static function getStatFile() {
		$file = ROOT . "/tmp/miner_stat.json";
		return $file;
	}

}
