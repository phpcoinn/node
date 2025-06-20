<?php

class PeerRequest
{

	public static $ip;
	public static $data;
	public static $requestId;
	public static $info;
	public static $peer;

	static function processRequest() {
		global $_config;


		_logp("PeerRequest: received peer request");
		if(isset($_config) && $_config['offline']==true) {
			_logf("Peer is set to offline");
			api_err("Peer is set to offline");
		}
		if (!empty($_POST['data'])) {
			$data = json_decode(trim($_POST['data']), true);
		}
		global $_config;
		if ($_POST['coin'] != COIN) {
			_logf("Invalid coin request=".json_encode($_REQUEST)." server=".json_encode($_SERVER));
			api_err("Invalid coin ".json_encode($_REQUEST), 3);
		}
		if(isset($_POST['network'])) {
			if($_POST['network'] != NETWORK) {
				_logf("Invalid network");
				api_err("Invalid network ".$_POST['network']);
			}
		}
		if(isset($_POST['chain_id']) && strlen($_POST['chain_id'])>0) {
			if($_POST['chain_id'] != CHAIN_ID) {
				_logf("Invalid chain ID");
				api_err("Invalid chain ID ".$_POST['chain_id']);
			}
		}
		$ip = Nodeutil::getRemoteAddr();

		if(version_compare($_POST['version'], MIN_VERSION) < 0) {
			$peer = Peer::findByIp($ip);
			if($peer) {
				Peer::blacklist($peer['id'], "Invalid version ".$_POST['version']);
			}
			_logp("request=".json_encode($_REQUEST));
			_logf("Invalid version ".$_POST['version']);
			api_err("Invalid version ".$_POST['version']);
		}
		$requestId = $_POST['requestId'];
		_log("Peer request from IP = $ip requestId=$requestId q=".$_GET['q']." chainId=".$_POST['chain_id'] ,4);

		_logp("q=".$_GET['q']);

		$info = $_POST['info'];

		$ip = Peer::validateIp($ip);
		_log("Filtered IP = $ip",4);

		if(($ip === false || strlen($ip)==0)) {
			_logf("Invalid peer IP address");
			api_err("Invalid peer IP address");
		}

		$ip = $ip . (empty(COIN_PORT) ? "" :  ":" . COIN_PORT);
		_logp("ip=$ip");

		if(!Blacklist::checkIp($ip)) {
			_logf("blocked-ip");
			api_err("blocked-ip");
		}

		$peer = Peer::getByIp($ip);
		if($peer) {
			if($peer['blacklisted'] > time() && strpos($peer['blacklist_reason'],"Invalid hostname") === 0) {
				$hostname=$info['hostname'];
				_log("Peer request from blacklisted peer ip=$ip hostname=$hostname reason=".$peer['blacklist_reason']." info=".json_encode($info). "REMOTE_ADDR=".$_SERVER['REMOTE_ADDR'].
				" HTTP_X_FORWARDED_FOR=".@$_SERVER['HTTP_X_FORWARDED_FOR']);
				_logf("blacklisted-peer");
				api_err("blacklisted-peer SERVER=".json_encode($_SERVER). " peer=".json_encode($peer));
			}
		} else {
            _log("Peer with $ip not in list", 2);
        }

		if(!empty($info)) {
			$hostname=$info['hostname'];
			if(!empty($hostname)) {
				if(!empty($peer['hostname']) && $peer['hostname'] != $hostname) {
					Peer::blacklist($peer['id'], "Invalid hostname $hostname");
					_logf("blocked-invalid-hostname");
					api_err("blocked-invalid-hostname");
				}
                _log("PRC: ip=$ip hostname=$hostname mn=".@$info['masternode']." found_peer=". ($peer ? $peer['hostname'] : ""),5);
			}
			_logp("update peer info");
			Peer::updatePeerInfo($ip, $info);
            if(!empty($peer['id'])) {
                $peer['height'] = $info['height'];
                if ($peer['blacklisted'] < time() && $peer['fails'] > 0) {
                    _logp("clear blacklist");
                    Peer::clearFails($peer['id']);
                    Peer::clearStuck($peer['id']);
                }
                _log("check peer height hostname=$hostname height=" . $peer['height'], 5);
                $current_height = Block::getHeight();
                if (isset($peer['height']) && ($current_height - $peer['height'] > 100)) {
                    Peer::blacklist($peer['id'], "100 blocks behind");
                }
                if ($peer['blacklisted'] > time() && $peer['blacklist_reason'] == "100 blocks behind") {
                    if ($current_height - $peer['height'] < 10) {
                        _log("PBH: Check peer if is still blocks behind current_height = $current_height peer_height=" . $peer['height'] . " blacklisted=" . ($peer['blacklisted'] > time()) .
                            " reason=" . $peer['blacklist_reason'] . " - remove form blacklist", 5);
                        Peer::clearBlacklist($peer['id']);
                    }
                }
            }
		}

		_logf("finish process");

		self::$ip=$ip;
		self::$data=$data;
		self::$requestId=$requestId;
		self::$info = $info;
		self::$peer = $peer;
	}

	static function peer() {
		$data =self::$data;
		$ip =self::$ip;
		global $_config;
		_log("Received peer requst: ". json_encode($data),3);

		if(!Peer::validate($data['hostname'])) {
			api_err("invalid-hostname");
		}

		// sanitize the hostname
		$hostname = filter_var($data['hostname'], FILTER_SANITIZE_URL);
		$hostname = san_host($hostname);
		_log("Received peer request from $hostname",3);
		if($hostname === $_config['hostname']) {
			api_err("self-peer");
		}

		// if it's already peered, only repeer on request
		$res = Peer::getSingle($hostname, $ip);
		if ($res == 1) {
			_log("$hostname is already in peer db",3);
			if ($data['repeer'] == 1) {
				$res = peer_post($hostname."/peer.php?q=peer", ["hostname" => $_config['hostname']], 30, $err);
				if ($res !== false) {
					api_echo("re-peer-ok");
				} else {
					api_err("re-peer failed - $err");
				}
			}
			api_echo("peer-ok-already");
		} else {
			_log("$hostname is new peer",2);
		}
		_log("Inserting $hostname in peer db",3);
		$res = Peer::insert($ip, $hostname);
		_log("Inserted $hostname = $res",3);
        if(isset($_REQUEST['info'])) {
            Peer::updatePeerInfo($ip, $_REQUEST['info']);
        }
		// re-peer to make sure the peer is valid
		if ($data['repeer'] == 1) {
			_log("Repeer to $hostname",3);
			$res = peer_post($hostname . "/peer.php?q=peer", ["hostname" => $_config['hostname']], 30, $err);
			_log("peer response " . print_r($res,1),4);
			if ($res !== false) {
				_log("Repeer OK",3);
				api_echo("re-peer-ok");
			} else {
				_log("Repeer FAILED - DELETING",2);
				if($ip) {
					Peer::deleteByIp($ip);
					api_err("re-peer failed - $err");
				} else {
					api_err("invalid peer ip");
				}
			}
		} else {
			api_echo("peer-ok");
		}
	}

	static function ping() {
		// confirm peer is active
		api_echo("pong");
	}

	static function submitTransaction() {
		$data = self::$data;
		global $db, $_config;

		_log("receive a new transaction from a peer from ".self::$ip,2);
		_log("data: ".json_encode($data),3);

		$tx = Transaction::getFromArray($data);
		$tx->mempool = true;
		// receive a new transaction from a peer
//    $current = $block->current();

		if(Config::getVal("blockchain_invalid") == 1) {
			api_err("invalid-peer");
		}

		// no transactions accepted if the sync is running
		if (Config::isSync()) {
			api_err("sync");
		}

        $hash = $tx->id;
        // make sure it's not already in mempool
        $res = Mempool::existsTx($hash);
        if ($res != 0) {
            api_err("The transaction is already in mempool");
        }

        $res = Mempool::checkMempoolBalance($tx, $error);
        if(!$res) {
            api_err("Error processing new transaction in mempool: $error");
        }

		// validate transaction data
		if (!$tx->check(null, false, $txerr)) {
			api_err("Invalid transaction: $txerr");
		}

		// make sure the peer is not flooding us with transactions
		$res = Mempool::getSourceTxCount($tx->src);
		if ($res > 25) {
			api_err("Too many transactions from this address in mempool. Please rebroadcast later.");
		}
		$res = Mempool::getPeerTxCount(self::$ip);
		if ($res > $_config['peer_max_mempool']) {
			api_err("Too many transactions broadcasted from this peer");
		}


		// make sure the transaction is not already on the blockchain
		$res = $db->single("SELECT COUNT(1) FROM transactions WHERE id=:id", [":id" => $hash]);
		if ($res != 0) {
			api_err("The transaction is already in a block");
		}
		// make sure the sender has enough balance
		$balance = $db->single("SELECT balance FROM accounts WHERE id=:id", [":id" => $tx->src]);
		if ($balance < $tx->val + $tx->fee) {
			api_err("Not enough funds");
		}

		// make sure the sender has enough pending balance
		$memspent = Mempool::getSourceMempoolBalance($tx->src);
		if ($balance - $memspent < $tx->val + $tx->fee) {
			api_err("Not enough funds (mempool)");
		}

		$max_txs = Block::max_transactions();
		$mempool_size = Transaction::getMempoolCount();

		if($mempool_size + 1 > $max_txs) {
			$error = "Mempool full";
			_log("Not added transaction to mempool because is full: max_txs=$max_txs mempool_size=$mempool_size");
			api_err($error);
		}

        if(FEATURE_SMART_CONTRACTS) {
            if ($tx->type == TX_TYPE_SC_EXEC || $tx->type == TX_TYPE_SC_SEND) {
                $schash = SmartContract::processSmartContractTx($tx, Block::getHeight() + 1, $error);
                if ($schash === false) {
                    throw new Exception("Error processing smart contract transaction: Invalid transaction schash: " . $error);
                }
            }
        }

		// add to mempool
		$tx->add_mempool(self::$ip);

		Propagate::transactionToAll($tx->id);
		api_echo("transaction-ok");
	}

	static function submitBlock() {

		self::submitBlockNew();
		return;


		$ip = self::$ip;
		$data = self::$data;

		$current = Block::current();

		$microsync = isset($data['microsync']);
		_log("submitBlock: ".($microsync ? "[microsync] " : "")."Receive new block from a peer $ip : id=".$data['id']." height=".$data['height']." current=".$current['height'], 5);



		$data['id'] = san($data['id']);
		if ($current['id'] == $data['id'] && $current['height']==$data['height']) {
			_log("submitBlock: We have this block - OK",3);
			api_echo("block-ok");
		}
		if ($data['date'] > time() + 30) {
			_log("submitBlock: block in the future");
			api_err("block in the future");
		}

		//_log("submitBlock: current_height=".$current['height']." data_height=".$data['height']." current_id=".$current['id']." data_id=".$data['id']);

		if ($current['height'] == $data['height'] && $current['id'] != $data['id']) {
			$accept_new = false;
			_log("submitBlock:: DIFFERENT FORKS SAME HEIGHT", 3);
			_log("submitBlock: id= ".json_encode($data),3);
			$ourblock = Block::export("", $current['height']);
			_log("submitBlock:our ".json_encode($ourblock),3);

			//compare two blocks
			_log("submitBlock: compare  blocks data -> our: id=".$data['id']." -> ".$ourblock['id']." elapsed=".$data['elapsed']." -> ".$ourblock['elapsed']." date=".$data['date']." -> ".$ourblock['date'], 5);
			if($data['elapsed']==$ourblock['elapsed']) {
				if($data['date']==$ourblock['date']) {
					$accept_new = strcmp($data['id'], $ourblock['id']);
				} else {
					$accept_new = $data['date'] < $ourblock['date'];
				}
			} else {
				$accept_new = $data['elapsed'] < $ourblock['elapsed'];
			}
			_log("submitBlock: accept_new=$accept_new");

//			//wins block with lowest elapsed time - highest difficulty
//			$difficulty1 = $current['difficulty'];
//			$difficulty2 = $data['difficulty'];
//			//_log("DFSH: compare difficulty: difficulty1=$difficulty1 difficulty2=$difficulty2");
//			if($difficulty1 > $difficulty2) {
//				$accept_new = true;
//			}  else if ($difficulty1 == $difficulty2) {
//				$date1 = $current['date'];
//				$date2 = $data['date'];
//				//_log("DFSH: compare date: date1=$date1 date2=$date2");
//				if($date1 > $date2) {
//					$accept_new = true;
//				} else if ($date1 == $date2) {
//					$miner1=$current['miner'];
//					$miner2=$data['miner'];
//					$generator1=$current['generator'];
//					$generator2=$data['generator'];
//					//_log("DFSH: compare miners: miner1=$miner1 miner2=$miner2");
//					//_log("DFSH: compare generator: generator1=$generator1 generator2=$generator2");
//					$id1=$current['id'];
//					$id2=$data['id'];
//					//_log("DFSH: compare ids: id1=$id1 id2=$id2");
//					if(strcmp($id1, $id2)) {
//						$accept_new = true;
//					}
//				}
//			}

			//_log("DFSH: Accept new = ".$accept_new);

			if ($accept_new) {
				// if the new block is accepted, run a microsync to sync it
				_log('submitBlock: ['.$ip."] Starting microsync - $data[height]",1);
				$ip=escapeshellarg($ip);
				$dir = ROOT."/cli";
				system(  "php $dir/microsync.php '$ip'  > /dev/null 2>&1  &");
				api_echo("microsync");
			} else {
				_log('submitBlock: ['.$ip."] suggesting reverse-microsync - $data[height]",1);
				api_echo("reverse-microsync"); // if it's not, suggest to the peer to get the block from us
			}
		}
		// if it's not the next block
		if ($current['height'] != $data['height'] - 1) {
			_log("Not next block - exit", 5);
			api_err("not-next-block");

//			if($microsync) {
//				_logf("already in microsync", 5);
//				api_err("already-microsync");
//			}
//			_log("if it's not the next block",1);
			// if the height of the block submitted is lower than our current height, send them our current block
			if ($data['height'] < $current['height']) {
				//_log("DFSH: Our height is higher");
				$pr = Peer::getByIp($ip);
				if (!$pr) {
					//_log("DFSH: No peer by IP $ip");
					api_err("block-too-old");
				}
				Propagate::blockToPeer($pr['hostname'], $pr['ip'], "current");
				_log('submitBlock: ['.$ip."] block too old, sending our current block - $current[height]",3);
				//_log("DFSH: Send our block to peer ".$pr['hostname']);
				api_err("block-too-old");
			}
			// if the block difference is bigger than 150, nothing should be done. They should sync
			if ($data['height'] - $current['height'] > 150) {
				_log('submitBlock: ['.$ip."] block-out-of-sync - $data[height]",2);
				//_log("DFSH: block difference higher than 150");
				api_err("block-out-of-sync");
			}
			// request them to send us a microsync with the latest blocks
			$pr = Peer::getByIp($ip);
			$hostname = $pr['hostname'];
			_log('submitBlock:: ['.$hostname."] requesting microsync - $current[height] - $data[height]",2);
			api_echo(["request" => "microsync", "height" => $current['height'], "block" => $current['id']]);
		}
		// check block data
		$block = Block::getFromArray($data);
		if (!$block->check()) {
			_log('submitBlock: ['.$ip."] invalid block - $data[height]",1);
			api_err("invalid-block");
		}
		$block->prevBlockId = $current['id'];

		$current = Block::current();
		//_log("DFSH: check add BLOCK ".$block->height. " current=".$current['height']);
		if($block->height == $current['height']) {
			_log("submitBlock: block checked ok", 5);
			api_echo("block-ok");
		}

		$lock_file = ROOT . "/tmp/lock-block-".$block->height;
		_log("Check lock file $lock_file", 5);
		if (!mkdir($lock_file, 0700)) {
			_log("submitBlock: Lock file exists $lock_file", 3);
			api_echo("sync");
		}

		//_log("DFSH: ADD BLOCK ".$block->height);
		$res = $block->add($error);

		_log("Remove lock file $lock_file", 5);
		@rmdir($lock_file);

		if (Config::isSync()) {
			api_err("submitBlock: sync");
		}

		if (!$res) {
			_log('submitBlock: ['.$ip."] invalid block data - $data[height] Error:$error",1);
			api_err("invalid-block-data $error");
		}

		$last_block = Block::export("", $data['height']);
		$bl = Block::getFromArray($last_block);
		$res = $bl->verifyBlock($err);

		if (!$res) {
			_log("Can not verify added block err=$err",1);
			api_err("invalid-block-data");
		}

		_log('submitBlock: ['.$ip."] block ok, repropagating - $data[height]",1);

		Propagate::blockToAll($data['id']);
		api_echo("block-ok");
	}

	static function currentBlock() {
		$current = Block::current();
		api_echo(["block"=>$current, "info"=>Peer::getInfo()]);
	}

	static function getBlock() {
		$data = self::$data;
		$height = intval($data['height']);
		$export = Block::export("", $height);
		if (!$export) {
			api_err("invalid-block");
		}
		api_echo($export);
	}

	static function getBlocks() {
		$data = self::$data;
		global $db;
		// returns X block starting at height,  used in syncing
		$height = intval($data['height']);

		$r = $db->run(
			"SELECT id,height FROM blocks WHERE height>=:height ORDER by height ASC LIMIT 100",
			[":height" => $height]
		);
		foreach ($r as $x) {
			$blocks[$x['height']] = Block::export($x['id']);
		}
		api_echo($blocks);
	}

	static function getPeerBlocks() {
		$data = self::$data;
		global $db;
		// returns X block starting at height,  used in syncing
		$height = intval($data['height']);
		$count = intval($data['count']);

		if(empty($count)) {
			$count = 100;
		}

		if(empty($height)) {
			$r = $db->run(
				"SELECT id,height FROM blocks ORDER by height DESC LIMIT $count"
			);
		} else {
			$r = $db->run(
				"SELECT id,height FROM blocks WHERE height < :height ORDER by height DESC LIMIT $count",
				[":height" => $height]
			);
		}

		foreach ($r as $x) {
			$blocks[$x['height']] = Block::export($x['id']);
		}
		$current = Block::current();
		api_echo(["block"=>$current,"blocks"=>$blocks, "info"=>Peer::getInfo()]);
	}

	static function getPeers() {
		//	_log("Executing getPeers");
		$peers = Peer::getPeers();
		//    _log("Response".print_r($peers,1));
		api_echo($peers);
	}

	static function getAppsHash() {
		$appsHashFile = Nodeutil::getAppsHashFile();
		$appsHash = file_get_contents($appsHashFile);
		api_echo($appsHash);
	}

	static function updateDapps() {
		Dapps::updateDapps(self::$data, self::$ip);
	}

	static function checkDapps() {
		Dapps::checkDapps(self::$data['dapps_id'], self::$ip);
	}

	static function updateMasternode() {
		if(Config::isSync()) {
			api_err("sync");
		}
		$masternode = self::$data;
		$ip = self::$ip;
		Masternode::updateMasternode($masternode, $ip, $error);
		if($error) {
			api_err($error);
		} else {
			api_echo("OK");
		}
	}

	static function getMasternode() {
		$public_key = self::$data;
		_log("Masternode: getMasternode $public_key");
		$masternode = Masternode::get($public_key);
		api_echo($masternode);
	}

    static function propagateMsg7()
    {
        global $db, $_config;
        _log("PM: received propagateMsg7 from ".self::$peer['hostname']);
        $envelope = self::$data;
        $envelope = json_decode(base64_decode($envelope),true);
        $payload = $envelope['payload'];
        $payload = json_decode(base64_decode($payload),true);
        $public_key = $payload['public_key'];
        if($public_key == $_config['masternode_public_key']) {
            api_echo("PM: Origin node received message",0);
        }
        $id = $payload['id'];
        $cfg="msg-".$id;
        $sql="select * from config c where cfg = ?";
        $row = $db->run($sql, [$cfg], false);
        if($row) {
            api_echo("PM: Message already processed",0);
        }
        $signature = $envelope['signature'];
        $res = ec_verify($envelope['payload'], $signature, $public_key);
        if(!$res) {
            api_err("PM: Signature failed", 0);
        }

        $sql="insert into config (cfg, val) values (?, ?)";
        $res = $db->run($sql, [$cfg, time()], false);
        if($res === false) {
            api_err("PM: Error saving message", 0);
        }

        $message = $payload['message'];
        _log("PM: PROCESS MESSAGE $message");
        $msg=json_decode($message,true);
        $sender=$msg['sender'];
        $response = ["msg"=>$msg, "received"=>time()];
        $res = peer_post($sender.'/peer.php?q=processedMessage',$response);
        _log("PM: send event to master res=".$res);
            Propagate::message($envelope);
        api_echo(true);
        }

    static function processedMessage() {
        $data = self::$data;
        $received = $data['received'];
        $sent = $data['msg']['time'];
        $elapsed = $received - $sent;
        $returned = time() - $received;
        _log("PM: processedMessage on peer ".self::$peer['hostname']." data: ".json_encode($data)." received: "
            .$received." sent: ".$sent." elapsed: ".$elapsed." returned: ".$returned);
        api_echo(true);
    }

	static function logPropagate() {
		$data = self::$data;
		$data = base64_decode($data);
		$data = json_decode($data, true);
		self::emitToScoket("logPropagate", $data);
	}

    static function peerTest() {
        $t1 = self::$data;
        _log("PP: received peerTest  data=".json_encode(self::$data));
        $url = self::$peer['hostname'] . "/peer.php?q=peerTest2";
        $data['t1']=$t1;
        $data['t2']=time();
        $data['t2-t1']=$data['t2'] - $data['t1'];
        sleep(5);
        $res = peer_post($url, $data);
        _log("PP: call back ".self::$peer['hostname']." res=".$res);
        api_echo($res);
    }

    static function peerTest2() {
        _log("PP: received peerTest2 request data=".json_encode(self::$data));
        $data = self::$data;
        $data['t3']=time();
        $data['t3-t2']=$data['t3']-$data['t2'];
        $data['t3-t1']=$data['t3']-$data['t1'];
        sleep(5);
        _log("PP: send response");
        api_echo($data);
    }

	static function logSubmitBlock() {
		$data = self::$data;
		$data = base64_decode($data);
		$data = json_decode($data, true);
		self::emitToScoket("logSubmitBlock", $data);
	}

	static function emitToScoket($event, $data) {
        global $_config;
		$log = ["event"=>$event, "data"=>$data, "hostname" => $_config['hostname']];
		$res = peer_post("http://node1.phpcoin.net:3001/emit", $log);
		api_echo("OK");
	}

	static function submitBlockNew() {
		$ip = self::$ip;
		$data = self::$data;
		global $_config;

		if(Config::getVal("blockchain_invalid") == 1) {
			api_err("invalid-peer");
		}

		$current = Block::current();

		$diff = $current['height']-$data['height'];
		$hostname="";
		if(self::$peer) {
			$hostname = @self::$peer['hostname'];
		}
		// receive a  new block from a peer
		_logp("submitBlock: Receive new block from a peer $ip hostname=$hostname : id=".$data['id']." height=".$data['height']." current=".$current['height']. " diff=".$diff, 5);

		if($diff < 0) {
			if($diff == -1) {
//				api_echo("block-ok");
				$peer_block = Block::getFromArray($data);
				$res = $peer_block->check($err);
				if(!$res) {
					_logf("block check failed: $err", 5);
					api_err("invalid-block");
				}
				_logp("received block is checked ok", 5);
				$peer_block->prevBlockId = $current['id'];
				$res = $peer_block->add($err);
				if(!$res) {
					_logf("Error adding block: $err", 5);
					api_err("invalid-block");
				}
				_logp("added block - ok", 5);
				Propagate::blockToAll($data['id']);
				api_echo("block-ok");
			} else {
				_logf("we are on lower block", 5);
//				if(self::$peer) {
//					$dir = ROOT."/cli";
//					$cmd = "php $dir/peersync.php ".self::$peer['hostname'];
//					$check_cmd = "php $dir/peersync.php";
//					_log("submitBlock: run peer sync with ".self::$peer['hostname']);
//					Nodeutil::runSingleProcess($cmd, $check_cmd);
//				}
				api_echo("peer-sync");
			}
		} else if ($diff > 0) {
			$block=Block::export("",$data['height']);
			if(!$block) {
				_logf("we do not have block at height ".$data['height']);
				api_err("peer-error");
			}
			_logp("compare blocks our=".$block['id']." remote=".$data['id']);
			if($block['id'] == $data['id']) {
				_logf("blocks are same");
				api_echo("block-ok");
			}
			_logp("submitBlock: BLOCKS ARE NOT SAME elapsed=".$block['elapsed'].",".$data['elapsed'], 5);
			$res = NodeSync::compareBlocks($block, $data);
			if($res>0) {
				_logf("my block is winner");
				if(self::$peer) {
					Propagate::blockToPeer(self::$peer['hostname'], self::$peer['ip'], $block['id']);
				}
				api_err("block-not-ok");
			} else if ($res<0) {
				_logf("other block is winner");
				api_err("block-ok");
			} else {
				_logf("blocks are actually same");
				api_err("block-ok");
			}
		} else {
			_logp("heights are equal", 5);
			if($current['id']==$data['id']) {
				_logf("blocks are same on same height");
				api_echo("block-ok");
			}
			_logp("BLOCKS ARE NOT SAME ON SAME HEIGHT", 5);
			$current = Block::export($current['id']);
			$res = NodeSync::compareBlocks($current, $data);
			if($res>0) {
				_logf("my block is winner", 5);
				if(self::$peer) {
					Propagate::blockToPeer(self::$peer['hostname'], self::$peer['ip'], $current['id']);
				}
				api_err("block-not-ok");
			} else if ($res<0) {
				_logf("other block is winner", 5);
				if(self::$peer) {
					$dir = ROOT."/cli";
					$cmd = "php $dir/deepcheck.php ".self::$peer['hostname']. " ".$data['height'];
					$check_cmd = "php $dir/deepcheck.php";
					_log("submitBlock: run deep check with ".self::$peer['hostname'], 5);
					Nodeutil::runSingleProcess($cmd, $check_cmd);
				}
				api_err("block-not-ok");
			} else {
				_logf("blocks are actually same", 5);
				api_err("block-ok");
			}
		}
	}

	static function deepCheck() {
		$hostname = self::$peer['hostname'];
		_log("Start run deepCheck",4);
		$dir = ROOT."/cli";
		$cmd = "php $dir/deepcheck.php $hostname";
        $check_cmd = "php $dir/deepcheck.php";
		Nodeutil::runSingleProcess($cmd, $check_cmd);
		api_echo(["status"=>"started","hostname"=>$hostname]);
	}

    static function checkMyPeer() {
        $peer = Peer::getByIp(self::$ip);
        api_echo($peer);
    }
    
    static function getDbBlocks() {
        global $db;
        $data = self::$data;
        $height = $data['height'];
        $limit = $data['limit'];
        $max_height = $height + $limit;
        $maxheight = Block::getHeight();

        $sql="select * from blocks b where b.height > ? and b.height <= ? order by b.height";
        $blocks = $db->run($sql,[$height,$max_height], false);

        $sql="select * from transactions t where t.height > ? and t.height <= ? order by t.height";
        $txs = $db->run($sql,[$height,$max_height], false);


        $sql="select * from smart_contracts where height > ? and height <= ? order by height";
        $smart_contracts = $db->run($sql,[$height,$max_height], false);

        $sql="select * from smart_contract_state where height > ? and height <= ? order by height";
        $smart_contract_state = $db->run($sql,[$height,$max_height], false);

        $accounts = [];
        $masternodes = [];
        if($max_height >= Block::getHeight()) {
            $sql = "select * from accounts order by id";
            $accounts = $db->run($sql, [], false);

            $sql = "select * from masternode";
            $masternodes = $db->run($sql, [], false);
        }

        $block = Block::get($height);

        $res=[
            "block"=>$block,
            "maxheight"=>$maxheight,
            "blocks"=>$blocks,
            "transactions"=>$txs,
            "accounts"=>$accounts,
            "masternodes"=>$masternodes,
            "smart_contracts"=>$smart_contracts,
            "smart_contract_state"=>$smart_contract_state,
        ];
        api_echo($res);
    }

}
