<?php

class Masternode extends Task
{

	static $name = "masternode";
	static $title = "Masternode";

	static $run_interval = 30;

	public $id;
	public $public_key;
	public $height;
	public $win_height;
	public $signature;
	public $ip;
	public $collateral;
	public $verified;

	function __construct()
	{

	}

	function sign($height, $private_key) {
		$base = self::getSignatureBase($this->public_key, $height);
		$this->signature = ec_sign($base, $private_key);
		return $this->signature;
	}

	function verify($height, &$error = null) {
		try {
			$this->verified = 0;
			$base = self::getSignatureBase($this->public_key, $height);
			$chain_id = Block::getChainId($height);
			$res = ec_verify($base, $this->signature, $this->public_key, $chain_id);
			if(!$res) {
				throw new Exception("Masternode signature not valid");
			}
			$collateral = Block::getMasternodeCollateral($height);
			_log("Masternode: verify collateral mn=".$this->collateral." valid=".$collateral, 5);
			if($this->collateral != $collateral) {
				throw new Exception("Masternode collateral not valid: expected=$collateral received=".$this->collateral. " version=".PeerRequest::$info['version']. " ip=".PeerRequest::$ip);
			}
			$this->verified = 1;
		} catch (Exception $e) {
			$error = $e->getMessage();
			_log("Error: $error", 5);
			return false;
		}
		return true;
	}

	static function getCountByCollateral($collateral) {
		global $db;
		$sql="select count(*) from masternode m where m.collateral = :collateral";
		$cnt = $db->single($sql, [":collateral"=>$collateral]);
		return $cnt;
	}

	static function getSignatureBase($public_key, $win_height) {
		$parts = [];
		$address = Account::getAddress($public_key);
		$parts[]=$address;
		$parts[]=$win_height;
		if($win_height > UPDATE_9_ADD_MN_COLLATERAL_TO_SIGNATURE) {
			$parts[]=Block::getMasternodeCollateral($win_height);
		}
		$base = implode("-", $parts);
		_log("Masternode: signature base=$base", 5);
		return $base;
	}

	function storeSignature() {
		global $db;
		$sql = "update masternode set signature = :signature, verified=:verified where public_key = :public_key";
		$db->run($sql, [":signature" => $this->signature, ":public_key"=>$this->public_key, ":verified" => $this->verified]);
	}

	function storeWinHeight() {
		global $db;
		$sql = "update masternode set win_height = :win_height where public_key = :public_key";
		$res = $db->run($sql, [":win_height" => $this->win_height, ":public_key"=>$this->public_key]);
		return $res;
	}

	static function fromDB($row) {
		$masternode = new Masternode();
		$masternode->public_key = $row['public_key'];
		$masternode->height = $row['height'];
		$masternode->win_height = $row['win_height'];
		$masternode->signature = $row['signature'];
		$masternode->id = $row['id'];
		$masternode->ip = $row['ip'];
		$masternode->collateral = $row['collateral'];
		$masternode->verified = $row['verified'];
		return $masternode;
	}

	static function existsPublicKey($publicKey) {
		global $db;
		$sql="select count(*) from masternode m where m.public_key =:public_key";
		$res = $db->single($sql, [":public_key"=>$publicKey]);
		return $res > 0;
	}

	static function existsAddress($address) {
		global $db;
		$sql="select count(*) from masternode m where m.id =:address";
		$res = $db->single($sql, [":address"=>$address]);
		return $res > 0;
	}

	function add() {
		global $db;
		$sql="insert into masternode (id, public_key, height, signature, win_height, collateral) 
			values (:id, :public_key, :height, :signature, :win_height, :collateral)";
		$res = $db->run($sql, [
			":id" => $this->id,
			":public_key" => $this->public_key,
			":height"=>$this->height,
			":signature"=>$this->signature,
			":win_height"=>$this->win_height,
			":collateral"=>$this->collateral,
		]);
		return $res;
	}

	function update() {
		global $db;
		_log("Masternode update win_height=".$this->win_height." public_key=".$this->public_key." verified=".$this->verified, 5);
		$sql="update masternode set height=:height,  signature=:signature, win_height=:win_height, ip=:ip , verified=:verified
			where public_key=:public_key";
		$res = $db->run($sql, [
			":public_key" => $this->public_key,
			":height"=>$this->height,
			":signature"=>$this->signature,
			":win_height"=>$this->win_height,
			":ip"=>$this->ip,
			":verified"=>$this->verified
		]);
		return $res;
	}

	static function getAll() {
		global $db;
		return $db->run("select * from masternode");
	}

	static function getAllSorted() {
		global $db;
		return $db->run("select * from masternode order by win_height desc");
	}

	static function getForBroadcast($limit = 5) {
		global $db;
		return $db->run("select * from masternode order by rand() limit :limit ", [":limit"=>$limit]);
	}

	static function getCount() {
		global $db;
		$sql="select count(1) from masternode";
		return $db->single($sql);
	}

    static function getVerifiedCount() {
        global $db;
        $sql="select count(1) from masternode where verified = 1";
        return $db->single($sql);
    }

	static function getCountForCollateral($collateral, $height) {
		global $db;
		$sql="select count(1) from masternode where collateral = :collateral and height < :height";
		return $db->single($sql, [":collateral"=>$collateral, ":height"=>$height]);
	}

	static function getActiveCount() {
		global $db;
		$sql="select count(1) from masternode m where m.signature is not null";
		return $db->single($sql);
	}

	static function delete($publicKey) {
		global $db;
		$sql="delete from masternode where public_key=:public_key";
		$res = $db->run($sql, [":public_key"=>$publicKey]);
		return $res;
	}

	static function get($publicKey) {
		global $db;
		$sql="select * from masternode where public_key=:public_key";
		$res=$db->run($sql, [":public_key"=>$publicKey]);
		return @$res[0];
	}

	static function create($publicKey, $height, $collateral=null) {
		$mn = new Masternode();
		$mn->id = Account::getAddress($publicKey);
		$mn->public_key = $publicKey;
		$mn->height = $height;
		$mn->signature = null;
		$mn->win_height = Masternode::getLastWinHeight($mn->id);
		if(empty($collateral)) {
			$collateral = Block::getMasternodeCollateral($height);
		}
		$mn->collateral = $collateral;
		$res = $mn->add();
		return $res;
	}

	static function getMnCreateTx($mn_address) {
		global $db;
		$sql="select * from transactions where ((dst =:dst and (message ='mncreate' or message='')) or message =:dst2) and type=:type order by height desc limit 1";
		$rows = $db->run($sql, [":dst"=>$mn_address, ":dst2"=>$mn_address, ":type"=>TX_TYPE_MN_CREATE]);
		return $rows[0];
	}

	static function getWinner($height) {
		global $db, $_config;

		$local_id = null;
		if(Masternode::isLocalMasternode()) {
			$publicKey = $_config['masternode_public_key'];
			$local_id = Account::getAddress($publicKey);
		}

		$collateral = Block::getMasternodeCollateral($height);
		$sql = "select m.*, 
			masternode_height.last_win_height
			from masternode m
			left join (
			    select b.masternode, max(b.height) as last_win_height
			                             from blocks b
			where b.masternode is not null
			group by b.masternode
			    ) as masternode_height on (masternode_height.masternode = m.id)
			where m.height <= :height and m.collateral = :collateral and m.verified = 1
			group by m.id, m.signature, m.public_key, masternode_height.last_win_height
			order by masternode_height.last_win_height, md5(m.signature), m.height";
		$rows = $db->run($sql, [":height"=>$height, ":collateral"=>$collateral]);
		foreach($rows as $row) {
			$mn = Masternode::fromDB($row);
			if(!empty($local_id) && $local_id == $mn->id) {
				continue;
			}
			if($mn->verify($height)) {
				_log("Masternode: found mn winner ".$mn->id);
				return $row;
			}
		}
		return null;
	}


	static function updateWinner($height, $public_key) {
		global $db;
		$sql="update masternode set win_height = :height where public_key = :public_key";
		$res = $db->run($sql, [":height"=>$height, ":public_key" => $public_key]);
		return $res;
	}
	
	static function getStucked($height) {
		global $db;
		$sql = "select * from masternode where win_height < :height";
		$rows = $db->run($sql, [":height"=>$height]);
		return $rows;
	}

	static function getLastWinHeight($id, $height=null) {
		global $db;
		if($height==null) {
			$height = Block::getHeight();
		}
		$sql= "select max(height) from blocks where masternode = :id and height <= :height";
		return $db->single($sql, [":id"=>$id, ":height"=>$height]);
	}

	static function getMasternodeHeight($id, $height) {
		global $db;

        $sql="select max(height) from (
            select max(height) as height from transactions t where t.type = 2 and t.height <= $height
            and t.dst = '$id' and (t.message='mncreate' or t.message='')
            union
            (select max(height) as height from transactions t where t.type = 2 and t.height <= $height
            and t.message = '$id')) as heights";
        $res = $db->single($sql);
        return $res;

	}

	static function allowedMasternodes($height) {
		return FEATURE_MN && $height >= Block::getMnStartHeight();
	}

	static function checkCreateMasternodeTransaction($height, Transaction $transaction, &$error, $verify=true) {

		try {

			//masternodes must be allowed and height after start height
			if (!self::allowedMasternodes($height)) {
				throw new Exception("Not allowed transaction type {$transaction->type} for height $height");
			}
			if(!$verify) {
				//source address can not be masternode
				$res = Masternode::existsPublicKey($transaction->publicKey);
				if ($res) {
					throw new Exception("Source public key {$transaction->publicKey} is already a masternode");
				}

				//destionation address must be verified
				$dst = $transaction->dst;
				$dstPublicKey = Account::publicKey($dst);
				if (!$dstPublicKey && !in_array($height, MN_CREATE_IGNORE_HEIGHT)) {
					throw new Exception("Destination address $dst is not verified!");
				}
				//destionation address must not be masternode
				$res = Masternode::existsPublicKey($dstPublicKey);
				if ($res) {
					throw new Exception("Destination address $dst is already a masternode");
				}
                if($height >= MN_COLD_START_HEIGHT) {
                    $msg = $transaction->msg;
                    if(Account::valid($msg)) {
                        $masternodeAddr = $msg;
                        $res = Masternode::existsAddress($masternodeAddr);
                        if ($res) {
                            throw new Exception("Masternode address $dst is already a masternode");
                        }
                        $mnPublicKey = Account::publicKey($masternodeAddr);
                        if (!$mnPublicKey) {
                            throw new Exception("Masternode address $masternodeAddr is not verified!");
                        }
                    }
                }
			} else {
                $masternodeAddr = $transaction->dst;
                if($height >= MN_COLD_START_HEIGHT) {
                    $msg = $transaction->msg;
                    if(!empty($msg) && Account::valid($msg)) {
                        $masternodeAddr = $msg;
                    }
                }
                $res = Masternode::checkExistsMasternode($masternodeAddr, $transaction->mempool ? $height : $height-1);
                if($res) {
                    throw new Exception("Masternode already created");
                }
            }
			//masternode collateral must be exact
			$collateral = Block::getMasternodeCollateral($height);
			if($transaction->val != $collateral) {
				throw new Exception("Invalid masternode collateral {$transaction->val}, must be ".$collateral);
			}
			return true;
		} catch (Exception $e) {
			$error = $e->getMessage();
			_log("Error in masternode create tx: ".$error);
			return false;
		}
	}

	static function checkRemoveMasternodeTransaction($height, Transaction $transaction, &$error, $verify=true) {

		try {
			//masternodes must be allowed and height after start height
			if(!Masternode::allowedMasternodes($height)) {
				throw new Exception("Not allowed transaction type {$transaction->type} for height $height");
			}
			if(!$verify) {
				//masternode must exists in database
				$masternode = Masternode::get($transaction->publicKey);
				if(!$masternode) {
                    if($height > MN_COLD_START_HEIGHT) {
                        $src = $transaction->src;
                        $masternodes=Account::getMasternodeRewardAddress($src);
                        if(!$masternodes) {
                            throw new Exception("Can not find cold masternode for address ".$transaction->src);
                        }
                        foreach($masternodes as $mn) {
                            if($mn['masternode']==$transaction->msg) {
                                $masternode = $mn;
                                break;
                            }
                        }
                        if(!$masternode) {
                            throw new Exception("Can not find cold masternode for address ".$transaction->src);
                        }
                    } else {
					    throw new Exception("Can not find masternode with public key ".$transaction->publicKey);
				    }
				}
				//masternode must run minimal number of blocks
				$collateral_changed = $masternode['collateral'] != Block::getMasternodeCollateral($height);
				if($height < $masternode['height'] + MN_MIN_RUN_BLOCKS && !$collateral_changed) {
					throw new Exception("Masternode must run at least ".MN_MIN_RUN_BLOCKS." blocks. Created at block ". $masternode['height']. " check block=".$height);
				}
				//destination address can not be masternode
				$dst = $transaction->dst;
				$dstPublicKey = Account::publicKey($dst);
				if($dstPublicKey) {
					$res = Masternode::existsPublicKey($dstPublicKey);
					if($res) {
						throw new Exception("Destination address $dst can not be masternode");
					}
				}
				//masternode collateral must be exact as when created
				$collateral = $masternode['collateral'];
				if($transaction->val != $collateral) {
					throw new Exception("Invalid masternode collateral {$transaction->val}, must be ".$collateral);
				}
			}
			return true;

		} catch (Exception $e) {
			$error = $e->getMessage();
			_log("Error in masternode remove tx: ".$error);
			return false;
		}



	}

	static function checkIsSendFromMasternode($height, Transaction $transaction, &$error, $verify) {
		try {

            global $db;

            $checkHeight = $verify ? $height-1 : $height;
            $checkAddress = $transaction->src;

            $sql="select * from transactions t where (t.dst = :id) and t.type = :type
			and t.height <= :height";
            $createTxs = $db->run($sql, [":id"=>$checkAddress, ":type"=>TX_TYPE_MN_CREATE, ":height"=>$checkHeight]);
            $sql="select *  from transactions t where (t.src = :id) and t.type = :type
			and t.height <= :height";
            $removeTxs = $db->run($sql, [":id"=>$checkAddress, ":type"=>TX_TYPE_MN_REMOVE, ":height"=>$checkHeight]);

            if(count($createTxs) - count($removeTxs) > 0) {
                $masternode_existing = true;
            } else {
                $masternode_existing = false;
            }

            if($masternode_existing) {

                $lockedCollateral = 0;
                foreach($createTxs as $tx) {
                    $lockedCollateral+=floatval($tx['val']);
                }
                foreach($removeTxs as $tx) {
                    $lockedCollateral-=floatval($tx['val']);
                }

                $total_sent = Transaction::getTotalSent($checkAddress, $checkHeight);
                $total_received = Transaction::getTotalReceived($checkAddress, $checkHeight);
                $balance = floatval($total_received) - floatval($total_sent);
                $mempool_balance = 0;
                if(!$verify) {
                    $mempool_balance = floatval(Mempool::mempoolBalance($checkAddress,$transaction->id));
                }
                $remain = $balance - $lockedCollateral - floatval($transaction->val) + $mempool_balance;
                if(round($remain,8) < 0) {
                    throw new Exception("Can not spent more than collateral. Locked=$lockedCollateral Balance=$balance amount=".$transaction->val);
                }
            }

			return true;
		} catch (Exception $e) {
			$error = $e->getMessage();
			_log("Error in  send tx: ".$error);
			return false;
		}
	}

	static function isLocalMasternode() {
		global $_config;
		if(isset($_config['masternode']) && $_config['masternode']==true && isset($_config['masternode_public_key']) && isset($_config['masternode_private_key'])) {
			return true;
		} else {
			return false;
		}
	}

	static function checkLocalMasternode() {
		global $_config, $db;
		if(!self::isLocalMasternode()) {
			_log("Masternode: Local masternode not configured");
			return;
		}
		$publicKey = $_config['masternode_public_key'];
		$localMasternode = Masternode::get($publicKey);
		if($localMasternode) {
			_log("Masternode: Local masternode already exists", 4);
			return;
		}

		$id = Account::getAddress($publicKey);
		$sql="select max(t.height) as create_height, count(t.id) as created
			from transactions t
			where t.type = :create and ((t.dst = :id and (t.message='mncreate' or t.message='')) or t.message = :id1)";
		$res = $db->row($sql, [":create"=>TX_TYPE_MN_CREATE, ":id"=>$id,  ":id1"=>$id]);
		$created = $res['created'];
		$create_height = $res['create_height'];

		$sql="select max(t.height), count(t.id) as removed
		from transactions t where ((t.src = :src and (t.message='mnremove' or t.message='')) or t.message = :src1) and t.type = :remove;
		";
		$res = $db->row($sql, [":remove"=>TX_TYPE_MN_REMOVE, ":src"=>$id, ":src1"=>$id]);
		$removed = $res['removed'];
		if($created == $removed ) {
			_log("Masternode: No new masternode to create created=$created removed=$removed");
			return;
		}
		Masternode::create($publicKey, $create_height);
	}

	static function checkExistsMasternode($id, $height = null) {
		global $db;
		if(empty($height)) {
			$height = Block::getHeight();
		}
		$sql="select max(t.height) as create_height, count(t.id) as created
			from transactions t
			where t.type = :create and ((t.dst = :id and (t.message='mncreate' or t.message='')) or t.message =:id2) and t.height <= :height";
		$res = $db->row($sql, [":create"=>TX_TYPE_MN_CREATE, ":id"=>$id, ":id2"=>$id,":height"=>$height]);
		$created = $res['created'];

		$sql="select max(t.height), count(t.id) as removed
		from transactions t where ((t.src = :id and (t.message='mnremove' or t.message='')) or t.message =:id2) and t.type = :remove and t.height <=:height";
		$res = $db->row($sql, [":remove"=>TX_TYPE_MN_REMOVE, ":id"=>$id,  ":id2"=>$id, ":height"=>$height]);
		$removed = $res['removed'];
		return ($created>0 && $created != $removed);
	}

	static function processBlock($syncing = false) {
		global $_config, $db;

		$height = Block::getHeight();
		if(!Masternode::allowedMasternodes($height)) {
			_log("Masternode: not enabled", 5);
			return;
		}
		if(!Masternode::isLocalMasternode()) {
			_log("Masternode: Local node is not masternode", 5);
			return;
		}
		$masternode = self::get($_config['masternode_public_key']);
		if(!$masternode) {
			_log("Masternode: Not found local masternode in list", 3);
			return;
		}

        $nodeScore = $_config['node_score'];
        if($nodeScore < MIN_NODE_SCORE && !DEVELOPMENT) {
            _log("Masternode: invalid node score", 5);
            return;
        }

		$masternode = Masternode::fromDB($masternode);

		$win_height = Masternode::getLastWinHeight($masternode->id, $height);
		if($win_height <> $masternode->win_height) {
			_log("Updated local masternode win_height={$masternode->win_height} calc=$win_height", 4);
			$masternode->win_height = $win_height;
			$masternode->storeWinHeight();
		}

		$res = $masternode->sign($height + 1, $_config['masternode_private_key']);
		if (!$res) {
			_log("Masternode: Error signing masternode row");
			return;
		}

		$res = $masternode->verify($height+1, $err);
		if(!$res) {
			$masternode->signature = null;
		}

		$masternode->storeSignature();

		if(!empty($masternode->signature) && $masternode->verified && !$syncing) {
			_log("Masternode: Call propagate local mastenode win_height={$masternode->win_height} blockchain height=$height", 5);
			Propagate::masternode();
		}

	}

	static function propagate($id) {
		global $_config, $db;
		$height = Block::getHeight();
		if(!Masternode::allowedMasternodes($height)) {
			return;
		}
		if(!Masternode::isLocalMasternode()) {
			return;
		}

		$public_key = $_config['masternode_public_key'];
		$masternode = Masternode::get($public_key);

		if(Propagate::PROPAGATE_BY_FORKING && $id === "local") {
			_log("PF: start propagate", 5);
			$start = microtime(true);
			$peers = Peer::getPeersForPropagate();
			$info = Peer::getInfo();
			define("FORKED_PROCESS", getmypid());
            $cnt = count($peers);
            $i=0;
            $pipes = [];
			foreach ($peers as $peer) {
                $i++;
                $socket = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                if (!$socket) {
                    continue;
                }
				$pid = pcntl_fork();
				if ($pid == -1) {
					die('could not fork');
                } elseif ($pid > 0) {
                    fclose($socket[1]);
                    $pipes[$i] = $socket;
				} else if ($pid == 0) {
                    pcntl_signal(SIGALRM, function($signal){
                        if ($signal == SIGALRM) {
                            exit();
                        }
                    });
                    pcntl_alarm(10);
                    register_shutdown_function(function() use ($socket){
                        fclose($socket[1]);
                        posix_kill(getmypid(), SIGKILL);
                    });
                    fclose($socket[0]);
					$hostname = $peer['hostname'];
					$url = $hostname."/peer.php?q=updateMasternode";
					$res = peer_post($url, ["height"=>$height, "masternode"=>$masternode], 10, $err, $info, $curl_info);
                    $t2 = microtime(true);
                    $elapsed = $t2  - $start;
					_log("PF: Propagating to peer $i/$cnt: ".$hostname." res=".json_encode($res). " err=$err elapsed=$elapsed", 2);
                    $res = ["hostname"=>$hostname, "connect_time" => $curl_info['connect_time'], "elapsed"=>$elapsed, "res"=>$res, "err"=>$err];
                    fwrite($socket[1], json_encode($res));
					exit();
				}
			}
			while (pcntl_waitpid(0, $status) != -1) ;
            $connect_times = [];
            $elapsed_times = [];
            $ok_responses = 0;
            foreach($pipes as $pipe) {
                $output = stream_get_contents($pipe[0]);
                fclose($pipe[0]);
                $output = json_decode($output, true);
                $hostname = $output['hostname'];
                $connect_time = $output['connect_time'];
                $elapsed = $output['elapsed'];
                if(!empty($connect_time)) {
                    $connect_times[]=$connect_time;
                }
                $res = $output['res'];
                $err = $output['err'];
                if($res !== false) {
                    $ok_responses++;
                    Peer::storeResponseTime($hostname, $connect_time);
                } else {
                    _log("PM: $err", 2);
                }
                $elapsed_times[]=$elapsed;
            }
            $avg_connect_time = array_sum($connect_times) / count($connect_times);
            $avg_elapsed_times = array_sum($elapsed_times) / count($elapsed_times);
            _log("PF: Connect times avg = ".$avg_connect_time ." min=".min($connect_times). " max = ".max($connect_times), 5);
            _log("PF: Elapsed times avg = ".$avg_elapsed_times ." min=".min($elapsed_times). " max = ".max($elapsed_times), 5);
			_log("PF: Total time = ".(microtime(true)-$start). " total=".count($pipes). " ok_responses=$ok_responses", 5);
		} else {

			if ($id === "local") {
				//start propagate to each peer
				$peers = Peer::getPeersForMasternode();
				if (count($peers) == 0) {
					_log("Masternode: No peers to propagate");
				} else {
					foreach ($peers as $peer) {
						Propagate::masternodeToPeer($peer['hostname']);
					}
				}
			} else {
				//propagate to single peer
				$peer = base64_decode($id);
				_log("Masternode: propagating masternode to $peer pid=" . getmypid(), 5);
				$url = $peer . "/peer.php?q=updateMasternode";
				$res = peer_post($url, ["height" => $height, "masternode" => $masternode], 30, $err);
				_log("Masternode: Propagating to peer: " . $peer . " res=" . json_encode($res) . " err=$err", 5);
			}
		}
	}

	static function sync($masternode) {
		$savedMasternode = self::get($masternode['public_key']);
		if(!$savedMasternode) {
			$mn = new Masternode();
			$mn->id = $masternode['id'];
			$mn->public_key = $masternode['public_key'];
			$mn->height = $masternode['height'];
			$mn->signature = $masternode['signature'];
			$mn->win_height = $masternode['win_height'];
			$mn->collateral = Block::getMasternodeCollateral($mn->height);
			$res = $mn->add();
			if(!$res) {
				_log("Masternode: Can not add masternode");
				return false;
			}
		} else {
//			_log("Masternode: sync ".json_encode($masternode));
			$savedMasternode = Masternode::fromDB($savedMasternode);
			$savedMasternode->id = $masternode['id'];
			$savedMasternode->public_key = $masternode['public_key'];
			$savedMasternode->height = $masternode['height'];
			$savedMasternode->signature = $masternode['signature'];
			$savedMasternode->win_height = $masternode['win_height'];
			$savedMasternode->ip=$masternode['ip'];
			$savedMasternode->verified = $masternode['verified'];
			$res = $savedMasternode->update();
			if($res === false) {
				_log("Masternode: Can not update masternode = ".json_encode($masternode));
				return false;
			}
		}
		return true;
	}

	static function updateMasternode($data, $ip, &$error) {

		global $_config;

		try {

			$masternode=$data['masternode'];
			$mn_height=$data['height'];
			$height = Block::getHeight();
			_log("Masternode: updateMasternode ip=$ip mn_height=$mn_height height=$height masternode=" . $masternode['public_key']. " win_height=".$masternode['win_height']. " signature=".$masternode['signature'], 5);

			if(!Masternode::allowedMasternodes($height)) {
				throw new Exception("Masternode: Not allowed masternodes");
			}


			if($mn_height != $height) {
				throw new Exception("Masternode: Received height $mn_height is different than local $height - skip");
			}


			if(Masternode::isLocalMasternode() && $masternode['public_key']==$_config['masternode_public_key']) {
				throw new Exception("Masternode: Can not update local masternode");
			}

			$mn = Masternode::fromDB($masternode);
			$res = $mn->check($height, $err);
			if(!$res) {
				throw new Exception("Masternode: check failed: $err");
			}

			$signature=$masternode['signature'];
			_log("Masternode: check if synced signature=".$signature . " public_key=".$masternode['public_key'], 5);
			$mn_synced = Masternode::checkSynced($signature, $masternode['public_key']);
			if($mn_synced) {
				_log("Masternode: already synced", 3);
				return true;
			}

			$mn_ip = Masternode::getByIp($ip);
			if($mn_ip && $mn_ip['public_key']!=$masternode['public_key']) {
				_log("Masternode: invalid IP address $ip for public_key ".$masternode['public_key']);
				Masternode::deleteInvalid($ip, $masternode['public_key']);
				return true;
			}

//		    _log("Masternode: synced ".$masternode['public_key']." win_height=".$masternode['win_height']);
			$masternode['ip']=$ip;
			$masternode['verified']=$mn->verified;
			$res = Masternode::sync($masternode);
			if(!$res) {
				throw new Exception("Masternode: Can not sync local with remote masternode");
			}

			_log("Masternode: synced remote masternode $ip id=".$masternode['id']. " signature=".$masternode['signature'],1);
			return true;

		} catch (Exception $e) {
			$error = $e->getMessage();
			_log($error, 4);
			return false;
		}

	}

	static function getRewardTx($generator, $new_block_date, $public_key, $private_key, $height, &$mn_signature, &$block_masternode) {
		if(!Masternode::allowedMasternodes($height)) {
			return false;
		}
		_log("Masternode: generating reward transaction", 5);
		$winner = Masternode::getWinner($height);
		if(!$winner) {
			_log("Masternode: not found winner");
			$mn_count = Masternode::getCount();
			if($mn_count > 0 && $height > UPDATE_5_NO_MASTERNODE) {
				$collateral = Block::getMasternodeCollateral($height);
				$cnt = Masternode::getCountByCollateral($collateral);
				if($cnt > 0) {
					return false;
				} else {
					$dst = $generator;
				}
			} else {
				$dst = $generator;
			}
		} else {
			$collateralTx=Masternode::checkCollateral($winner['id'], $height);
			if($collateralTx) {
                $winnerAddress = $collateralTx['dst'];
				$dst = $winnerAddress;
				$mn_signature = $winner['signature'];
                $block_masternode = Masternode::getMasternodeAddress($height, $collateralTx);
			} else {
				$dst = $generator;
                $block_masternode = $generator;
			}
		}
		$rewardinfo = Block::reward($height);
		$reward = $rewardinfo['masternode'];
		$reward_tx = Transaction::getRewardTransaction($dst, $new_block_date, $public_key, $private_key, $reward, "masternode");
		_log("Masternode: reward tx=".json_encode($reward_tx));
		return $reward_tx;
	}

    static function getMasternodeAddress($height, $collateralTx) {
        if($collateralTx instanceof Transaction) {
            $collateralTx = $collateralTx->toArray();
        }
        if($height >= MN_COLD_START_HEIGHT) {
            $msg = $collateralTx['message'];
            if(!empty($msg) && $msg != "mncreate" && Account::valid($msg)) {
                return $msg;
            } else {
                return $collateralTx['dst'];
            }
        } else {
            return $collateralTx['dst'];
        }
    }

	static function checkTx(Transaction $transaction, $block, &$error) {
		$height = $block->height;
		if(!Masternode::allowedMasternodes($height)) {
			return true;
		}
		if($transaction->msg != "masternode") {
			return true;
		}

		try {
			if($block->masternode) {
				if($transaction->dst != $block->masternode) {
                    if($height >= MN_COLD_START_HEIGHT) {
                        $collateralTx=Masternode::checkCollateral($block->masternode, $height);
                        if(!$collateralTx || $collateralTx['dst']!=$transaction->dst) {
                            throw new Exception("Transaction dst invalid. Must be masternode collateral dst address");
                        }
                    } else {
					    throw new Exception("Transaction dst invalid. Must be masternode");
				    }
				}
				$mnPublicKey = Account::publicKey($block->masternode);
				if(!$mnPublicKey) {
					throw new Exception("Not found public key for msternode");
				}
				$masternode = Masternode::get($mnPublicKey);
				if(!$masternode) {
					if(!Masternode::checkExistsMasternode($block->masternode, $height))  {
						throw new Exception("Masternode not found in list");
					}
				}
				$signatureBase = Masternode::getSignatureBase($mnPublicKey, $height);
				$res = ec_verify($signatureBase, $block->mn_signature, $mnPublicKey, Block::getChainId($height));
				if(!$res) {
					throw new Exception("Masternode signature not valid");
				}
				$res=Masternode::checkCollateral($block->masternode, $height);
				if(!$res) {
					throw new Exception("Masternode collateral not found");
				}
			} else {
				if($transaction->dst != $block->generator) {
					throw new Exception("Transaction dst invalid. Must be generator");
				}
			}

			$rewardinfo = Block::reward($height);
			$reward = $rewardinfo['masternode'];
			if($transaction->val != $reward) {
				throw new Exception("Invalid transaction reward");
			}

			return true;

		} catch (Exception $e) {
			$error = $e->getMessage();
			_log($error);
			return false;
		}


	}

	static function processRewardTx(Transaction  $transaction, &$error=null) {

		$block = Block::current();
		$height = $block['height'];
		if(!Masternode::allowedMasternodes($height)) {
			return true;
		}
		if($transaction->msg != "masternode") {
			return true;
		}
		try {

			if($block['masternode']) {
				_log("Masternode: updating winner for block $height id=".$block['masternode'], 5);
				$mnPublicKey = Account::publicKey($block['masternode']);
				$res = Masternode::updateWinner($height, $mnPublicKey);
				if($res === false) {
					throw new Error("Error updating masternode winner");
				}
			}

			return true;
		} catch (Exception $e) {
			$error = $e->getMessage();
			_log($error);
			return false;
		}
	}

	static function verifyBlock(Block  $block, &$error) {

		try {

			if(!Masternode::allowedMasternodes($block->height)) {
				return true;
			}

			if(empty($block->masternode) && $block->height > UPDATE_5_NO_MASTERNODE) {
				$collateral = Block::getMasternodeCollateral($block->height);
				$mn_count = Masternode::getCountForCollateral($collateral, $block->height);
				_log("check collateral : $collateral count=$mn_count", 5);
				if($mn_count > 0) {
					throw new Exception("Masternode: not found winner for block");
				}
			}

			if(!empty($block->masternode)) {
				$data = $block->data;
				$found = false;
				$mnPublickKey = Account::publicKey($block->masternode);
				if(!$mnPublickKey) {
					throw new Exception("Masternode: not found public key");
				}

				$base = Masternode::getSignatureBase($mnPublickKey, $block->height);
				$res = ec_verify($base, $block->mn_signature, $mnPublickKey, Block::getChainId($block->height));
				if(!$res) {
					throw new Exception("Masternode: masternode signature failed");
				}

				foreach ($data as $transaction) {
					$tx = Transaction::getFromArray($transaction);
					if($tx->type == TX_TYPE_REWARD && $tx->msg == 'masternode' && $tx->publicKey == $block->publicKey) {
                        $collateralTx=Masternode::checkCollateral($block->masternode, $block->height);
                        if($collateralTx && $tx->dst == $collateralTx['dst']) {
                            $reward = Block::reward($block->height);
                            $mn_reward = $reward['masternode'];
                            if($mn_reward == $tx->val) {
                                $found = true;
                                break;
                            }
                        }
                    }
				}
				if(!$found) {
					throw new Exception("Masternode: not found reward transaction");
				}
			}

			return true;

		} catch (Exception $e) {
			$error = $e->getMessage();
			_log($error);
			return false;
		}


	}

	static function checkStucked() {
		global $_config;
		$height = Block::getHeight();
		if(!Masternode::allowedMasternodes($height)) {
			return;
		}
		$rows = Masternode::getStucked($height);
		if(count($rows)==0) {
			return;
		}
		$peers = Peer::getActive(5);
		foreach($rows as $row) {

			$win_heights = [];
			foreach ($peers as $peer) {
				$peer_url = $peer['hostname'];
				$masternode = peer_post($peer_url."/peer.php?q=getMasternode", $row['public_key']);
				if(!$masternode) {
					continue;
				}
				$win_heights[$masternode['win_height']][]=$masternode;
			}

			if(count(array_keys($win_heights))==1) {
				$masternode = $win_heights[array_keys($win_heights)[0]][0];
				if(self::isLocalMasternode() && $_config['masternode_public_key']==$masternode['public_key']) {
					continue;
				}
				Masternode::sync($win_heights[array_keys($win_heights)[0]][0]);
			}

		}
	}

	static function runThread() {
		$height = Block::getHeight();
		if(!Masternode::allowedMasternodes($height)) {
			return;
		}
		if(!Masternode::isLocalMasternode()) {
			return;
		}
		if(defined("MASTERNODE_PROCESS")) {
			return;
		}
		$lock_file = ROOT."/tmp/mn-lock";

		if (!file_exists($lock_file)) {
			$res = shell_exec("ps uax | grep '".ROOT."/cli/masternode.php' | grep -v grep");
			if(empty($res)) {
				$dir = ROOT . "/cli";
				system("php $dir/masternode.php > /dev/null 2>&1  &");
			}
		}
	}

	static function reverseBlock($block, &$err = null) {
		try {

			$masternode = $block['masternode'];
			if($masternode) {
				$publicKey = Account::publicKey($masternode);
				$winnerMasternode = Masternode::get($publicKey);
				$winnerMasternode = Masternode::fromDB($winnerMasternode);
				$new_win_height = Masternode::getLastWinHeight($masternode);
				$winnerMasternode->win_height = $new_win_height;
				$winnerMasternode->update();
			}
			return true;
		} catch (Exception $e) {
			$err = "Masternode: Error reverting masternode. Error: ".$e->getMessage();
			_log($err);
			return false;
		}

	}

	function check($height, &$error = null) {

		try {

			if(Account::publicKey($this->id) != $this->public_key) {
				throw new Exception("Invalid masternode address");
			}

			if(!$this->verify($height+1, $mn_err)) {
				throw new Exception("Masternode check failed: $mn_err");
			}

			$mn_height = Masternode::getMasternodeHeight($this->id, $height);
			if($mn_height != $this->height) {
				throw new Exception("Invalid masternode height saved={$this->height} calculated=$mn_height");
			}

			$win_height = Masternode::getLastWinHeight($this->id, $height);
			if($win_height != $this->win_height) {
				throw new Exception("Invalid masternode {$this->id} {$this->ip} win_height saved={$this->win_height} calculated=$win_height");
			}

            return true;

		} catch(Exception $e) {
			$error = $e->getMessage();
			_log($error, 2);
			return false;
		}

	}

	public static function checkSend($transaction)
	{
		$height = Block::getHeight();
		if (Masternode::allowedMasternodes($height)) {
			$masternode = Masternode::get($transaction->publicKey);
			if($masternode) {
                if(Masternode::isHot($masternode['id'], $height)) {
                    $balance = Account::getBalanceByPublicKey($transaction->publicKey);
                    $memspent = Mempool::getSourceMempoolBalance($transaction->src);
                    $collateral = Block::getMasternodeCollateral($height);
                    if(floatval($balance) - floatval($memspent) - $transaction->val < $collateral) {
    //					throw new Exception("Can not spent more than collateral. Balance=$balance memspent=$memspent amount=".$transaction->val);
                    }
                }
            }
        }
	}

	static function checkMasternode() {
		global $_config;
		$height = Block::getHeight();
		if(!Masternode::allowedMasternodes($height)) {
			echo "Masternode phase not started".PHP_EOL;
			return;
		}
		if(!Masternode::isLocalMasternode()) {
			echo "Node is not configured as masternode".PHP_EOL;
			return;
		}
		$masternode = Masternode::get($_config['masternode_public_key']);
		if(!$masternode) {
			echo "Error: local masternode not found in list".PHP_EOL;
			return;
		}
		$masternode = Masternode::fromDB($masternode);
		$res = $masternode->check($height, $error);
		if(!$res) {
			echo "Error: local masternode not valid: ".PHP_EOL.$error.PHP_EOL;
			return;
		}
		echo "Local masternode valid".PHP_EOL;
		echo "Address: " . Account::getAddress($_config['masternode_public_key']).PHP_EOL;
	}

	static function resetMasternode() {
		$height = Block::getHeight();
		if(!Masternode::allowedMasternodes($height)) {
			echo "Masternode phase not started".PHP_EOL;
			return;
		}
		if(!Masternode::isLocalMasternode()) {
			echo "Node is not configured as masternode".PHP_EOL;
			return;
		}
		global $_config;
		Masternode::delete($_config['masternode_public_key']);
		$lock_file = ROOT."/tmp/mn-lock";
		@rmdir($lock_file);
		echo "Masternode deleted! Will be recreated in next process".PHP_EOL;
	}

	static function getMasternodesForPublicKey($public_key) {
		global $db;
		$sql="select m.id as masternode_address, a.balance as masternode_balance, m.collateral
			from transactions t
			left join masternode m on (m.id = t.dst)
			left join accounts a on (m.id = a.id)
			where t.type = :mn_create and t.public_key = :public_key
			and m.id is not null
			group by m.id, m.collateral";
		return $db->run($sql, [":mn_create" => TX_TYPE_MN_CREATE, ":public_key" => $public_key]);
	}

	static function process() {
		_log("Masternode: start process",5);
		$height = Block::getHeight();
		if(!Masternode::allowedMasternodes($height)) {
			_log("Masternode feature not enabled");
			return;
		}
		Masternode::checkLocalMasternode();
		Masternode::processBlock();
	}

	static function checkSynced($signature, $public_key) {
		global $db;
		$sql = "select 1 from masternode m where m.signature = :signature and m.public_key = :public_key and m.ip is not null";
		$res = $db->single($sql, [":signature"=>$signature, ":public_key"=>$public_key]);
		return $res == 1;
	}

	static function getByIp($ip) {
		global $db;
		$sql = "select * from masternode m where m.ip = :ip";
		$row = $db->row($sql, [":ip"=>$ip]);
		return $row;
	}

	private static function clearIp($public_key)
	{
		global $db;
		$sql="update masternode set ip=null where public_key =:public_key";
		$db->run($sql, [":public_key"=>$public_key]);
	}

	private static function updatePublicKey($public_key, $ip)
	{
		global $db;
		$sql="update masternode set ip=:ip where public_key =:public_key";
		$db->run($sql, [":ip"=>$ip, ":public_key"=>$public_key]);
	}

	static function checkCollateral($masternode, $height) {
		global $db;
		$collateral = Block::getMasternodeCollateral($height);
		$sql="select * from transactions t use index(transactions_type_index) 
            where ((t.dst = :dst and (t.message='mncreate' or t.message='')) or t.message=:dst2) and t.type = :type and t.val =:collateral and t.height <= :height
            order by t.height desc limit 1";
		$row = $db->row($sql, [":dst"=>$masternode, ":dst2"=>$masternode, ":type"=>TX_TYPE_MN_CREATE, ":collateral"=>$collateral, ":height"=>$height]);
		return $row;
	}

    static function isHot($id, $height) {
        $transaction = Masternode::checkCollateral($id, $height);
        if($height >= MN_COLD_START_HEIGHT) {
            $msg = $transaction['message'];
            if(!empty($msg) && Account::valid($msg) && $msg == $id) {
                return false;
            }
        }
    }

	static function checkAnyCollateral($height) {
		global $db;
		$collateral = Block::getMasternodeCollateral($height);
		$sql="select * from transactions t where t.type = :type and t.val =:collateral";
		$row = $db->row($sql, [":type"=>TX_TYPE_MN_CREATE, ":collateral"=>$collateral]);
		return $row;
	}

	static function resetVerified() {
		global $db;
		_log("MN: reset verified", 5);
		$db->run("update masternode set verified = 0");
	}

	static function deleteInvalid($ip, $public_key) {
		global $db;
		$sql="delete from masternode where ip=:ip or public_key=:public_key";
		$db->run($sql, [":ip"=>$ip, ":public_key"=>$public_key]);
	}

	static function isExisting($id, $height) {
		global $db;
		$sql="select count(t.id) as cnt_create from transactions t where (t.dst = :id) and t.type = :type
			and t.height <= :height";
		$cnt_created = $db->single($sql, [":id"=>$id, ":type"=>TX_TYPE_MN_CREATE, ":height"=>$height]);
		$sql="select count(t.id) as cnt_remove from transactions t where (t.src = :id) and t.type = :type
			and t.height <= :height";
		$cnt_removed = $db->single($sql, [":id"=>$id, ":type"=>TX_TYPE_MN_REMOVE, ":height"=>$height]);
		if($cnt_created - $cnt_removed > 0) {
			return true;
		} else {
			return false;
		}
	}
}
