<?php

class Miner {


	public $address;
	public $private_key;
	public $node;
	public $miningStat;
	public $cnt = 0;
	public $block_cnt = 0;
	public $cpu = 25;
    public $minerid;
    private $forked;

	private $running = true;

    private $hashing_time = 0;
    private $hashing_cnt = 0;
    private $speed;
    private $sleep_time;
    private $attempt;

    private $miningNodes = [];

	function __construct($address, $node, $forked=false)
	{
		$this->address = $address;
		$this->node = $node;
        $this->minerid = time() . uniqid();
        $this->forked = $forked;
	}

	function getMiningInfo() {
        $info = $this->getMiningInfoFromNode($this->node);
        if($info === false) {
            if(is_array($this->miningNodes) && count($this->miningNodes)>0) {
                foreach ($this->miningNodes as $node) {
                    $info = $this->getMiningInfoFromNode($node);
                    if($info !== false) {
                        return $info;
                    }
                }
            }
            return false;
        } else {
            return $info;
        }
	}

    function getMiningInfoFromNode($node) {
        $url = $node."/mine.php?q=info";
        _log("Getting info from url ". $url, 3);
        $info = url_get($url);
        if(!$info) {
            _log("Error contacting peer");
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

    function getMiningNodes() {
        _log("Get mining nodes from ".$this->node, 3);
        $url = $this->node."/mine.php?q=getMiningNodes";
        $info = url_get($url);
        if(!$info) {
            return;
        }
        $info = json_decode($info, true);
        if ($info['status'] != "ok") {
            return;
        }
        $this->miningNodes = $info['data'];
        _log("Received ".count($this->miningNodes). " mining nodes", 3);
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
    }

	function start() {
        global $argv;
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
		$this->sleep_time=(100-$this->cpu)*5;

        $this->getMiningNodes();

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

			$this->attempt = 0;

			$bl = new Block(null, $this->address, $height, null, null, $data, $difficulty, Block::versionCode($height), null, $prev_block_id);

			$t1 = microtime(true);
			$prev_elapsed = null;
			while (!$blockFound) {
				$this->attempt++;
                if($this->sleep_time == INF) {
                    $this->running = false;
                    break;
                }
		        usleep($this->sleep_time * 1000);

				$now = time();
				$elapsed = $now - $offset - $block_date;
				$new_block_date = $block_date + $elapsed;
				_log("Time=now=$now nodeTime=$nodeTime offset=$offset elapsed=$elapsed",4);
				$th = microtime(true);
				$bl->argon = $bl->calculateArgonHash($block_date, $elapsed);
				$bl->nonce=$bl->calculateNonce($block_date, $elapsed, $chain_id);
				$bl->date = $block_date;
				$hit = $bl->calculateHit();
				$target = $bl->calculateTarget($elapsed);
				$blockFound = ($hit > 0 && $target > 0 && $hit > $target);

                $this->measureSpeed($t1, $th);

				$s = "PID=".getmypid()." Mining attempt={$this->attempt} height=$height difficulty=$difficulty elapsed=$elapsed hit=$hit target=$target speed={$this->speed} submits=".
                    $this->miningStat['submits']." accepted=".$this->miningStat['accepted']. " rejected=".$this->miningStat['rejected']. " dropped=".$this->miningStat['dropped'];
                if(!$this->forked && !in_array("--flat-log", $argv)){
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

            $postData = [
                'argon' => $bl->argon,
                'nonce' => $bl->nonce,
                'height' => $height,
                'difficulty' => $difficulty,
                'address' => $this->address,
                'hit' => (string)$hit,
                'target' => (string)$target,
                'date' => $new_block_date,
                'elapsed' => $elapsed,
                'minerInfo' => 'phpcoin-miner cli ' . VERSION,
                "version" => MINER_VERSION
            ];

            $this->miningStat['submits']++;
            $res = $this->sendHash($this->node, $postData, $response);
            $accepted = false;
            if($res) {
                $accepted = true;
            } else {
                if(is_array($this->miningNodes) && count($this->miningNodes)>0) {
                    foreach ($this->miningNodes as $node) {
                        $res = $this->sendHash($node, $postData, $response);
                        if($res) {
                            $accepted = true;
                            break;
                        }
                    }
                }
            }

            if($accepted) {
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

			_log("Mining stats: ".json_encode($this->miningStat), 2);
			$minerStatFile = Miner::getStatFile();
			file_put_contents($minerStatFile, json_encode($this->miningStat));

		}

		_log("Miner stopped");
	}

    private function sendHash($node, $postData, &$response) {
        $res = url_post($node . "/mine.php?q=submitHash&", http_build_query($postData), 5);
        $response = json_decode($res, true);
        _log("Send hash to node $node response = ".json_encode($response));
        if(!isset($this->miningStat['submitted_blocks'])) {
            $this->miningStat['submitted_blocks']=[];
        }
        $this->miningStat['submitted_blocks'][]=[
            "time"=>date("r"),
            "node"=>$node,
            "height"=>$postData['height'],
            "elapsed"=>$postData['elapsed'],
            "hashes"=>$this->attempt,
            "hit"=> $postData['hit'],
            "target"=>$postData['target'],
            "status"=>@$response['status']=="ok" ? "accepted" : "rejected",
            "response"=>@$response['data']
        ];
        if (@$response['status'] == "ok") {
            return true;
        } else {
            return false;
        }
    }

	static function getStatFile() {
		$file = getcwd() . "/miner_stat.json";
		return $file;
	}

}
