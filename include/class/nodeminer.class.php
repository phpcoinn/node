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
		_log("Getting peers count = ".$peers);
		if($peers === 0 && false) {
			_log("No connected peers");
			return false;
		}
		$info = Blockchain::getMineInfo();
		return $info;
	}

	function start() {
		$block = new Block();
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
				_log("Can not get mining info", 0);
				return false;
			}

			$_config = Nodeutil::getConfig();

			if (isset($_config['sync']) && $_config['sync'] == 1) {
				_log("Sync in process - stop miner");
				return false;
			}

			$peersCount = Peer::getCount();
			if($peersCount < 3) {
				_log("Not enough node peers");
				return false;
			}

			$nodeScore = $_config['node_score'];
			if($nodeScore != 100) {
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

			while (!$blockFound) {
				$attempt++;
				usleep(500 * 1000);
				$this->checkRunning();
				$now = time();
				$elapsed = $now - $block_date;
				$new_block_date = $block_date + $elapsed;
				_log("Time=now=$now elapsed=$elapsed",4);
				$argon = null;
				$nonce = Block::calculateNonce($generator, $block_date, $elapsed, $argon);
				$hit = $block->calculateHit($nonce, $generator, $height, $difficulty);
				$target = $block->calculateTarget($difficulty, $elapsed);
				$blockFound = ($hit > 0 && $target>=0 &&  $hit > $target);
				_log("Mining attempt=$attempt height=$height difficulty=$difficulty elapsed=$elapsed hit=$hit target=$target blockFound=$blockFound", 3);
				$this->miningStat['hashes']++;
				if($attempt % 10 == 0) {
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
			if($nodeScore != 100) {
				_log("Node score not ok - mining dropped");
				$this->miningStat['dropped']++;
				break;
			}

			//add reward transaction
			$rewardinfo = Block::reward($height);
			$reward = $rewardinfo['miner'] + $rewardinfo['generator'];
			$reward = num($reward);
			$reward_tx = Transaction::getRewardTransaction($generator, $new_block_date, $this->public_key, $this->private_key, $reward);
			$data[$reward_tx['id']]=$reward_tx;
			ksort($data);

			$signature = $block->sign($generator, $generator, $height, $new_block_date, $nonce, $data, $this->private_key, $difficulty, $argon, $prev_block_id);

			$this->miningStat['submits']++;

			$prev_block = $block->get($height-1);
			$date = $prev_block['date']+$elapsed;
			$result = $block->mine($this->public_key, $generator, $nonce, $argon, $difficulty, $signature, $height, $date);


			$miner = Account::getAddress($this->public_key);
			if ($result) {

				$res = $block->add(
					$height,
					$this->public_key,
					$miner,
					$nonce,
					$data,
					$date,
					$signature,
					$difficulty,
					$argon,
					$prev_block['id']
				);

				if ($res) {
					$current = $block->current();
					$current['id']=escapeshellarg(san($current['id']));
					$dir = ROOT."/cli";
					$cmd = "php ".XDEBUG_CLI." $dir/propagate.php block {$current['id']}  > /dev/null 2>&1  &";
					_log("Call propagate " . $cmd);
					shell_exec($cmd);
					_log("Block confirmed", 1);
					$this->miningStat['accepted']++;
				} else {
					_log("Block not confirmed: " . $res, 1);
					$this->miningStat['rejected']++;
				}
			}

			sleep(3);

			_log("Mining stats: ".json_encode($this->miningStat), 1);
			$minerStatFile = self::getStatFile();
			file_put_contents($minerStatFile, json_encode($this->miningStat));

		}

		_log("Miner stopped", 0);
	}

	function checkRunning() {
		$this->running = file_exists(MINER_LOCK_PATH);
	}

	static function getStatFile() {
		$file = ROOT . "/tmp/miner_stat.json";
		return $file;
	}

}
