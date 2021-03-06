<?php

class PeerRequest
{

	public static $ip;
	public static $data;
	public static $requestId;
	public static $info;

	static function processRequest() {
		global $_config;
		if(isset($_config) && $_config['offline']==true) {
			api_err("Peer is set to offline");
		}
		if (!empty($_POST['data'])) {
			$data = json_decode(trim($_POST['data']), true);
		}
		global $_config;
		if ($_POST['coin'] != COIN) {
			api_err("Invalid coin ".print_r($_REQUEST, 1));
		}
		if(isset($_POST['network'])) {
			if($_POST['network'] != NETWORK) {
				api_err("Invalid network ".$_POST['network']);
			}
		}
		if(version_compare($_POST['version'], MIN_VERSION) < 0) {
			api_err("Invalid version ".print_r($_REQUEST, 1));
		}
		$ip = Nodeutil::getRemoteAddr();
		$requestId = $_POST['requestId'];
		_log("Peer request from IP = $ip requestId=$requestId",4);

		$info = $_POST['info'];

		$ip = Peer::validateIp($ip);
		_log("Filtered IP = $ip",4);

		if(($ip === false || strlen($ip)==0)) {
			api_err("Invalid peer IP address");
		}

		if($_config['testnet']) {
			$ip = $ip . ":8001";
		}

		if(!Blacklist::checkIp($ip)) {
			api_err("blocked-ip");
		}

		if(!empty($info)) {
			Peer::updatePeerInfo($ip, $info);
		}

		self::$ip=$ip;
		self::$data=$data;
		self::$requestId=$requestId;
		self::$info = $info;
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
		// if we have enough peers, add it to DB as reserve
		$res = Peer::getCount(true);
		$reserve = 1;
		if ($res < $_config['max_peers']) {
			$reserve = 0;
		}
		_log("Inserting $hostname in peer db",3);
		$res = Peer::insert($ip, $hostname, $reserve);
		_log("Inserted $hostname = $res",3);
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


		// no transactions accepted if the sync is running
		if (Config::isSync()) {
			api_err("sync");
		}

		// validate transaction data
		if (!$tx->verify(0, $txerr)) {
			api_err("Invalid transaction: $txerr");
		}
		$hash = $tx->id;
		// make sure it's not already in mempool
		$res = Mempool::existsTx($hash);
		if ($res != 0) {
			api_err("The transaction is already in mempool");
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

		// add to mempool
		$tx->add_mempool(self::$ip);

		// rebroadcast the transaction to some peers unless the transaction is smaller than the average size of transactions in mempool - protect against garbage data flooding
//		$res = $db->row("SELECT COUNT(1) as c, sum(val) as v FROM  mempool ", [":src" => $tx->src]);
//		if ($res['c'] < $_config['max_mempool_rebroadcast'] && $res['v'] / $res['c'] < $tx->val) {
		$dir = ROOT."/cli";
		system( "php $dir/propagate.php transaction '{$tx->id}'  > /dev/null 2>&1  &");
//		}
		api_echo("transaction-ok");
	}

	static function submitBlock() {
		$ip = self::$ip;
		$data = self::$data;
		global $_config;

		$current = Block::current();

		// receive a  new block from a peer
		_log("Sync: Receive new block from a peer $ip : id=".$data['id']." height=".$data['height']." current=".$current['height'], 5);

//		Peer::updateHeight($ip, $data);

		// if sync, refuse all
		if (Config::isSync()) {
			_log('['.$ip."] Block rejected due to sync", 5);
			api_err("sync");
		}
		$data['id'] = san($data['id']);
		// block already in the blockchain
		if ($current['id'] == $data['id']) {
			_log("block-ok",3);
			api_echo("block-ok");
		}
		if ($data['date'] > time() + 30) {
			_log("block in the future");
			api_err("block in the future");
		}

		//_log("DFSH: current_height=".$current['height']." data_height=".$data['height']." current_id=".$current['id']." data_id=".$data['id']);

		if ($current['height'] == $data['height'] && $current['id'] != $data['id']) {
			// different forks, same height
			$accept_new = false;
			//_log("DFSH: DIFFERENT FORKS SAME HEIGHT", 3);
			_log("data ".json_encode($data),3);
			_log("current ".json_encode($current),3);

			//wins block with lowest elapsed time - highest difficulty
			$difficulty1 = $current['difficulty'];
			$difficulty2 = $data['difficulty'];
			//_log("DFSH: compare difficulty: difficulty1=$difficulty1 difficulty2=$difficulty2");
			if($difficulty1 > $difficulty2) {
				$accept_new = true;
			}  else if ($difficulty1 == $difficulty2) {
				$date1 = $current['date'];
				$date2 = $data['date'];
				//_log("DFSH: compare date: date1=$date1 date2=$date2");
				if($date1 > $date2) {
					$accept_new = true;
				} else if ($date1 == $date2) {
					$miner1=$current['miner'];
					$miner2=$data['miner'];
					$generator1=$current['generator'];
					$generator2=$data['generator'];
					//_log("DFSH: compare miners: miner1=$miner1 miner2=$miner2");
					//_log("DFSH: compare generator: generator1=$generator1 generator2=$generator2");
					$id1=$current['id'];
					$id2=$data['id'];
					//_log("DFSH: compare ids: id1=$id1 id2=$id2");
					if(strcmp($id1, $id2)) {
						$accept_new = true;
					}
				}
			}

			//_log("DFSH: Accept new = ".$accept_new);

			if ($accept_new) {
				// if the new block is accepted, run a microsync to sync it
				_log('['.$ip."] Starting microsync - $data[height]",1);
				$ip=escapeshellarg($ip);
				$dir = ROOT."/cli";
				system(  "php $dir/microsync.php '$ip'  > /dev/null 2>&1  &");
				api_echo("microsync");
			} else {
				_log('['.$ip."] suggesting reverse-microsync - $data[height]",1);
				api_echo("reverse-microsync"); // if it's not, suggest to the peer to get the block from us
			}
		}
		// if it's not the next block
		if ($current['height'] != $data['height'] - 1) {
			//_log("DFSH: if it's not the next block",1);
			// if the height of the block submitted is lower than our current height, send them our current block
			if ($data['height'] < $current['height']) {
				//_log("DFSH: Our height is higher");
				$pr = Peer::getByIp($ip);
				if (!$pr) {
					//_log("DFSH: No peer by IP $ip");
					api_err("block-too-old");
				}
				$peer_host = escapeshellcmd(base58_encode($pr['hostname']));
				$pr['ip'] = escapeshellcmd(san_ip($pr['ip']));
				$dir = ROOT."/cli";
				system( "php $dir/propagate.php block current '$peer_host' '$pr[ip]'   > /dev/null 2>&1  &");
				_log('['.$ip."] block too old, sending our current block - $current[height]",3);
				//_log("DFSH: Send our block to peer ".$pr['hostname']);
				api_err("block-too-old");
			}
			// if the block difference is bigger than 150, nothing should be done. They should sync
			if ($data['height'] - $current['height'] > 150) {
				_log('['.$ip."] block-out-of-sync - $data[height]",2);
				//_log("DFSH: block difference higher than 150");
				api_err("block-out-of-sync");
			}
			// request them to send us a microsync with the latest blocks
			//_log('DFSH: ['.$ip."] requesting microsync - $current[height] - $data[height]",2);
			api_echo(["request" => "microsync", "height" => $current['height'], "block" => $current['id']]);
		}
		// check block data
		$block = Block::getFromArray($data);
		if (!$block->check()) {
			_log('['.$ip."] invalid block - $data[height]",1);
			api_err("invalid-block");
		}
		$block->prevBlockId = $current['id'];

		if (Config::isSync()) {
			//_log('DFSH: ['.$ip."] Block rejected due to sync", 5);
			api_err("sync");
		}

		$current = Block::current();
		//_log("DFSH: check add BLOCK ".$block->height. " current=".$current['height']);
		if($block->height == $current['height']) {
			api_echo("block-ok");
		}

		$lock_file = ROOT . "/tmp/lock-block-".$block->height;
		_log("Check lock file $lock_file", 5);
		if (!mkdir($lock_file, 0700)) {
			_log("Lock file exists $lock_file", 3);
			api_echo("sync");
		}

		//_log("DFSH: ADD BLOCK ".$block->height);
		$res = $block->add($error);

		_log("Remove lock file $lock_file", 5);
		@rmdir($lock_file);

		if (!$res) {
			//_log('DFSH: ['.$ip."] invalid block data - $data[height] Error:$error",1);
			api_err("invalid-block-data $error");
		}

		$last_block = Block::export("", $data['height']);
		$bl = Block::getFromArray($last_block);
		$res = $bl->verifyBlock();

		if (!$res) {
			//_log("DFSH: Can not verify added block",1);
			api_err("invalid-block-data");
		}

		//_log('DFSH: ['.$ip."] block ok, repropagating - $data[height]",1);

		// send it to all our peers
		$data['id']=escapeshellcmd(san($data['id']));
		$dir = ROOT."/cli";
		system("php $dir/propagate.php block '$data[id]' all all linear > /dev/null 2>&1  &");
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

	static function getApps() {
		global $_config;
		if ($_config['repository']) {
			_log("AppsHash: Received request getApps", 3);
			$appsHashFile = Nodeutil::getAppsHashFile();
			$buildArchive = false;
			if (!file_exists($appsHashFile)) {
				$buildArchive = true;
				$appsHashCalc = calcAppsHash();
			} else {
				$appsHash = file_get_contents($appsHashFile);
				_log("AppsHash: Read apps hash from file = ".$appsHash, 3);
				$appsHashTime = filemtime($appsHashFile);
				$now = time();
				$elapsed = $now - $appsHashTime;
				_log("AppsHash: Elapsed check time $elapsed", 3);
				if ($elapsed > 60) {
					$appsHashCalc = calcAppsHash();
					_log("AppsHash: Calculated apps hash = ".$appsHashCalc);
					if ($appsHashCalc != $appsHash) {
						$buildArchive = true;
					}
				} else {
					$appsHashCalc = $appsHash;
				}
			}
			if ($buildArchive) {
				_log("AppsHash: build archive", 2);
				file_put_contents($appsHashFile, $appsHashCalc);
				buildAppsArchive();
				$dir = ROOT . "/cli";
				_log("AppsHash: Propagating apps",3);
				system("php $dir/propagate.php apps $appsHashCalc > /dev/null 2>&1  &");
			} else {
				_log("AppsHash: No need to build archive",2);
			}
			$signature = ec_sign($appsHashCalc, $_config['repository_private_key']);
			api_echo(["hash" => $appsHashCalc, "signature" => $signature]);
		} else {
			api_err("AppsHash: No repository server");
		}
	}

	static function updateApps() {

		global $_config;
		if(!defined("APPS_REPO_SERVER")) {
			if($_config['testnet'] ) {
				define("APPS_REPO_SERVER", "https://repo.testnet.phpcoin.net:8001");
			} else {
				define("APPS_REPO_SERVER", "https://repo.phpcoin.net");
			}
		}
		if(!defined("APPS_REPO_SERVER_PUBLIC_KEY")) {
			if($_config['testnet'] ) {
				define("APPS_REPO_SERVER_PUBLIC_KEY", "PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCwUKtSuRJEs8RrRrkZbND1WxVNomPtvowAo5hzQr6xe2TUyHYLnzu2ubVMfBAYM4cBZJLckvxWenHB2nULzmU8VHz");
			} else {
				define("APPS_REPO_SERVER_PUBLIC_KEY", "PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCyHWjnG15LHdWRRbNEmAPiYcyCqFZm1VKi8QziKYbMtrXUw8rqhrS3EEoyJxXASNZid9CsB1dg64u5sYgnUsrZg7C");
			}
		}

		$data = self::$data;
		$hash = $data['hash'];
		$appsHashFile = Nodeutil::getAppsHashFile();
		$appsHash = file_get_contents($appsHashFile);
		_log("PeerApps: received update apps hash=$hash localHash=$appsHash",3);
		if($appsHash == $hash && false) {
			_log("PeerApps: No need to update apps",3);
			api_err("No need to update apps");
		} else {
			$url = APPS_REPO_SERVER."/peer.php?q=getApps";
			$res = peer_post($url,[],30, $err);
			_log("PeerApps: Contancting repo server $url response=".json_encode($res),3);
			if($res === false) {
				_log("PeerApps: No response from repo server: $err",2);
				api_err("No response from repo server: $err");
			} else {
				$res = Nodeutil::downloadApps($error);
				if($res) {
					api_echo("OK");
				} else {
					api_err("Error downloading apps: $error");
				}
			}
		}
	}
	static function updateDapps() {
		Dapps::updateDapps(self::$data, self::$ip);
	}

	static function checkDapps() {
		Dapps::checkDapps(self::$data['dapps_id'], self::$ip);
	}

	static function updateMasternode() {
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

	static function propagateMsg() {
		global $_config;
		$data = self::$data;
		$data = $data['data'];
		$data = base64_decode($data);
		$data = json_decode($data, true);

		if(!isset($_config['propagate_msg-enable'])) {
			_log("Msg propagate: Not enabled. Stop");
			return;
		}

		if(count($data['hops'])>10) {
			_log("Msg propagate: max hops exceed. Stop");
			return;
		}

		$hops = $data['hops'];
		foreach($hops as $hop) {
			if($hop['node'] == $_config['hostname']) {
				_log("Msg propagate: already added this host to hop. Stop");
				return;
			}
		}

		$address = $data['source']['address'];
		$public_key = $_config['repository_public_key'];
		if(!empty($public_key) && Account::getAddress($public_key)==$address) {
			$hops_cnt = count($data['hops']);
			$elapsed = microtime(true) - $data['source']['time'];
			_log("Msg propagate: Hop Hostname Time");
			foreach($hops as $index=> $hop) {
				_log("Msg propagate: ".($index+1)." ".$hop['node']." ".($hop['time']-$data['source']['time']));
			}
			_log("Msg propagate: Total elapsed $elapsed");
			return;
		}
		$hop = [
			"node"=>$_config['hostname'],
			"time"=>microtime(true)
		];
		$data['hops'][]=$hop;
		$peers = Peer::getPeersForSync();
		$dir = ROOT."/cli";
		$msg = base64_encode(json_encode($data));
		foreach($peers  as $peer) {
			$hostname = $peer['hostname'];
			$peer = base64_encode($hostname);
			$cmd = "php $dir/propagate.php message $peer $msg > /dev/null 2>&1  &";
			system($cmd);
		}
	}

}
