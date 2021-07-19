<?php

class Miner {


	public $public_key;
	public $private_key;
	public $node;
	public $miningStat;
	public $cnt = 0;
	public $miningPeerFn = null;
	public $checkRunningFn = null;
	public $block_cnt = 0;

	private $running = true;

	function __construct($public_key, $private_key, $node)
	{
		$this->public_key = $public_key;
		$this->private_key = $private_key;
		$this->node = $node;
	}

	function getMiningInfo() {

		if($this->miningPeerFn !== null) {
			return call_user_func($this->miningPeerFn, $this);
		}
		$url = $this->node."/mine.php?q=info&".XDEBUG;
		_log("Getting info from url ". $url, 3);
		$info = @file_get_contents($url);
		if(!$info) {
			_log("Error contacting peer");
			return false;
		}
		if(!$info) {
			_log("Can not retrieve mining info");
			return false;
		}
		_log("Received mining info: ".$info, 3);
		$info = json_decode($info, true);
		if ($info['status'] != "ok") {
			_log("Wrong status for node: ".json_encode($info));
			return false;
		}
		return $info;
	}

	function start() {
		global $_config;
		$acc = new Account();
		$block = new Block();
		$tx = new Transaction();
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

			if (isset($_config['sanity_sync']) && $_config['sanity_sync'] == 1) {
				_log("Sync in process - stop miner");
				return false;
			}

			$generator = Account::getAddress($this->public_key);
			$height = $info['data']['height']+1;
			$block_date = $info['data']['date'];
			$difficulty = $info['data']['difficulty'];
			$reward = $info['data']['reward'];
			$data = $info['data']['data'];
			$nodeTime = $info['data']['time'];
			$prev_block_id = $info['data']['block'];
			$blockFound = false;

			$now = time();
			$offset = $nodeTime - $now;

			$attempt = 0;

			while (!$blockFound) {
				$attempt++;
				usleep(500 * 1000);
				$this->checkRunning();
				$now = time();
				$elapsed = $now - $offset - $block_date;
				$new_block_date = $block_date + $elapsed;
				_log("Time=now=$now nodeTime=$nodeTime offset=$offset elapsed=$elapsed",4);
				$argon = null;
				$nonce = Block::calculateNonce($generator, $block_date, $elapsed, $argon);
				$hit = $block->calculateHit($nonce, $this->public_key, $height, $difficulty, $new_block_date, $elapsed);
				$target = $block->calculateTarget($difficulty, $elapsed);
				$blockFound = ($hit > 0 && $target>=0 &&  $hit > $target);
				_log("Mining attempt=$attempt height=$height difficulty=$difficulty elapsed=$elapsed hit=$hit target=$target blockFound=$blockFound", 3);
				$this->miningStat['hashes']++;
				if($attempt % 10 == 0) {
					$info = $this->getMiningInfo();
					if($info!==false) {
						_log("Checking new block from server ".$info['data']['block']. " with our block $prev_block_id", 4);
						if($info['data']['block']!= $prev_block_id) {
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

			//add reward transaction
			$reward_tx = $tx->getRewardTransaction($generator, $new_block_date, $this->public_key, $this->private_key, $reward);
			$data[$reward_tx['id']]=$reward_tx;
			ksort($data);
			$signature = $block->sign($generator, $height, $new_block_date, $nonce, $data, $this->private_key, $difficulty, $argon, $prev_block_id);
			$postData = http_build_query(
				[
					'argon' => $argon,
					'nonce' => $nonce,
					'height' => $height,
					'difficulty' => $difficulty,
					'public_key' => $this->public_key,
					'signature' => $signature,
					'elapsed' => $elapsed,
					'data' => json_encode($data)
				]
			);
			$opts = [
				'http' =>
					[
						'method' => 'POST',
						'header' => 'Content-type: application/x-www-form-urlencoded',
						'content' => $postData,
					],
			];
			$context = stream_context_create($opts);
			$res = file_get_contents($this->node . "/mine.php?q=submitBlock&" . XDEBUG, false, $context);
			$this->miningStat['submits']++;
			$data = json_decode($res, true);
			if ($data['status'] == "ok") {
				_log("Block confirmed", 1);
				$this->miningStat['accepted']++;
			} else {
				_log("Block not confirmed: " . $res, 1);
				$this->miningStat['rejected']++;
			}

			sleep(3);

			if($this->block_cnt > 0 && $this->cnt >= $this->block_cnt) {
				break;
			}

			_log("Mining stats: ".json_encode($this->miningStat), 1);
			$minerStatFile = Miner::getStatFile();
			file_put_contents($minerStatFile, json_encode($this->miningStat));

		}

		_log("Miner stopped", 0);
	}

	function checkRunning() {
		if($this->checkRunningFn !== null) {
			$this->running = call_user_func($this->checkRunningFn);
		}
	}

	static function getStatFile() {
		$network = getenv("NETOWRK");
		if(!empty($network)) {
			$file = ROOT . "/tmp/$network.miner_stat.json";
		} else {
			$file = ROOT . "/tmp/miner_stat.json";
		}
		return $file;
	}

}
