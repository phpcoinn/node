<?php

class Miner {


	public $address;
	public $private_key;
	public $node;
	public $miningStat;
	public $cnt = 0;
	public $block_cnt = 0;
	public $cpu = 0;
    public $minerid;
    private $forked;

	private $running = true;

	function __construct($address, $node, $forked=false)
	{
		$this->address = $address;
		$this->node = $node;
        $this->minerid = time() . uniqid();
        $this->forked = $forked;
	}

	function getMiningInfo() {

		$url = $this->node."/mine.php?q=info&".XDEBUG;
		_log("Getting info from url ". $url, 3);
		$info = url_get($url);
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

    function sendStat($hashes, $height, $interval) {
        $postData = http_build_query([
            "address"=>$this->address,
            "minerid"=>$this->minerid,
            "cpu"=>$this->cpu,
            "hashes"=>$hashes,
            "height"=>$height,
            "interval"=>$interval,
            "miner_type"=>"cli",
            'minerInfo'=>'phpcoin-miner cli ' . VERSION,
            "version"=>MINER_VERSION
        ]);
        $res = url_post($this->node . "/mine.php?q=submitStat&", $postData);
    }

	function checkAddress() {
		$url = $this->node."/mine.php?q=checkAddress";
		$postdata = http_build_query([
			"address"=>$this->address
		]);
		$res = url_post($url, $postdata);
		$data = json_decode($res, true);
		if ($data['status'] == "ok" && $data['data']==$this->address) {
			return true;
		}
		return false;
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
        $start_time = time();
        $prev_hashes = null;
		while($this->running) {
			$this->cnt++;
//			_log("Mining cnt: ".$this->cnt);

			$info = $this->getMiningInfo();
			if($info === false) {
				_log("Can not get mining info", 0);
				sleep(3);
				continue;
			}

			if(!isset($info['data']['generator'])) {
				_log("Miner node does not send generator address");
				sleep(3);
				continue;
			}

			if(!isset($info['data']['ip'])) {
				_log("Miner node does not send ip address ",json_encode($info));
				sleep(3);
				continue;
			}

			$ip = $info['data']['ip'];
			if(!Peer::validateIp($ip)) {
				_log("Miner does not have valid ip address: $ip");
				sleep(3);
				continue;
			}

			if(!$this->checkAddress() && false) {
				_log("Miner is not allowed to mine to address from this ip");
				sleep(3);
				continue;
			}

			$height = $info['data']['height']+1;
			$block_date = $info['data']['date'];
			$difficulty = $info['data']['difficulty'];
			$reward = $info['data']['reward'];
			$data = [];
			$nodeTime = $info['data']['time'];
			$prev_block_id = $info['data']['block'];
            $chain_id=$info['data']['chain_id'];
			$blockFound = false;


			$now = time();
			$offset = $nodeTime - $now;

			$attempt = 0;

			$bl = new Block(null, $this->address, $height, null, null, $data, $difficulty, Block::versionCode($height), null, $prev_block_id);

			$t1 = microtime(true);
			$prev_elapsed = null;
			while (!$blockFound) {
				$attempt++;
				usleep((100-$this->cpu) * 5 * 1000);
				$now = time();
				$elapsed = $now - $offset - $block_date;
				$new_block_date = $block_date + $elapsed;
				_log("Time=now=$now nodeTime=$nodeTime offset=$offset elapsed=$elapsed",4);
				$bl->argon = $bl->calculateArgonHash($block_date, $elapsed);
				$bl->nonce=$bl->calculateNonce($block_date, $elapsed, $chain_id);
				$bl->date = $block_date;
				$hit = $bl->calculateHit();
				$target = $bl->calculateTarget($elapsed);
				$blockFound = ($hit > 0 && $target > 0 && $hit > $target);

				$t2 = microtime(true);
				$diff = $t2 - $t1;
				$speed = round($attempt / $diff,2);

				$s = "PID=".getmypid()." Mining attempt=$attempt height=$height difficulty=$difficulty elapsed=$elapsed hit=$hit target=$target speed=$speed submits=".
                    $this->miningStat['submits']." accepted=".$this->miningStat['accepted']. " rejected=".$this->miningStat['rejected']. " dropped=".$this->miningStat['dropped'];
                if(!$this->forked){
                    echo "$s \r";
                } else {
                    echo $s. PHP_EOL;
                }
				$this->miningStat['hashes']++;
				if($prev_elapsed != $elapsed && $elapsed % 10 == 0) {
					$prev_elapsed = $elapsed;
					$info = $this->getMiningInfo();
					if($info!==false) {
						_log("Checking new block from server ".$info['data']['block']. " with our block $prev_block_id", 4);
						if($info['data']['block']!= $prev_block_id) {
							_log("New block received", 2);
							$this->miningStat['dropped']++;
							break;
						}
					}
				}
                $send_interval = 60;
                $t=time();
                $elapsed_send = $t - $start_time;
                if($elapsed_send >= $send_interval) {
                    $start_time = time();
                    $hashes = $this->miningStat['hashes'] - $prev_hashes;
                    $prev_hashes = $this->miningStat['hashes'];
                    $this->sendStat($hashes, $height, $send_interval);
                }
			}

			if(!$blockFound || $elapsed <=0) {
				continue;
			}

			$postData = http_build_query(
				[
					'argon' => $bl->argon,
					'nonce' => $bl->nonce,
					'height' => $height,
					'difficulty' => $difficulty,
					'address' => $this->address,
					'date'=> $new_block_date,
					'elapsed' => $elapsed,
					'minerInfo'=>'phpcoin-miner cli ' . VERSION,
                    "version"=>MINER_VERSION
				]
			);

			$res = url_post($this->node . "/mine.php?q=submitHash&" . XDEBUG, $postData);


			$this->miningStat['submits']++;
			$data = json_decode($res, true);
			if ($data['status'] == "ok") {
				_log("Block confirmed", 1);
				$this->miningStat['accepted']++;
			} else {
				_log("Block not confirmed: " . $res, 1);
				$this->miningStat['rejected']++;
			}

            if(!isset($this->miningStat['submitted_blocks'])) {
                $this->miningStat['submitted_blocks']=[];
            }
            $this->miningStat['submitted_blocks'][]=[
                "time"=>date("r"),
                "height"=>$height,
                "elapsed"=>$elapsed,
                "hashes"=>$attempt,
                "hit"=>(string) $hit,
                "target"=>(string) $target,
                "status"=>$data['status']=="ok" ? "accepted" : "rejected",
                "response"=>$data['data']
            ];

			sleep(3);

			if($this->block_cnt > 0 && $this->cnt >= $this->block_cnt) {
				break;
			}

			_log("Mining stats: ".json_encode($this->miningStat), 2);
			$minerStatFile = Miner::getStatFile();
			file_put_contents($minerStatFile, json_encode($this->miningStat));

		}

		_log("Miner stopped");
	}

	static function getStatFile() {
		$file = getcwd() . "/miner_stat.json";
		return $file;
	}

}
