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
        } else if (file_get_contents("php://input")) {
            $data = json_decode(file_get_contents("php://input"), true);
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

    static function getBalances($data) {
        $addresses = $data['addresses'];
        if (empty($addresses)) {
            api_err("Missing addresses");
        }
        $addresses=json_decode($addresses, true);
        if (empty($addresses)) {
            api_err("Empty addresses");
        }
        api_echo(Account::getBalances($addresses));
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
	 * @apiParam {numeric} [offset] Offset for paginating transactions
	 * @apiParam {object} [filter] Additional parameters to filter query
	 * @apiParam {string} filter.address Filter transactions by address
	 * @apiParam {numeric} filter.type Filter transactions by type
	 * @apiParam {string} filter.dir Filter transactions by direction: send or receive
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
		$offset = intval($data['offset']);
		if(empty($offset)) {
			$offset = 0;
		}
		$transactions = Transaction::getByAddress($address, $limit, $offset, $data['filter']);
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
		if (empty($public_key)) {
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

		$ret = Transaction::get_transactions($height, $block);

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
		$val = $data['val'];
		$fee = $data['fee'];
		$transaction = new Transaction($public_key,$dst,$val,$type,$date,$message, $fee);
		$transaction->signature = $signature;

		if(isset($data['data'])) {
			$transaction->data = $data['data'];
		}

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
		$syncRunning = Config::isSync();
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
	 * @apiSuccess {string} data.generator Node who added block to blockchain
	 * @apiSuccess {string} data.miner Node who mined a block
	 * @apiSuccess {string} data.masternode Masternode who received reward for block
	 * @apiSuccess {string} data.totalSupply Total supply of coin
	 * @apiSuccess {string} data.currentSupply Current coin value in circulation
	 * @apiSuccess {string} data.avgBlockTime10 Average block time for last 10 blocks
	 * @apiSuccess {string} data.hashRate10 Hash rate for last 10 blocks
	 * @apiSuccess {string} data.hashRate100 Hash rate for last 100 blocks
	 * @apiSuccess {string} data.lastBlockTime Date of last block
	 */
	static function nodeInfo($data) {
        $basic = isset($data['basic']);
        $nocache = isset($data['nocache']);
		api_echo(Nodeutil::getNodeInfo($basic,$nocache));
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

    /**
     * @api            {get} /api.php?q=getMasternodes  getMasternodes
     * @apiName        getMasternodes
     * @apiGroup       API
     * @apiDescription Return all masternodes from node
     *
     * @apiSuccess {string} [public_key] Public key of masternode
     * @apiSuccess {numeric} [height] Height at which masternode is created
     * @apiSuccess {string} [ip] IP address of masternode
     * @apiSuccess {numeric} [win_height] Last height whem masternode received reward
     * @apiSuccess {string} [signature] current masternode signature
     * @apiSuccess {string} [id] Address of masternode
     * @apiSuccess {numeric} [collateral] Locked collateral in masternode
     * @apiSuccess {numeric} [verified] 1 if masternode is verified for current height
     */
	static function getMasternodes($data) {
		api_echo(Masternode::getAll());
	}

    /**
     * @api            {get} /api.php?q=getMasternodesForAddress  getMasternodesForAddress
     * @apiName        getMasternodesForAddress
     * @apiGroup       API
     * @apiDescription Return all masternodes created from specified address
     *
     * @apiParam {string} address Address
     *
     * @apiSuccess {numeric} [collateral] Locked collateral of masternode
     * @apiSuccess {numeric} [reward_address] Address which receives masternode rewards (if cold masternode)
     * @apiSuccess {string} [masternode_address] Address of masternode
     * @apiSuccess {numeric} [masternode_balance] Balance of masternode (hot or cold)
     *
     */
	static function getMasternodesForAddress($data) {
		$address = $data['address'];
		$public_key = Account::publicKey($address);
		if(!$public_key) {
			api_err("Invalid address");
		}
		api_echo(Account::getMasternodes($address));
	}

    /**
     * @api            {get} /api.php?q=getMasternode  getMasternode
     * @apiName        getMasternode
     * @apiGroup       API
     * @apiDescription Return masternode data
     *
     * @apiParam {string} address Address
     *
     * @apiSuccess {string} [public_key] Public key of masternode
     * @apiSuccess {numeric} [height] Height at which masternode is created
     * @apiSuccess {string} [ip] IP address of masternode
     * @apiSuccess {numeric} [win_height] Last height whem masternode received reward
     * @apiSuccess {string} [signature] current masternode signature
     * @apiSuccess {string} [id] Address of masternode
     * @apiSuccess {numeric} [collateral] Locked collateral in masternode
     * @apiSuccess {numeric} [verified] 1 if masternode is verified for current height
     */
	static function getMasternode($data) {
		$address = $data['address'];
		if(empty($address)) {
			api_err("Empty masternode address");
		}
		$public_key = Account::publicKey($address);
		$mn = Masternode::get($public_key);
		api_echo($mn);
	}

    /**
     * @api            {get} /api.php?q=getFee  getFee
     * @apiName        getFee
     * @apiGroup       API
     * @apiDescription Get current transaction fee
     *
     * @apiSuccess {numeric} Current transaction fee
     */
	static function getFee($data) {
		$fee = Blockchain::getFee($data['height']);
		api_echo(number_format($fee, 5));
	}

	static function getSmartContract($data) {
		$sc_address = $data['address'];
		$smartContract = SmartContract::getById($sc_address);
		api_echo($smartContract);
	}

	static function getSmartContractProperty($data) {
		$sc_address = $data['address'];
		$property = $data['property'];
		$key = $data['key'];
		if(empty($sc_address)) api_err("Smart contract address not specified");
		if(empty($property)) api_err("Smart contract property not specified");
		$res = SmartContractEngine::get($sc_address, $property, $key, $error);
		if($res === false) {
			api_err("Error getting Smart contract property: $error");
		}
		api_echo($res);
	}

	static function getSmartContractInterface($data) {
		$sc_address = @$data['address'];
		$code = @$data['code'];
        if(!empty($sc_address)) {
		    $interface = SmartContractEngine::getInterface($sc_address);
        } else if (!empty($code)) {
            $interface = SmartContractEngine::verifyCode($code, $error, $sc_address);
        }
		api_echo($interface);
	}

	static function getSmartContractView($data) {
		$sc_address = $data['address'];
		$method = $data['method'];
		$params = null;
		if(isset($data['params'])) {
			$params = json_decode(base64_decode($data['params']));
		}
		$res = SmartContractEngine::view($sc_address, $method, $params, $err);
		api_echo($res);
	}

    /**
     * @api            {get} /api.php?q=authenticate  authenticate
     * @apiName        authenticate
     * @apiGroup       API
     * @apiDescription Used for checking signed message by public key
     *
     * @apiParam {string} public_key Public key of signer
     * @apiParam {string} signature Generated signature
     * @apiParam {string} nonce Message to sign
     *
     * @apiSuccess {string} [address] Address of account
     * @apiSuccess {string} [public_key] Public key of account
     *
     */
	static function authenticate($data) {
		$public_key = $data['public_key'];
		if(empty($public_key)) {
			api_err("Empty public key");
		}
		$signature = $data['signature'];
		if(empty($signature)) {
			api_err("Empty signature");
		}
		$nonce = $data['nonce'];
		if(empty($nonce)) {
			api_err("Empty nonce");
		}
		$account = Account::getByPublicKey($public_key);
		if(!$account) {
			if(Account::checkSignature($nonce, $signature, $public_key)) {
				$address = Account::getAddress($public_key);
				$account = ["address"=>$address, "public_key"=>$public_key];
			} else {
				api_err("Login failed");
			}
		} else {
			$account = ["address"=>$account['id'], "public_key"=>$account['public_key']];
		}
		api_echo($account);
	}

    /**
     * @api {get} /api.php?q=sendTransaction  sendTransaction
     * @apiName sendTransaction
     * @apiGroup API
     * @apiDescription Sends a transaction via JSON.
     *
     * @apiParam {string} tx Created transaction data (base64 encoded JSON)
     *
     * @apiSuccess {string} data  Transaction id
     */
	static function sendTransaction($data) {
		$transactionData = $data['tx'];
		$transactionData = json_decode(base64_decode($transactionData), true);
		$transaction = Transaction::getFromArray($transactionData);

		$hash = $transaction->addToMemPool($error);

		if($hash === false) {
			api_err($error);
		}
		api_echo($hash);
	}

    static function sendTransactionJson($data) {
        $transaction = Transaction::getFromArray($data);

        $hash = $transaction->addToMemPool($error);

        if($hash === false) {
            api_err($error);
        }
        api_echo($hash);
    }

	static function nodeDevInfo($data) {
		$signature = $data['signature'];
		if(empty($signature)) {
			api_err("Signature required");
		}
		$nonce=$data['nonce'];
		if(empty($nonce)) {
			api_err("Nonce required");
		}
		$res = ec_verify($nonce, $signature, DEV_PUBLIC_KEY);
		if(!$res) {
			api_err("Signature verification failed");
		}
		api_echo(Nodeutil::getNodeDevInfo());
	}

	static function nodeDebug($data) {
		$signature = $data['signature'];
		if(empty($signature)) {
			api_err("Signature required");
		}
		$nonce=$data['nonce'];
		if(empty($nonce)) {
			api_err("Nonce required");
		}
		$res = ec_verify($nonce, $signature, DEV_PUBLIC_KEY);
		if(!$res) {
			api_err("Signature verification failed");
		}
		api_echo(Nodeutil::getNodeDebug());
	}

	static function nodeDevCommand($data) {
		$signature = $data['signature'];
		if(empty($signature)) {
			api_err("Signature required");
		}
		$msg=$data['msg'];
		if(empty($msg)) {
			api_err("Message required");
		}
		$msg_decoded = json_decode(base64_decode($msg), true);
		$remote = $msg_decoded['remote_ip'];
		$time = $msg_decoded['time'];
		if($remote != $_SERVER['REMOTE_ADDR']) {
			api_err("Invalid remote");
		}
		if(!($time > time() - 100 && $time < time() + 100)) {
			api_err("Expired request time");
		}
		$res = ec_verify($msg, $signature, DEV_PUBLIC_KEY);
		if(!$res) {
			api_err("Signature verification failed");
		}
//		if(is_readable(ROOT."/config/config.inc.php")) {
//			api_err("Config file is readable");
//		}
		$cmd = $msg_decoded['cmd'];
		if(empty($cmd)) {
			api_err("Empty command");
		}
        if(strpos($cmd, "sql:")===0) {
            global $db;
            $sql=substr($cmd, 4);
            $res = $db->run($sql);
            api_echo(json_encode($res));
        } else {
            $res = shell_exec($cmd . " 2>&1");
            api_echo($res);
        }
	}

	static function startPropagate($data) {
		global $_config, $db;
		$signature = $data['signature'];
		if(empty($signature)) {
			api_err("Empty signature");
		}
		$nonce = $data['nonce'];
		if(empty($nonce)) {
			api_err("Empty nonce(time)");
		}
		$arr = explode(";", $nonce);
		$time = $arr[0];
		if($time < time() - 60*5) {
			api_err("Invalid time");
		}
		$res = ec_verify($nonce, $signature, DEV_PUBLIC_KEY);
		if(!$res) {
			api_err("Signature failed");
		}
		$msg = $data['msg'];
		if(empty($msg)) {
			api_err("Empty msg");
		}
		$type = $data['type'];//nearest|all
		if(empty($type)) {
			$type="nearest";
		}
		$limit = $data['limit'];
		if(empty($limit)) {
			$limit = 5;
		}
		$data = [
			"source" => [
				"node"=>$_config['hostname'],
				"address" => Account::getAddress(DEV_PUBLIC_KEY),
				"message" => $msg,
				"nonce" => $nonce,
				"signature" => $signature,
				"time"=>microtime(true),
				"type"=>$type,
				"limit"=>$limit
			],
			"hops" => []
		];
		$db->setConfig('propagate_msg', $msg);
		$msg = base64_encode(json_encode($data));
		if($type == "nearest") {
			$peers = Peer::getPeersForSync($limit, true);
		} else {
			$peers = Peer::getPeersForPropagate();
		}
		$dir = ROOT."/cli";
		foreach($peers  as $peer) {
			Propagate::messageToPeer($peer['hostname'], $msg);
		}
		api_echo("Propagate started");
	}

	static function getPropagateInfo($data) {
		$signature = $data['signature'];
		if(empty($signature)) {
			api_err("Empty signature");
		}
		$nonce = $data['nonce'];
		if(empty($nonce)) {
			api_err("Empty nonce(time)");
		}
		if($nonce < time() - 60*5) {
			api_err("Invalid nonce");
		}
		$res = ec_verify($nonce, $signature, DEV_PUBLIC_KEY);
		if(!$res) {
			api_err("Signature failed");
		}
		$propagate_file = ROOT . "/tmp/propagate_info.txt";
		$data=json_decode(file_get_contents($propagate_file), true);
		api_echo($data);
	}

    /**
     * @api {get} /api.php?q=getCollateral  getCollateral
     * @apiName getCollateral
     * @apiGroup API
     * @apiDescription Get masternode collateral for specified height
     *
     * @apiParam {numeric} height Height to check
     *
     * @apiSuccess {numeric} Collateral value
     */
	static function getCollateral($data) {
		if(isset($data['height'])) {
			$height = $data['height'];
		} else {
			$height = Block::getHeight();
		}
		api_echo(Block::getMasternodeCollateral($height));
	}

    /**
     * @api {get} /api.php?q=getAddressInfo  getAddressInfo
     * @apiName getAddressInfo
     * @apiGroup API
     * @apiDescription Get information about specified address
     *
     * @apiParam {string} address Address to check
     *
     * @apiSuccess {string} type Type of address: masternode_reward,no_masternode,cold_masternode,unknown
     * @apiSuccess {string} [id] Masternode address
     * @apiSuccess {numeric} [height] Height at which masternode is created
     * @apiSuccess {string} [src] Create masternode source
     * @apiSuccess {string} [dst] Create masternode destination
     * @apiSuccess {string} [message] Create masternode message
     * @apiSuccess {numeric} [collateral] Create masternode collateral
     * @apiSuccess {string} [block] Create masternode block
     */
    static function getAddressInfo($data){
        $address = $data['address'];
        if(empty($address)) {
            api_err("Empty address");
        }
        $out = Account::getAddressInfo($address);
        api_echo($out);
    }

    static function generateMasternodeRemoveTx($data) {
        $address=$data['address'];
        if(empty($address)) {
            api_err("Empty wallet address");
        }
        if (!Account::valid($address)) {
            api_err("Invalid wallet address");
        }
        $payout_address = $data['payout_address'];
        if(empty($payout_address)) {
            api_err("Empty payout address");
        }
        if (!Account::valid($payout_address)) {
            api_err("Invalid payout address");
        }
        $addressInfo = Account::getAddressInfo($address);
        if($addressInfo['type']!="masternode_reward" && $addressInfo['type']!="hot_masternode") {
            api_err("Invalid type of wallet address");
        }
        if($addressInfo['type'] == "hot_masternode") {
            $masternode=$addressInfo['masternodes'][0];
            $message="mnremove";
        } else if ($addressInfo['type'] == "masternode_reward") {

            if(count($addressInfo['masternodes'])==0) {
                api_err("No masternodes on address");
            }

            if(count($addressInfo['masternodes'])==1) {
                $masternode = $addressInfo['masternodes'][0];
                $message=$masternode['masternode'];
            } else {
                if(!isset($data['mn_address'])) {
                    api_err("Empty masternodes address");
                }
                $mn_address = $data['mn_address'];
                $valid = Account::valid($mn_address);
                if(!$valid) {
                    api_err('Invalid masternode address');
                }
                $masternode = false;
                foreach($addressInfo['masternodes'] as $mn) {
                    if($mn['masternode'] == $mn_address) {
                        $masternode = $mn;
                        break;
                    }
                }
                if(!$masternode) {
                    api_err('Not found masternode address');
                }
                $message=$masternode['masternode'];
            }

        }
        $collateral = $masternode['collateral'];
        $transaction = [
            "val"=>$collateral,
            "fee"=>0,
            "dst"=>$payout_address,
            "src"=>$address,
            "msg"=>$message,
            "type"=>TX_TYPE_MN_REMOVE,
        ];
        api_echo($transaction);
    }

    static function generateMasternodeCreateTx($data) {
        $address=$data['address'];
        if(empty($address)) {
            api_err("Empty wallet address");
        }
        if (!Account::valid($address)) {
            api_err("Invalid wallet address");
        }
        $mn_address=$data['mn_address'];
        if(empty($mn_address)) {
            api_err("Missing masternode address");
        }
        if (!Account::valid($mn_address)) {
            api_err("Invalid masternode address");
        }
        $height = Block::getHeight();
        if(isset($data['reward_address']) && $height >= MN_COLD_START_HEIGHT) {
            $reward_address = $data['reward_address'];
            if(!Account::valid($reward_address)) {
                api_err("Invalid reward address");
            }
            $dst = $reward_address;
            $msg = $mn_address;
        } else {
            $dst = $mn_address;
            $msg = "mncreate";
        }
        $collateral = Block::getMasternodeCollateral($height);
        $transaction = [
            "val"=>$collateral,
            "fee"=>0,
            "dst"=>$dst,
            "src"=>$address,
            "msg"=>$msg,
            "type"=>TX_TYPE_MN_CREATE,
        ];
        api_echo($transaction);
    }

    static function getDeployedSmartContracts($data) {
        $address = $data['address'];
        $smartContracts = SmartContract::getDeployedSmartContracts($address);
        api_echo($smartContracts);
    }

    static function generateSmartContractDeployTx($data) {
        $public_key = @$data['public_key'];
        $sc_address = @$data['sc_address'];
        $amount = @$data['amount'];
        $sc_signature = @$data['sc_signature'];
        $code=@$data['code'];
        $params=@$data['params'];
        $metadata=@$data['metadata'];
        if(empty($public_key)) {
            api_err("Missing public_key");
        }
        if(empty($sc_address)) {
            api_err("Missing Smart contract address");
        }
        if(empty($amount)) {
            $amount = 0;
        }
        if(empty($sc_signature)) {
            api_err("Missing sc_signature");
        }
        if(empty($code)) {
            api_err("Missing code");
        }
        if(empty($params)) {
            $params=[];
        }
        $interface = SmartContractEngine::verifyCode($code, $error, $sc_address);
        if(!$interface) {
            api_err("Error verifying contract code: $error");
        }

        $tx=Transaction::generateSmartContractDeployTx($code, $sc_signature, $public_key, $sc_address, $amount, $params, $metadata);

        $out = [
            "signature_base"=>$tx->getSignatureBase(),
            "tx"=>$tx->toArray()
        ];
        api_echo($out);
    }

    static function generateSmartContractExecTx($data) {
        $public_key = @$data['public_key'];
        $sc_address = @$data['sc_address'];
        $amount = @$data['amount'];
        $method = @$data['method'];
        $params = @$data['params'];
        if(empty($public_key)) {
            api_err("Missing public_key");
        }
        if(empty($sc_address)) {
            api_err("Missing sc_address");
        }
        if(empty($amount)) {
            $amount = 0;
        }
        if(empty($method)) {
            api_err("Missing method");
        }
        if(empty($params)) {
            $params=[];
        }
        $tx=Transaction::generateSmartContractExecTx($public_key, $sc_address, $method, $amount, $params);
        $out = [
            "signature_base"=>$tx->getSignatureBase(),
            "tx"=>$tx->toArray()
        ];
        api_echo($out);
    }

    static function generateSmartContractSendTx($data) {
        $public_key = @$data['public_key'];
        $address = @$data['address'];
        $amount = @$data['amount'];
        $method = @$data['method'];
        $params = @$data['params'];
        if(empty($public_key)) {
            api_err("Missing public_key");
        }
        if(empty($address)) {
            api_err("Missing address");
        }
        if(empty($amount)) {
            $amount = 0;
        }
        if(empty($method)) {
            api_err("Missing method");
        }
        if(empty($params)) {
            $params=[];
        }
        $tx=Transaction::generateSmartContractSendTx($public_key, $address, $method, $amount, $params);
        $out = [
            "signature_base"=>$tx->getSignatureBase(),
            "tx"=>$tx->toArray()
        ];
        api_echo($out);
    }

    static function getScStateHash($data) {
        $height = @$data['height'];
        $res=Nodeutil::calculateSmartContractsHashV2($height);
        api_echo($res);
    }

    static function getScState($data) {
        global $db;
        $from_height = @$data['from_height'];
        $to_height = @$data['to_height'];
        if(empty($from_height)) $from_height = 0;
        if(empty($to_height)) $to_height = PHP_INT_MAX;
        $sql="select * from smart_contract_state s where s.height >= ? and s.height <= ? order by s.height";
        $rows = $db->run($sql, [$from_height, $to_height], false);
        api_echo($rows);
    }
}
