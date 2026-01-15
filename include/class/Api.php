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
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {string} data Contains the address
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
	 *
	 * @apiError {object} Error response wrapper object
	 * @apiError {string} status Status: "error" for errors
	 * @apiError {string} data Error message
	 * @apiError {string} coin Coin name
	 * @apiError {string} version Node version
	 * @apiError {string} network Network name
	 * @apiError {string} chain_id Chain ID
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
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {string} data Output string
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
	 *
	 * @apiError {object} Error response wrapper object
	 * @apiError {string} status Status: "error" for errors
	 * @apiError {string} data Error message
	 * @apiError {string} coin Coin name
	 * @apiError {string} version Node version
	 * @apiError {string} network Network name
	 * @apiError {string} chain_id Chain ID
	 */
	static function base58($data) {
		api_echo(base58_encode($data['data']));
	}

	/**
	 * @api {get} /api.php?q=getBalance  getBalance
	 * @apiName getBalance
	 * @apiGroup API
	 * @apiDescription Returns the balance of a specific address or public key. At least one of public_key or address must be provided.
	 *
	 * @apiParam {string} [public_key] Public key
	 * @apiParam {string} [address] Address
	 *
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {string} data The PHP balance
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
	 *
	 * @apiError {object} Error response wrapper object
	 * @apiError {string} status Status: "error" for errors
	 * @apiError {string} data Error message
	 * @apiError {string} coin Coin name
	 * @apiError {string} version Node version
	 * @apiError {string} network Network name
	 * @apiError {string} chain_id Chain ID
	 */
	static function getBalance($data) {
		$public_key = @$data['public_key'];
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
     * @api {get} /api.php?q=getBalances  getBalances
     * @apiName getBalances
     * @apiGroup API
     * @apiDescription Returns the balances of multiple addresses
     *
     * @apiParam {string} addresses List of addresses (json encoded)
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {object} data The balances for each address
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     *
     * @apiError {object} Error response wrapper object
     * @apiError {string} status Status: "error" for errors
     * @apiError {string} data Error message
     * @apiError {string} coin Coin name
     * @apiError {string} version Node version
     * @apiError {string} network Network name
     * @apiError {string} chain_id Chain ID
     */
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
	 * @apiDescription Returns the pending balance, which includes pending transactions, of a specific address or public key. At least one of public_key or address must be provided.
	 *
	 * @apiParam {string} [public_key] Public key
	 * @apiParam {string} [address] Address
	 *
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {string} data The PHP balance
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
	 *
	 * @apiError {object} Error response wrapper object
	 * @apiError {string} status Status: "error" for errors
	 * @apiError {string} data Error message
	 * @apiError {string} coin Coin name
	 * @apiError {string} version Node version
	 * @apiError {string} network Network name
	 * @apiError {string} chain_id Chain ID
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
	 * @apiDescription Returns only balance in mempool of a specific address or public key. At least one of public_key or address must be provided.
	 *
	 * @apiParam {string} [public_key] Public key
	 * @apiParam {string} [address] Address
	 *
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {string} data The PHP balance in mempool
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
	 *
	 * @apiError {object} Error response wrapper object
	 * @apiError {string} status Status: "error" for errors
	 * @apiError {string} data Error message
	 * @apiError {string} coin Coin name
	 * @apiError {string} version Node version
	 * @apiError {string} network Network name
	 * @apiError {string} chain_id Chain ID
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
	 * @apiDescription Returns the latest transactions of an address. At least one of public_key or address must be provided.
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
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {array} data Array of transaction objects
	 * @apiSuccess {string} data[].block  Block ID
	 * @apiSuccess {numeric} data[].confirmation Number of confirmations
	 * @apiSuccess {numeric} data[].date  Transaction's date in UNIX TIMESTAMP format
	 * @apiSuccess {string} data[].dst  Transaction destination
	 * @apiSuccess {numeric} data[].fee  The transaction's fee
	 * @apiSuccess {numeric} data[].height  Block height
	 * @apiSuccess {string} data[].id  Transaction ID/HASH
	 * @apiSuccess {string} data[].message  Transaction's message
	 * @apiSuccess {string} data[].public_key  Account's public_key
	 * @apiSuccess {string} data[].sign Sign of transaction related to address
	 * @apiSuccess {string} data[].signature  Transaction's signature
	 * @apiSuccess {string} data[].src  Sender's address
	 * @apiSuccess {numeric} data[].type Transaction type
	 * @apiSuccess {string} data[].type_label Transaction label
	 * @apiSuccess {numeric} data[].val Transaction value
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
	 *
	 * @apiError {object} Error response wrapper object
	 * @apiError {string} status Status: "error" for errors
	 * @apiError {string} data Error message
	 * @apiError {string} coin Coin name
	 * @apiError {string} version Node version
	 * @apiError {string} network Network name
	 * @apiError {string} chain_id Chain ID
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
		$limit = intval($data['limit']??100);
		$offset = intval($data['offset']??0);
		if(empty($offset)) {
			$offset = 0;
		}
		$transactions = Transaction::getByAddress($address, $limit, $offset, @$data['filter']);
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
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {object} data Transaction object
	 * @apiSuccess {string} data.block  Block ID
	 * @apiSuccess {numeric} data.height  Block height
	 * @apiSuccess {string} data.id  Transaction ID/HASH
	 * @apiSuccess {string} data.dst  Transaction destination
	 * @apiSuccess {numeric} data.val Transaction value
	 * @apiSuccess {numeric} data.fee  The transaction's fee
	 * @apiSuccess {string} data.signature  Transaction's signature
	 * @apiSuccess {string} data.message  Transaction's message
	 * @apiSuccess {string} data.type  Transaction type
	 * @apiSuccess {numeric} data.date  Transaction's date in UNIX TIMESTAMP format
	 * @apiSuccess {string} data.public_key  Account's public_key
	 * @apiSuccess {numeric} data.confirmation Number of confirmations
	 * @apiSuccess {string} data.type_label  Transaction type label
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
	 *
	 * @apiError {object} Error response wrapper object
	 * @apiError {string} status Status: "error" for errors
	 * @apiError {string} data Error message
	 * @apiError {string} coin Coin name
	 * @apiError {string} version Node version
	 * @apiError {string} network Network name
	 * @apiError {string} chain_id Chain ID
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
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {string} data The public key
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
	 *
	 * @apiError {object} Error response wrapper object
	 * @apiError {string} status Status: "error" for errors
	 * @apiError {string} data Error message
	 * @apiError {string} coin Coin name
	 * @apiError {string} version Node version
	 * @apiError {string} network Network name
	 * @apiError {string} chain_id Chain ID
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
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {object} data Account object
	 * @apiSuccess {string} data.address Account address
	 * @apiSuccess {string} data.public_key Public key
	 * @apiSuccess {string} data.private_key Private key
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
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
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {object} data Block object
	 * @apiSuccess {string} data.id Block id
	 * @apiSuccess {string} data.generator Block Generator
	 * @apiSuccess {numeric} data.height Height
	 * @apiSuccess {numeric} data.date Block's date in UNIX TIMESTAMP format
	 * @apiSuccess {string} data.nonce Mining nonce
	 * @apiSuccess {string} data.signature Signature signed by the generator
	 * @apiSuccess {numeric} data.difficulty The base target / difficulty
	 * @apiSuccess {numeric} data.transactions Number of transactions in block
	 * @apiSuccess {string} data.version Block version
	 * @apiSuccess {string} data.argon Mining argon hash
	 * @apiSuccess {string} data.miner Miner who found block
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
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
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {object} data Block object
	 * @apiSuccess {string} data.id Block id
	 * @apiSuccess {string} data.generator Block Generator
	 * @apiSuccess {numeric} data.height Height
	 * @apiSuccess {numeric} data.date Block's date in UNIX TIMESTAMP format
	 * @apiSuccess {string} data.nonce Mining nonce
	 * @apiSuccess {string} data.signature Signature signed by the generator
	 * @apiSuccess {numeric} data.difficulty The base target / difficulty
	 * @apiSuccess {numeric} data.transactions Number of transactions in block
	 * @apiSuccess {string} data.version Block version
	 * @apiSuccess {string} data.argon Mining argon hash
	 * @apiSuccess {string} data.miner Miner who found block
	 * @apiSuccess {array} data.data List of transactions in block
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
	 *
	 * @apiError {object} Error response wrapper object
	 * @apiError {string} status Status: "error" for errors
	 * @apiError {string} data Error message
	 * @apiError {string} coin Coin name
	 * @apiError {string} version Node version
	 * @apiError {string} network Network name
	 * @apiError {string} chain_id Chain ID
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
	 * @apiDescription Returns the transactions of a specific block. At least one of height or block must be provided.
	 *
	 * @apiParam {numeric} [height] Block Height
	 * @apiParam {string} [block] Block id
	 *
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {array} data Array of transaction objects
	 * @apiSuccess {string} data[].block  Block ID
	 * @apiSuccess {numeric} data[].confirmations Number of confirmations
	 * @apiSuccess {numeric} data[].date  Transaction's date in UNIX TIMESTAMP format
	 * @apiSuccess {string} data[].dst  Transaction destination
	 * @apiSuccess {numeric} data[].fee  The transaction's fee
	 * @apiSuccess {numeric} data[].height  Block height
	 * @apiSuccess {string} data[].id  Transaction ID/HASH
	 * @apiSuccess {string} data[].message  Transaction's message
	 * @apiSuccess {string} data[].public_key  Account's public_key
	 * @apiSuccess {string} data[].signature  Transaction's signature
	 * @apiSuccess {string} data[].src  Sender's address
	 * @apiSuccess {numeric} data[].type Transaction type
	 * @apiSuccess {string} data[].type_label Transaction type label
	 * @apiSuccess {numeric} data[].val Transaction value
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
	 *
	 * @apiError {object} Error response wrapper object
	 * @apiError {string} status Status: "error" for errors
	 * @apiError {string} data Error message
	 * @apiError {string} coin Coin name
	 * @apiError {string} version Node version
	 * @apiError {string} network Network name
	 * @apiError {string} chain_id Chain ID
	 */
	static function getBlockTransactions($data) {
		$height = san($data['height']);
		$block = san(@$data['block']);

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
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {string} data  Version
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
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
	 * @apiParam {string} fee Transaction fee. Must be 0
	 * @apiParam {string} [signature] Transaction signature. It's recommended that the transaction is signed before being sent to the node to avoid sending your private key to the node.
	 * @apiParam {numeric} [date] Transaction's date in UNIX TIMESTAMP format. Required when the transaction is pre-signed.
	 * @apiParam {string} [message] A message to be included with the transaction. Maximum 128 chars.
	 * @apiParam {numeric} [type] The type of the transaction. 1 to send coins.
	 *
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {string} data  Transaction id
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
	 *
	 * @apiError {object} Error response wrapper object
	 * @apiError {string} status Status: "error" for errors
	 * @apiError {string} data Error message
	 * @apiError {string} coin Coin name
	 * @apiError {string} version Node version
	 * @apiError {string} network Network name
	 * @apiError {string} chain_id Chain ID
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
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {numeric} data  Number of mempool transactions
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
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
	 * @apiParam {string} public_key Public key
	 * @apiParam {string} signature Signature
	 * @apiParam {string} data Signed data
	 *
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {boolean} data true or false
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
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
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {object}  data A collection of data about the sync process.
	 * @apiSuccess {boolean} data.sync_running Whether the sync process is currently running.
	 * @apiSuccess {number}  data.last_sync The timestamp for the last time the sync process was run.
	 * @apiSuccess {boolean} data.sync Whether the sync process is currently synchronising.
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
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
	 * @apiParam {boolean} [basic] Return basic info only
	 * @apiParam {boolean} [nocache] Skip cache
	 *
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
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
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
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
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {boolean} data True if the address is valid, false otherwise.
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
	 *
	 * @apiError {object} Error response wrapper object
	 * @apiError {string} status Status: "error" for errors
	 * @apiError {boolean} data false (when address is invalid)
	 * @apiError {string} coin Coin name
	 * @apiError {string} version Node version
	 * @apiError {string} network Network name
	 * @apiError {string} chain_id Chain ID
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
	 * @apiSuccess {object} Response wrapper object
	 * @apiSuccess {string} status Status: "ok" for success
	 * @apiSuccess {array} data Array of peer objects
	 * @apiSuccess {numeric} data[].id Id of peer in internal database (not relevant)
	 * @apiSuccess {string} data[].hostname Peer hostname
	 * @apiSuccess {numeric} data[].blacklisted UNIX timestamp until peer is blacklisted
	 * @apiSuccess {numeric} data[].ping UNIX timestamp when peer was last pinged
	 * @apiSuccess {numeric} data[].fails Number of failed connections to peer
	 * @apiSuccess {numeric} data[].stuckfail Number of failed stuck connections to peer
	 * @apiSuccess {numeric} data[].height Blockchain height of peer
	 * @apiSuccess {numeric} data[].score Peer node score
	 * @apiSuccess {string} data[].blacklist_reason Reason why peer is blacklisted
	 * @apiSuccess {string} data[].version Version of peer node
	 * @apiSuccess {string} coin Coin name
	 * @apiSuccess {string} version Node version
	 * @apiSuccess {string} network Network name
	 * @apiSuccess {string} chain_id Chain ID
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
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {array} data Array of masternode objects
     * @apiSuccess {string} data[].public_key Public key of masternode
     * @apiSuccess {numeric} data[].height Height at which masternode is created
     * @apiSuccess {string} data[].ip IP address of masternode
	 * @apiSuccess {numeric} data[].win_height Last height when masternode received reward
     * @apiSuccess {string} data[].signature current masternode signature
     * @apiSuccess {string} data[].id Address of masternode
     * @apiSuccess {numeric} data[].collateral Locked collateral in masternode
     * @apiSuccess {numeric} data[].verified 1 if masternode is verified for current height
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
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
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {array} data Array of masternode objects
     * @apiSuccess {numeric} data[].collateral Locked collateral of masternode
     * @apiSuccess {string} data[].reward_address Address which receives masternode rewards (if cold masternode)
     * @apiSuccess {string} data[].masternode_address Address of masternode
     * @apiSuccess {numeric} data[].masternode_balance Balance of masternode (hot or cold)
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     *
     * @apiError {object} Error response wrapper object
     * @apiError {string} status Status: "error" for errors
     * @apiError {string} data Error message
     * @apiError {string} coin Coin name
     * @apiError {string} version Node version
     * @apiError {string} network Network name
     * @apiError {string} chain_id Chain ID
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
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {object} data Masternode object
     * @apiSuccess {string} data.public_key Public key of masternode
     * @apiSuccess {numeric} data.height Height at which masternode is created
     * @apiSuccess {string} data.ip IP address of masternode
	 * @apiSuccess {numeric} data.win_height Last height when masternode received reward
     * @apiSuccess {string} data.signature current masternode signature
     * @apiSuccess {string} data.id Address of masternode
     * @apiSuccess {numeric} data.collateral Locked collateral in masternode
     * @apiSuccess {numeric} data.verified 1 if masternode is verified for current height
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     *
     * @apiError {object} Error response wrapper object
     * @apiError {string} status Status: "error" for errors
     * @apiError {string} data Error message
     * @apiError {string} coin Coin name
     * @apiError {string} version Node version
     * @apiError {string} network Network name
     * @apiError {string} chain_id Chain ID
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
     * @apiParam {numeric} [height] Height for which to retrieve fee. If empty, last fee is returned
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {string} data Current transaction fee
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     */
	static function getFee($data) {
		$fee = Blockchain::getFee($data['height']);
		api_echo(number_format($fee, 5));
	}

    /**
     * @api            {get} /api.php?q=getSmartContractCreateFee  getSmartContractCreateFee
     * @apiName        getSmartContractCreateFee
     * @apiGroup       API
     * @apiDescription Get smart contract create fee
     *
     * @apiParam {number} [height] Height for which to retrieve fee. If empty last fee is returned
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {numeric} data Smart contract creation fee
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     */
    static function getSmartContractCreateFee($data) {
        $fee = Blockchain::getSmartContractCreateFee($data['height']);
        api_echo($fee);
    }

    /**
     * @api            {get} /api.php?q=getSmartContractExecFee  getSmartContractExecFee
     * @apiName        getSmartContractExecFee
     * @apiGroup       API
     * @apiDescription Get smart contract execution fee
     *
     * @apiParam {number} [height] Height for which to retrieve fee. If empty last fee is returned
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {numeric} data Smart contract execution fee
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     */
    static function getSmartContractExecFee($data) {
        $fee = Blockchain::getSmartContractExecFee($data['height']);
        api_echo($fee);
    }

    /**
     * @api            {get} /api.php?q=getSmartContract  getSmartContract
     * @apiName        getSmartContract
     * @apiGroup       SC
     * @apiDescription Get Smart Contract by its address
     *
     * @apiParam {string} address Address of Smart Contract
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {object} data Smart Contract object
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     */
	static function getSmartContract($data) {
		$sc_address = $data['address'];
		$smartContract = SmartContract::getById($sc_address);
		api_echo($smartContract);
	}

    /**
     * @api            {get} /api.php?q=getSmartContractProperty  getSmartContractProperty
     * @apiName        getSmartContractProperty
     * @apiGroup       SC
     * @apiDescription Read Smart Contract property
     *
     * @apiParam {string} address Address of Smart Contract
     * @apiParam {string} property Name of the property to read
     * @apiParam {string} [key] Key of property (if is map)
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {string} data Value of property
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     *
     * @apiError {object} Error response wrapper object
     * @apiError {string} status Status: "error" for errors
     * @apiError {string} data Error message
     * @apiError {string} coin Coin name
     * @apiError {string} version Node version
     * @apiError {string} network Network name
     * @apiError {string} chain_id Chain ID
     */
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

    /**
     * @api            {get} /api.php?q=getSmartContractInterface  getSmartContractInterface
     * @apiName        getSmartContractInterface
     * @apiGroup       SC
     * @apiDescription Get Smart Contract Interface definition. Requires either address (for deployed contract) or code (for compiled contract).
     *
     * @apiParam {string} [address] Address of Smart Contract
     * @apiParam {string} [code] Compiled code of Smart Contract
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {object} data Interface of Smart Contract
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     */
	static function getSmartContractInterface($data) {
		$sc_address = @$data['address'];
		$code = @$data['code'];
        if(!empty($code)) {
            $interface = SmartContractEngine::verifyCode($code, $error, $sc_address);
        } else if (!empty($sc_address)) {
            $interface = SmartContractEngine::getInterface($sc_address);
        }
		api_echo($interface);
	}

    /**
     * @api            {get} /api.php?q=getSmartContractView  getSmartContractView
     * @apiName        getSmartContractView
     * @apiGroup       SC
     * @apiDescription Executes Smart Contract view method
     *
     * @apiParam {string} address Address of Smart Contract
     * @apiParam {string} method View method name in Smart Contract
     * @apiParam {string} [params] Parameters for method (json string base64 encoded)
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {string} data Response from execution of method
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     */
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
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {object} data Account object
     * @apiSuccess {string} data.address Address of account
     * @apiSuccess {string} data.public_key Public key of account
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     *
     * @apiError {object} Error response wrapper object
     * @apiError {string} status Status: "error" for errors
     * @apiError {string} data Error message
     * @apiError {string} coin Coin name
     * @apiError {string} version Node version
     * @apiError {string} network Network name
     * @apiError {string} chain_id Chain ID
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
     * @api {post} /api.php?q=sendTransaction  sendTransaction
     * @apiName sendTransaction
     * @apiGroup API
     * @apiDescription Sends a transaction as JSON via POST.
     *
     * @apiParam {string} tx Created transaction data (base64 encoded JSON)
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {string} data  Transaction id
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     *
     * @apiError {object} Error response wrapper object
     * @apiError {string} status Status: "error" for errors
     * @apiError {string} data Error message
     * @apiError {string} coin Coin name
     * @apiError {string} version Node version
     * @apiError {string} network Network name
     * @apiError {string} chain_id Chain ID
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

    /**
     * @api {post} /api.php?q=sendTransactionJson  sendTransactionJson
     * @apiName sendTransactionJson
     * @apiGroup API
     * @apiDescription Sends a transaction via JSON via application/json. Transaction data should be sent in the request body as JSON.
     *
     * @apiBody {string} public_key Sender's public key
     * @apiBody {string} dst Destination address
     * @apiBody {numeric} val Transaction value
     * @apiBody {numeric} fee Transaction fee
     * @apiBody {string} signature Transaction signature
     * @apiBody {numeric} date Transaction date (UNIX timestamp)
     * @apiBody {string} [message] Transaction message
     * @apiBody {numeric} [type] Transaction type
     * @apiBody {object} [data] Additional transaction data
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {string} data  Transaction id
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     *
     * @apiError {object} Error response wrapper object
     * @apiError {string} status Status: "error" for errors
     * @apiError {string} data Error message
     * @apiError {string} coin Coin name
     * @apiError {string} version Node version
     * @apiError {string} network Network name
     * @apiError {string} chain_id Chain ID
     */
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
     * @apiParam {numeric} [height] Height to check. If empty, current height is used
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {numeric} data Collateral value
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
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
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {object} data Address information object
     * @apiSuccess {string} data.type Type of address: masternode_reward,no_masternode,cold_masternode,unknown
     * @apiSuccess {string} data.id Masternode address
     * @apiSuccess {numeric} data.height Height at which masternode is created
     * @apiSuccess {string} data.src Create masternode source
     * @apiSuccess {string} data.dst Create masternode destination
     * @apiSuccess {string} data.message Create masternode message
     * @apiSuccess {numeric} data.collateral Create masternode collateral
     * @apiSuccess {string} data.block Create masternode block
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     *
     * @apiError {object} Error response wrapper object
     * @apiError {string} status Status: "error" for errors
     * @apiError {string} data Error message
     * @apiError {string} coin Coin name
     * @apiError {string} version Node version
     * @apiError {string} network Network name
     * @apiError {string} chain_id Chain ID
     */
    static function getAddressInfo($data){
        $address = $data['address'];
        if(empty($address)) {
            api_err("Empty address");
        }
        $out = Account::getAddressInfo($address);
        api_echo($out);
    }

    /**
     * @api {get} /api.php?q=generateMasternodeRemoveTx  generateMasternodeRemoveTx
     * @apiName generateMasternodeRemoveTx
     * @apiGroup Masternodes
     * @apiDescription Create data for removing masternode transaction
     *
     * @apiParam {string} address Wallet address from which is masternode removed (hot or reward address)
     * @apiParam {string} payout_address Destination address where locked collateral will be transferred
     * @apiParam {string} [mn_address] Masternode address (required if reward address has multiple masternodes)
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {object} data Transaction data
     * @apiSuccess {numeric} data.val Transaction value
     * @apiSuccess {numeric} data.fee Transaction fee
     * @apiSuccess {string} data.dst Transaction destination address
     * @apiSuccess {string} data.src Transaction source address
     * @apiSuccess {string} data.msg Transaction message
     * @apiSuccess {numeric} data.type Transaction type
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     *
     * @apiError {object} Error response wrapper object
     * @apiError {string} status Status: "error" for errors
     * @apiError {string} data Error message
     * @apiError {string} coin Coin name
     * @apiError {string} version Node version
     * @apiError {string} network Network name
     * @apiError {string} chain_id Chain ID
     */
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

    /**
     * @api {get} /api.php?q=generateMasternodeCreateTx  generateMasternodeCreateTx
     * @apiName generateMasternodeCreateTx
     * @apiGroup Masternodes
     * @apiDescription Create data for create masternode transaction
     *
     * @apiParam {string} address Wallet address from which is masternode created
     * @apiParam {string} mn_address Address of masternode
     * @apiParam {string} [reward_address] Address of reward in case of cold masternode
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {object} data Transaction data
     * @apiSuccess {numeric} data.val Transaction value
     * @apiSuccess {numeric} data.fee Transaction fee
     * @apiSuccess {string} data.dst Transaction destination address
     * @apiSuccess {string} data.src Transaction source address
     * @apiSuccess {string} data.msg Transaction message
     * @apiSuccess {numeric} data.type Transaction type
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     *
     * @apiError {object} Error response wrapper object
     * @apiError {string} status Status: "error" for errors
     * @apiError {string} data Error message
     * @apiError {string} coin Coin name
     * @apiError {string} version Node version
     * @apiError {string} network Network name
     * @apiError {string} chain_id Chain ID
     */
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

    /**
     * @api            {get} /api.php?q=getDeployedSmartContracts  getDeployedSmartContracts
     * @apiName        getDeployedSmartContracts
     * @apiGroup       SC
     * @apiDescription Get deployed smart contract for specified address
     *
     * @apiParam {string} address Address to check
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {array} data List of Smart Contracts
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     *
     * @apiError {object} Error response wrapper object
     * @apiError {string} status Status: "error" for errors
     * @apiError {string} data Error message
     * @apiError {string} coin Coin name
     * @apiError {string} version Node version
     * @apiError {string} network Network name
     * @apiError {string} chain_id Chain ID
     */
    static function getDeployedSmartContracts($data) {
        $address = $data['address'];
        $smartContracts = SmartContract::getDeployedSmartContracts($address);
        api_echo($smartContracts);
    }

    /**
     * @api            {post} /api.php?q=generateSmartContractDeployTx  generateSmartContractDeployTx
     * @apiName        generateSmartContractDeployTx
     * @apiGroup       SC
     * @apiDescription Get data for deploy Smart Contract transaction
     *
     * @apiBody {string} public_key Public key of source wallet
     * @apiBody {string} sc_address Address of Smart Contract
     * @apiBody {numeric} [amount] Deploy amount
     * @apiBody {string} sc_signature Signature of compiled contract
     * @apiBody {string} code Compiled Smart Contract code
     * @apiBody {array} [params] List of deploy parameters
     * @apiBody {array} [metadata] Additional metadata parameters
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {object} data Transaction data
     * @apiSuccess {string} data.signature_base Transaction signature base
     * @apiSuccess {object} data.tx Transaction data
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     *
     * @apiError {object} Error response wrapper object
     * @apiError {string} status Status: "error" for errors
     * @apiError {string} data Error message
     * @apiError {string} coin Coin name
     * @apiError {string} version Node version
     * @apiError {string} network Network name
     * @apiError {string} chain_id Chain ID
     */
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

    /**
     * @api            {post} /api.php?q=generateSmartContractExecTx  generateSmartContractExecTx
     * @apiName        generateSmartContractExecTx
     * @apiGroup       SC
     * @apiDescription Get data for execute Smart Contract transaction
     *
     * @apiBody {string} public_key Public key of source wallet
     * @apiBody {string} sc_address Address of Smart Contract
     * @apiBody {numeric} [amount] Execution amount
     * @apiBody {string} method Name of method to call
     * @apiBody {array} [params] List of execution parameters
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {object} data Transaction data
     * @apiSuccess {string} data.signature_base Transaction signature base
     * @apiSuccess {object} data.tx Transaction data
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     *
     * @apiError {object} Error response wrapper object
     * @apiError {string} status Status: "error" for errors
     * @apiError {string} data Error message
     * @apiError {string} coin Coin name
     * @apiError {string} version Node version
     * @apiError {string} network Network name
     * @apiError {string} chain_id Chain ID
     */
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

    /**
     * @api            {post} /api.php?q=generateSmartContractSendTx  generateSmartContractSendTx
     * @apiName        generateSmartContractSendTx
     * @apiGroup       SC
     * @apiDescription Get data for send Smart Contract transaction
     *
     * @apiBody {string} public_key Public key of source wallet
     * @apiBody {string} address Address of Smart Contract
     * @apiBody {string} method Name of method to call
     * @apiBody {numeric} [amount] Send amount
     * @apiBody {array} [params] List of method parameters
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {object} data Transaction data
     * @apiSuccess {string} data.signature_base Transaction signature base
     * @apiSuccess {object} data.tx Transaction data
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     *
     * @apiError {object} Error response wrapper object
     * @apiError {string} status Status: "error" for errors
     * @apiError {string} data Error message
     * @apiError {string} coin Coin name
     * @apiError {string} version Node version
     * @apiError {string} network Network name
     * @apiError {string} chain_id Chain ID
     */
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

    /**
     * @api            {get} /api.php?q=getScStateHash  getScStateHash
     * @apiName        getScStateHash
     * @apiGroup       SC
     * @apiDescription Calculates hash of Smart Contract state
     *
     * @apiParam {numeric} [height] Height at which is calculated state
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {object} data State hash information
     * @apiSuccess {numeric} data.height Height at which is calculated state
     * @apiSuccess {numeric} data.count Number of states
     * @apiSuccess {string} data.hash Calculated hash
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     */
    static function getScStateHash($data) {
        $height = @$data['height'];
        $res=Nodeutil::calculateSmartContractsHashV2($height);
        api_echo($res);
    }

    /**
     * @api            {get} /api.php?q=getScState  getScState
     * @apiName        getScState
     * @apiGroup       SC
     * @apiDescription Retrieves node Smart Contract state between specified heights
     *
     * @apiParam {numeric} [from_height=0] Start height
     * @apiParam {numeric} [to_height=PHP_INT_MAX] End height
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {array} data List of states
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     */
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

    /**
     * @api            {get} /api.php?q=getTokenBalance  getTokenBalance
     * @apiName        getTokenBalance
     * @apiGroup       SC
     * @apiDescription Retrieves balance of specified token (ERC-20 Smart Contract)
     *
     * @apiParam {string} token Address of token
     * @apiParam {string} address Address to check
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {string} data Token balance (formatted)
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     */
    static function getTokenBalance($data) {
        global $db;
        $token=$data['token'];
        $address=$data['address'];
        $sql="select FORMAT(ss.var_value / POW(10, json_extract(sc.metadata, '$.decimals')), json_extract(sc.metadata, '$.decimals')) as balance
            from smart_contract_state ss
                     left join smart_contracts sc on (sc.address = ss.sc_address)
                     where ss.sc_address = ?
            and ss.variable = 'balances' and ss.var_key = ?
            order by ss.height desc limit 1";
        $balance=$db->single($sql, [$token,$address], false);
        api_echo($balance);
    }

    /**
     * @api            {get} /api.php?q=findTransactions  findTransactions
     * @apiName        findTransactions
     * @apiGroup       API
     * @apiDescription Search for transactions using various filters. Searches in transactions table by default, or mempool if mempool parameter is set.
     *
     * @apiParam {boolean} [mempool] If true, search in mempool instead of transactions table
     * @apiParam {string} [src] Filter by source address
     * @apiParam {string} [dst] Filter by destination address
     * @apiParam {numeric} [type] Filter by transaction type
     * @apiParam {string} [message] Filter by transaction message
     * @apiParam {string} [id] Filter by transaction ID
     * @apiParam {string} [block] Filter by block ID
     * @apiParam {numeric} [height] Filter by block height
     * @apiParam {numeric} [fromHeight] Filter by minimum block height
     * @apiParam {numeric} [limit=100] Maximum number of results (max 100)
     * @apiParam {numeric} [offset=0] Offset for pagination
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {array} data Array of transaction objects
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     */
    static function findTransactions($data) {
        $table = (!empty($data['mempool'])) ? "mempool" : "transactions";
        $sql="select * from $table t where 1=1 ";
        $params = [];
        if(isset($data['src'])) {
            $sql.=" and t.src = ?";
            $params[]=$data['src'];
        }
        if(isset($data['dst'])) {
            $sql.=" and t.dst = ?";
            $params[]=$data['dst'];
        }
        if(isset($data['type'])) {
            $sql.=" and t.type = ?";
            $params[]=$data['type'];
        }
        if(isset($data['message'])) {
            $sql.=" and t.message = ?";
            $params[]=$data['message'];
        }
        if(isset($data['id'])) {
            $sql.=" and t.id = ?";
            $params[]=$data['id'];
        }
        if(isset($data['block'])) {
            $sql.=" and t.block = ?";
            $params[]=$data['block'];
        }
        if(isset($data['height'])) {
            $sql.=" and t.height = ?";
            $params[]=$data['height'];
        }
        if(isset($data['fromHeight'])) {
            $sql.=" and t.height >= ?";
            $params[]=$data['fromHeight'];
        }
        if(isset($data['limit'])) {
            $limit=$data['limit'];
        }
        if(empty($limit) || $limit > 100) {
            $limit=100;
        }
        $offset = 0;
        if(isset($data['offset'])) {
            $offset=$data['offset'];
        }
        $params[]=$offset;
        $params[]=$limit;
        $sql.=" order by t.height desc limit ?, ?";
        global $db;
        $rows = $db->run($sql, $params, false);
        api_echo($rows);
    }

    /**
     * @api            {get} /api.php?q=generateSendTransaction  generateSendTransaction
     * @apiName        generateSendTransaction
     * @apiGroup       API
     * @apiDescription Generates ready to use data for signing transaction
     *
     * User just need to sign signature_base with its private key and send transaction with sendTransaction
     *
     * Note that signature_base must be prepended with CHAIN_ID for target network before signing
     *
     * @apiParam {string} public_key Public key of sender
     * @apiParam {string} address Address of sender
     * @apiParam {numeric} amount Amount to send
     * @apiParam {string} [message] Message for transaction
     * @apiParam {numeric} [fee] Transaction fee
     *
     * @apiSuccess {object} Response wrapper object
     * @apiSuccess {string} status Status: "ok" for success
     * @apiSuccess {object} data Transaction data as JSON
     * @apiSuccess {string} data.signature_base Generated signature base
     * @apiSuccess {object} data.tx Transaction as JSON object
     * @apiSuccess {string} coin Coin name
     * @apiSuccess {string} version Node version
     * @apiSuccess {string} network Network name
     * @apiSuccess {string} chain_id Chain ID
     *
     * @apiError {object} Error response wrapper object
     * @apiError {string} status Status: "error" for errors
     * @apiError {string} data Error message
     * @apiError {string} coin Coin name
     * @apiError {string} version Node version
     * @apiError {string} network Network name
     * @apiError {string} chain_id Chain ID
     */
    static function generateSendTransaction($data) {
        $publicKey = @$data['public_key'];
        $address = @$data['address'];
        $amount = @$data['amount'];
        $msg = @$data['message'];
        $fee = @$data['fee'];
        if(empty($publicKey)) {
            api_err("Missing public_key");
        }
        if(empty($address)) {
            api_err("Missing address");
        }
        if(empty($amount)) {
            api_err("Missing amount");
        }
        if(empty($fee)) $fee = 0;
        $tx = new Transaction($publicKey,$address,$amount,TX_TYPE_SEND,time(),$msg,$fee);
        $out = [
            "signature_base"=>$tx->getSignatureBase(),
            "tx"=>$tx->toArray()
        ];
        api_echo($out);
    }


}
