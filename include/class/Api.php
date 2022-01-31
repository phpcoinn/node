<?php

class Api
{

	static function checkAccess() {
		$ip = Nodeutil::getRemoteAddr();
		$ip = filter_var($ip, FILTER_VALIDATE_IP);
		global $_config;
		if ($_config['public_api'] == false && !in_array($ip, $_config['allowed_hosts'])) {
			api_err("private-api");
		}

	}

	static function getData() {
		if (!empty($_POST['data'])) {
			$data = json_decode($_POST['data'], true);
		} else {
			$data = $_GET;
		}
		return $data;
	}

	/**
	 * @api {get} /api.php?q=getAddress getAddress
	 * @apiName getAddress
	 * @apiGroup API
	 * @apiDescription Converts the public key to an PHP address.
	 *
	 * @apiParam {string} public_key The public key
	 *
	 * @apiSuccess {string} data Contains the address
	 */
	static function getAddress($data) {
		$public_key = $data['public_key'];
		if (strlen($public_key) < 32) {
			api_err("Invalid public key");
		}
		api_echo(Account::getAddress($public_key));
	}

	/**
	 * @api {get} /api.php?q=base58  base58
	 * @apiName base58
	 * @apiGroup API
	 * @apiDescription Converts a string to base58.
	 *
	 * @apiParam {string} data Input string
	 *
	 * @apiSuccess {string} data Output string
	 */
	static function base58($data) {
		api_echo(base58_encode($data['data']));
	}

	/**
	 * @api {get} /api.php?q=getBalance  getBalance
	 * @apiName getBalance
	 * @apiGroup API
	 * @apiDescription Returns the balance of a specific address or public key.
	 *
	 * @apiParam {string} [public_key] Public key
	 * @apiParam {string} [address] Address
	 *
	 * @apiSuccess {string} data The PHP balance
	 */
	static function getBalance($data) {
		$public_key = $data['public_key'];
		$address = $data['address'];
		if (!empty($public_key) && strlen($public_key) < 32) {
			api_err("Invalid public key");
		}
		if (!empty($public_key)) {
			$address = Account::getAddress($public_key);
		}
		if (empty($address)) {
			api_err("Invalid address");
		}
		$address = san($address);
		if(Account::valid($address)) {
			api_echo(Account::getBalance($address));
		} else {
			api_err("Invalid address");
		}
	}

	/**
	 * @api {get} /api.php?q=getPendingBalance  getPendingBalance
	 * @apiName getPendingBalance
	 * @apiGroup API
	 * @apiDescription Returns the pending balance, which includes pending transactions, of a specific address or public key.
	 *
	 * @apiParam {string} [public_key] Public key
	 * @apiParam {string} [address] Address
	 *
	 * @apiSuccess {string} data The PHP balance
	 */
	static function getPendingBalance($data) {
		$address = $data['address'];
		$public_key = san($data['public_key'] ?? '');
		if (!empty($public_key) && strlen($public_key) < 32) {
			api_err("Invalid public key");
		}
		if (!empty($public_key)) {
			$address = Account::getAddress($public_key);
		}
		if (empty($address)) {
			api_err("Invalid address");
		}
		$address = san($address);
		if (!Account::valid($address)) {
			api_err("Invalid address");
		}
		api_echo(Account::pendingBalance($address));
	}

	/**
	 * @api {get} /api.php?q=getMempoolBalance  getMempoolBalance
	 * @apiName getMempoolBalance
	 * @apiGroup API
	 * @apiDescription Returns only balance in mempool of a specific address or public key.
	 *
	 * @apiParam {string} [public_key] Public key
	 * @apiParam {string} [address] Address
	 *
	 * @apiSuccess {string} data The PHP balance in mempool
	 */
	static function getMempoolBalance($data) {
		$address = $data['address'];
		if (empty($address)) {
			api_err("Invalid address");
		}
		$address = san($address);
		if (!Account::valid($address)) {
			api_err("Invalid address");
		}
		api_echo(Mempool::mempoolBalance($address));
	}

	/**
	 * @api {get} /api.php?q=getTransactions  getTransactions
	 * @apiName getTransactions
	 * @apiGroup API
	 * @apiDescription Returns the latest transactions of an address.
	 *
	 * @apiParam {string} [public_key] Public key
	 * @apiParam {string} [address] Address
	 * @apiParam {numeric} [limit] Number of confirmed transactions, max 100, min 1
	 *
	 * @apiSuccess {string} block  Block ID
	 * @apiSuccess {numeric} confirmation Number of confirmations
	 * @apiSuccess {numeric} date  Transaction's date in UNIX TIMESTAMP format
	 * @apiSuccess {string} dst  Transaction destination
	 * @apiSuccess {numeric} fee  The transaction's fee
	 * @apiSuccess {numeric} height  Block height
	 * @apiSuccess {string} id  Transaction ID/HASH
	 * @apiSuccess {string} message  Transaction's message
	 * @apiSuccess {string} public_key  Account's public_key
	 * @apiSuccess {string} sign Sign of transaction related to address
	 * @apiSuccess {string} signature  Transaction's signature
	 * @apiSuccess {string} src  Sender's address
	 * @apiSuccess {numeric} type Transaction type
	 * @apiSuccess {string} type_label Transaction label
	 * @apiSuccess {numeric} val Transaction value
	 */
	static function getTransactions($data) {
		$address = san($data['address']);
		$public_key = san($data['public_key'] ?? '');
		if (!empty($public_key) && strlen($public_key) < 32) {
			api_err("Invalid public key");
		}
		if (!empty($public_key)) {
			$address = Account::getAddress($public_key);
		}
		if (empty($address)) {
			api_err("Invalid address");
		}
		if(!Account::valid($address)) {
			api_err("Invalid address");
		}
		$limit = intval($data['limit']);
		$transactions = Transaction::getByAddress($address, $limit);
		api_echo($transactions);
	}

	/**
	 * @api {get} /api.php?q=getTransaction  getTransaction
	 * @apiName getTransaction
	 * @apiGroup API
	 * @apiDescription Returns one transaction.
	 *
	 * @apiParam {string} transaction Transaction ID
	 *
	 * @apiSuccess {string} block  Block ID
	 * @apiSuccess {numeric} height  Block height
	 * @apiSuccess {string} id  Transaction ID/HASH
	 * @apiSuccess {string} dst  Transaction destination
	 * @apiSuccess {numeric} val Transaction value
	 * @apiSuccess {numeric} fee  The transaction's fee
	 * @apiSuccess {string} signature  Transaction's signature
	 * @apiSuccess {string} message  Transaction's message
	 * @apiSuccess {string} type  Transaction type
	 * @apiSuccess {numeric} date  Transaction's date in UNIX TIMESTAMP format
	 * @apiSuccess {string} public_key  Account's public_key
	 * @apiSuccess {numeric} confirmation Number of confirmations
	 * @apiSuccess {string} type_label  Transaction type label
	 */
	static function getTransaction($data) {
		$id = san($data['transaction']);
		$res = Transaction::get_transaction($id);
		if ($res === false) {
			$res = Transaction::get_mempool_transaction($id);
			if ($res === false) {
				api_err("invalid transaction");
			}
		}
		api_Echo($res);
	}

	/**
	 * @api {get} /api.php?q=getPublicKey  getPublicKey
	 * @apiName getPublicKey
	 * @apiGroup API
	 * @apiDescription Returns the public key of a specific address.
	 *
	 * @apiParam {string} address Address
	 *
	 * @apiSuccess {string} data The public key
	 */
	static function getPublicKey($data) {
		$address = san($data['address']);
		if (empty($address)) {
			api_err("Invalid account id");
		}
		if(!Account::valid($address)) {
			api_err("Invalid address");
		}
		$public_key = Account::publicKey($address);
		if ($public_key === false) {
			api_err("No public key found for this account");
		} else {
			api_echo($public_key);
		}
	}

	/**
	 * @api {get} /api.php?q=generateAccount  generateAccount
	 * @apiName generateAccount
	 * @apiGroup API
	 * @apiDescription Generates a new account.
	 *
	 * @apiSuccess {string} address Account address
	 * @apiSuccess {string} public_key Public key
	 * @apiSuccess {string} private_key Private key
	 */
	static function generateAccount($data) {
		$res = Account::generateAcccount();
		api_echo($res);
	}

	/**
	 * @api {get} /api.php?q=currentBlock  currentBlock
	 * @apiName currentBlock
	 * @apiGroup API
	 * @apiDescription Returns the current block.
	 *
	 * @apiSuccess {string} id Block id
	 * @apiSuccess {string} generator Block Generator
	 * @apiSuccess {numeric} height Height
	 * @apiSuccess {numeric} date Block's date in UNIX TIMESTAMP format
	 * @apiSuccess {string} nonce Mining nonce
	 * @apiSuccess {string} signature Signature signed by the generator
	 * @apiSuccess {numeric} difficulty The base target / difficulty
	 * @apiSuccess {numeric} transactions Number of transactions in block
	 * @apiSuccess {string} version Block version
	 * @apiSuccess {string} argon Mining argon hash
	 * @apiSuccess {string} miner Miner who found block
	 */
	static function currentBlock($data) {
		$current = Block::current();
		api_echo($current);
	}

	/**
	 * @api {get} /api.php?q=getBlock  getBlock
	 * @apiName getBlock
	 * @apiGroup API
	 * @apiDescription Returns the block.
	 *
	 * @apiParam {numeric} height Block Height
	 *
	 * @apiSuccess {string} id Block id
	 * @apiSuccess {string} generator Block Generator
	 * @apiSuccess {numeric} height Height
	 * @apiSuccess {numeric} date Block's date in UNIX TIMESTAMP format
	 * @apiSuccess {string} nonce Mining nonce
	 * @apiSuccess {string} signature Signature signed by the generator
	 * @apiSuccess {numeric} difficulty The base target / difficulty
	 * @apiSuccess {numeric} transactions Number of transactions in block
	 * @apiSuccess {string} version Block version
	 * @apiSuccess {string} argon Mining argon hash
	 * @apiSuccess {string} miner Miner who found block
	 * @apiSuccess {array} data List of transactions in block
	 */
	static function getBlock($data) {
		$height = san($data['height']);
		$ret = Block::export("", $height);
		if ($ret == false) {
			api_err("Invalid block");
		} else {
			api_echo($ret);
		}
	}

	/**
	 * @api {get} /api.php?q=getBlockTransactions  getBlockTransactions
	 * @apiName getBlockTransactions
	 * @apiGroup API
	 * @apiDescription Returns the transactions of a specific block.
	 *
	 * @apiParam {numeric} [height] Block Height
	 * @apiParam {string} [block] Block id
	 * @apiParam {boolean} [includeMiningRewards] Include mining rewards
	 *
	 * @apiSuccess {string} block  Block ID
	 * @apiSuccess {numeric} confirmations Number of confirmations
	 * @apiSuccess {numeric} date  Transaction's date in UNIX TIMESTAMP format
	 * @apiSuccess {string} dst  Transaction destination
	 * @apiSuccess {numeric} fee  The transaction's fee
	 * @apiSuccess {numeric} height  Block height
	 * @apiSuccess {string} id  Transaction ID/HASH
	 * @apiSuccess {string} message  Transaction's message
	 * @apiSuccess {string} public_key  Account's public_key
	 * @apiSuccess {string} signature  Transaction's signature
	 * @apiSuccess {string} src  Sender's address
	 * @apiSuccess {numeric} type Transaction type
	 * @apiSuccess {string} type_label Transaction type label
	 * @apiSuccess {numeric} val Transaction value
	 */
	static function getBlockTransactions($data) {
		$height = san($data['height']);
		$block = san($data['block']);
		$includeMiningRewards = (
			isset($data['includeMiningRewards']) &&
			!($data['includeMiningRewards'] === '0' || $data['includeMiningRewards'] === 'false')
		);

		$ret = Transaction::get_transactions($height, $block, $includeMiningRewards);

		if ($ret === false) {
			api_err("Invalid block");
		} else {
			api_echo($ret);
		}
	}

	/**
	 * @api {get} /api.php?q=version version
	 * @apiName version
	 * @apiGroup API
	 * @apiDescription Returns the node's version.
	 *
	 *
	 * @apiSuccess {string} data  Version
	 */
	static function version($data) {
		api_echo(VERSION);
	}

	/**
	 * @api {get} /api.php?q=send  send
	 * @apiName send
	 * @apiGroup API
	 * @apiDescription Sends a transaction.
	 *
	 * @apiParam {numeric} val Transaction value (without fees)
	 * @apiParam {string} dst Destination address
	 * @apiParam {string} public_key Sender's public key
	 * @apiParam {string} [signature] Transaction signature. It's recommended that the transaction is signed before being sent to the node to avoid sending your private key to the node.
	 * @apiParam {numeric} [date] Transaction's date in UNIX TIMESTAMP format. Requried when the transaction is pre-signed.
	 * @apiParam {string} [message] A message to be included with the transaction. Maximum 128 chars.
	 * @apiParam {numeric} [type] The type of the transaction. 1 to send coins.
	 *
	 * @apiSuccess {string} data  Transaction id
	 */
	static function send($data) {
		global $_config;
		_log("API send", 1);

		$type = intval($data['type']);
		$dst = san($data['dst']);

		$public_key = san($data['public_key']);
		$signature = san($data['signature']);
		$date = $data['date'] + 0;
		if ($date == 0) {
			$date = time();
		}
		$message=$data['message'];
		$val = $data['val'] + 0;
		$transaction = new Transaction($public_key,$dst,$val,$type,$date,$message);
		$transaction->signature = $signature;
		$hash = $transaction->addToMemPool($error);

		if($hash === false) {
			api_err($error);
		}
		api_echo($hash);
	}

	/**
	 * @api {get} /api.php?q=mempoolSize  mempoolSize
	 * @apiName mempoolSize
	 * @apiGroup API
	 * @apiDescription Returns the number of transactions in mempool.
	 *
	 * @apiSuccess {numeric} data  Number of mempool transactions
	 */
	static function mempoolSize($data) {
		$res = Mempool::getSize();
		api_echo($res);
	}

	/**
	 * @api {get} /api.php?q=checkSignature  checkSignature
	 * @apiName checkSignature
	 * @apiGroup API
	 * @apiDescription Checks a signature against a public key
	 *
	 * @apiParam {string} [public_key] Public key
	 * @apiParam {string} [signature] signature
	 * @apiParam {string} [data] signed data
	 *
	 *
	 * @apiSuccess {boolean} data true or false
	 */
	static function checkSignature($data) {
		$public_key=san($data['public_key']);
		$signature=san($data['signature']);
		$data=$data['data'];
		api_echo(Account::checkSignature($data, $signature, $public_key));
	}

	/**
	 * @api            {get} /api.php?q=sync sync
	 * @apiName        sync
	 * @apiGroup       API
	 * @apiDescription Returns details about the node's sync process.
	 *
	 * @apiSuccess {object}  data A collection of data about the sync process.
	 * @apiSuccess {boolean} data.sync_running Whether the sync process is currently running.
	 * @apiSuccess {number}  data.last_sync The timestamp for the last time the sync process was run.
	 * @apiSuccess {boolean} data.sync Whether the sync process is currently synchronising.
	 */
	static function sync($data) {
		global $db;
		$syncRunning = file_exists(Nodeutil::getSyncFile());
		$lastSync = (int)$db->single("SELECT val FROM config WHERE cfg='sync_last'");
		$sync = (bool)$db->single("SELECT val FROM config WHERE cfg='sync'");
		api_echo(['sync_running' => $syncRunning, 'last_sync' => $lastSync, 'sync' => $sync]);
	}

	/**
	 * @api            {get} /api.php?q=nodeInfo  nodeInfo
	 * @apiName        nodeInfo
	 * @apiGroup       API
	 * @apiDescription Returns details about the node.
	 *
	 * @apiSuccess {object}  data A collection of data about the node.
	 * @apiSuccess {string} data.hostname The hostname of the node.
	 * @apiSuccess {string} data.version The current version of the node.
	 * @apiSuccess {string} data.network Node network.
	 * @apiSuccess {string} data.dbversion The database schema version for the node.
	 * @apiSuccess {number} data.accounts The number of accounts known by the node.
	 * @apiSuccess {number} data.transactions The number of transactions known by the node.
	 * @apiSuccess {number} data.mempool The number of transactions in the mempool.
	 * @apiSuccess {number} data.masternodes The number of masternodes known by the node.
	 * @apiSuccess {number} data.peers The number of valid peers.
	 * @apiSuccess {number} data.height Current height of node.
	 * @apiSuccess {number} data.block Current block id of node.
	 * @apiSuccess {number} data.time Current time on node.
	 */
	static function nodeInfo($data) {
		global $db;
		$dbVersion = $db->single("SELECT val FROM config WHERE cfg='dbversion'");
		$hostname = $db->single("SELECT val FROM config WHERE cfg='hostname'");
		$accounts = $db->single("SELECT COUNT(1) FROM accounts");
		$tr = $db->single("SELECT COUNT(1) FROM transactions");
		$masternodes = $db->single("SELECT COUNT(1) FROM masternode");
		$mempool = Mempool::getSize();
		$peers = Peer::getCount();
		$current = Block::current();
		api_echo([
			'hostname'     => $hostname,
			'version'      => VERSION,
			'network'      => NETWORK,
			'dbversion'    => $dbVersion,
			'accounts'     => $accounts,
			'transactions' => $tr,
			'mempool'      => $mempool,
			'masternodes'  => $masternodes,
			'peers'        => $peers,
			'height'       => $current['height'],
			'block'       => $current['id'],
			'time'       => time(),
		]);
	}

	/**
	 * @api            {get} /api.php?q=checkAddress  checkAddress
	 * @apiName        checkAddress
	 * @apiGroup       API
	 * @apiDescription Checks the validity of an address.
	 *
	 * @apiParam {string} address Address
	 * @apiParam {string} [public_key] Public key
	 *
	 * @apiSuccess {boolean} data True if the address is valid, false otherwise.
	 */
	static function checkAddress($data) {
		$address = $data['address'];
		$public_key = $data['public_key'];
		if (!Account::valid($address)) {
			api_err(false);
		}

		if (!empty($public_key)) {
			if (Account::getAddress($public_key) != $address) {
				api_err(false);
			}
		}
		api_echo(true);
	}

	/**
	 * @api            {get} /api.php?q=getPeers  getPeers
	 * @apiName        getPeers
	 * @apiGroup       API
	 * @apiDescription Return all peers from node
	 *
	 * @apiSuccess {numeric} id Id of peer in internal database (not relevant)
	 * @apiSuccess {string} hostname Peer hostname
	 * @apiSuccess {numeric} blacklisted UNIX timestamp until peer is blacklisted
	 * @apiSuccess {numeric} ping UNIX timestamp when peer was last pinged
	 * @apiSuccess {numeric} reserve (net relevant)
	 * @apiSuccess {numeric} fails Number of failed conections to peer
	 * @apiSuccess {numeric} stuckfail Number of failed stuck conentions to peer
	 * @apiSuccess {numeric} height Blockchain height of peer
	 * @apiSuccess {string} appshash Hash of peer apps
	 * @apiSuccess {numeric} score Peer node score
	 * @apiSuccess {string} blacklist_reason Reason why peer is blacklisted
	 * @apiSuccess {string} version Version of peer node
	 */
	static function getPeers($data) {
		$peers = Peer::getAll();
		api_echo($peers);
	}
}
