<?php
/*
The MIT License (MIT)
Copyright (c) 2018 AroDev
Copyright (c) 2021 PHPCoin

phpcoin.net

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE
OR OTHER DEALINGS IN THE SOFTWARE.
*/
header('Content-Type: application/json');


/**
 * @api {get} /api.php 01. Basic Information
 * @apiName Info
 * @apiGroup API
 * @apiDescription Each API call will return the result in JSON format.
 * There are 2 objects, "status" and "data".
 *
 * The "status" object returns "ok" when the transaction is successful and "error" on failure.
 *
 * The "data" object returns the requested data, as sub-objects.
 *
 * The parameters must be sent either as POST['data'], json encoded array or independently as GET.
 *
 * @apiSuccess {String} status "ok"
 * @apiSuccess {String} data The data provided by the api will be under this object.
 *
 *
 *
 * @apiSuccessExample {json} Success-Response:
 *{
 *   "status":"ok",
 *   "data":{
 *      "obj1":"val1",
 *      "obj2":"val2",
 *      "obj3":{
 *         "obj4":"val4",
 *         "obj5":"val5"
 *      }
 *   }
 *}
 *
 * @apiError {String} status "error"
 * @apiError {String} result Information regarding the error
 *
 * @apiErrorExample {json} Error-Response:
 *     {
 *       "status": "error",
 *       "data": "The requested action could not be completed."
 *     }
 */

use PHPCoin\Blacklist;

require_once __DIR__.'/include/init.inc.php';

$ip = Nodeutil::getRemoteAddr();
$ip = filter_var($ip, FILTER_VALIDATE_IP);
global $_config;
if ($_config['public_api'] == false && !in_array($ip, $_config['allowed_hosts'])) {
    api_err("private-api");
}

$acc = new Account();
$block = new Block();

$q = $_GET['q'];
if (!empty($_POST['data'])) {
    $data = json_decode($_POST['data'], true);
} else {
    $data = $_GET;
}

/**
 * @api {get} /api.php?q=getAddress  02. getAddress
 * @apiName getAddress
 * @apiGroup API
 * @apiDescription Converts the public key to an PHP address.
 *
 * @apiParam {string} public_key The public key
 *
 * @apiSuccess {string} data Contains the address
 */

if ($q == "getAddress") {
    $public_key = $data['public_key'];
//	_log("API: getAddress ".$public_key);
    if (strlen($public_key) < 32) {
        api_err("Invalid public key");
    }
    api_echo(Account::getAddress($public_key));
} elseif ($q == "base58") {
    /**
     * @api {get} /api.php?q=base58  03. base58
     * @apiName base58
     * @apiGroup API
     * @apiDescription Converts a string to base58.
     *
     * @apiParam {string} data Input string
     *
     * @apiSuccess {string} data Output string
     */

    api_echo(base58_encode($data['data']));
} elseif ($q == "getBalance") {
    /**
     * @api {get} /api.php?q=getBalance  04. getBalance
     * @apiName getBalance
     * @apiGroup API
     * @apiDescription Returns the balance of a specific address or public key.
     *
     * @apiParam {string} [public_key] Public key
     * @apiParam {string} [address] Address
     *
     * @apiSuccess {string} data The PHP balance
     */

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
} elseif ($q == "getPendingBalance") {
	/**
	 * @api {get} /api.php?q=getPendingBalance  05. getPendingBalance
	 * @apiName getPendingBalance
	 * @apiGroup API
	 * @apiDescription Returns the pending balance, which includes pending transactions, of a specific address or public key.
	 *
	 * @apiParam {string} [public_key] Public key
	 * @apiParam {string} [address] Address
	 *
	 * @apiSuccess {string} data The PHP balance
	 */

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
} elseif ($q=="getMempoolBalance") {
	$address = $data['address'];
	if (empty($address)) {
		api_err("Invalid address");
	}
	$address = san($address);
	if (!Account::valid($address)) {
		api_err("Invalid address");
	}
	api_echo(Account::mempoolBalance($address));
} elseif ($q == "getTransactions") {
    /**
     * @api {get} /api.php?q=getTransactions  06. getTransactions
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
     * @apiSuccess {string} signature  Transaction's signature
     * @apiSuccess {string} public_key  Account's public_key
     * @apiSuccess {string} src  Sender's address
     * @apiSuccess {string} type  "debit", "credit" or "mempool"
     * @apiSuccess {numeric} val Transaction value
     * @apiSuccess {numeric} version Transaction version
     */

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
} elseif ($q == "getTransaction") {
    /**
     * @api {get} /api.php?q=getTransaction  07. getTransaction
     * @apiName getTransaction
     * @apiGroup API
     * @apiDescription Returns one transaction.
     *
     * @apiParam {string} transaction Transaction ID
     *
     * @apiSuccess {string} block  Block ID
     * @apiSuccess {numeric} confirmation Number of confirmations
     * @apiSuccess {numeric} date  Transaction's date in UNIX TIMESTAMP format
     * @apiSuccess {string} dst  Transaction destination
     * @apiSuccess {numeric} fee  The transaction's fee
     * @apiSuccess {numeric} height  Block height
     * @apiSuccess {string} id  Transaction ID/HASH
     * @apiSuccess {string} message  Transaction's message
     * @apiSuccess {string} signature  Transaction's signature
     * @apiSuccess {string} public_key  Account's public_key
     * @apiSuccess {string} src  Sender's address
     * @apiSuccess {string} type  "debit", "credit" or "mempool"
     * @apiSuccess {numeric} val Transaction value
     * @apiSuccess {numeric} version Transaction version
     */

    $id = san($data['transaction']);
    $res = Transaction::get_transaction($id);
    if ($res === false) {
        $res = Transaction::get_mempool_transaction($id);
        if ($res === false) {
            api_err("invalid transaction");
        }
    }
    api_Echo($res);
} elseif ($q == "getPublicKey") {
    /**
     * @api {get} /api.php?q=getPublicKey  08. getPublicKey
     * @apiName getPublicKey
     * @apiGroup API
     * @apiDescription Returns the public key of a specific address.
     *
     * @apiParam {string} address Address
     *
     * @apiSuccess {string} data The public key
     */

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
} elseif ($q == "generateAccount") {
    /**
     * @api {get} /api.php?q=generateAccount  09. generateAccount
     * @apiName generateAccount
     * @apiGroup API
     * @apiDescription Generates a new account. This function should only be used when the node is on the same host or over a really secure network.
     *
     * @apiSuccess {string} address Account address
     * @apiSuccess {string} public_key Public key
     * @apiSuccess {string} private_key Private key
     */

    $res = Account::generateAcccount();
    api_echo($res);
} elseif ($q == "currentBlock") {
    /**
     * @api {get} /api.php?q=currentBlock  10. currentBlock
     * @apiName currentBlock
     * @apiGroup API
     * @apiDescription Returns the current block.
     *
     * @apiSuccess {string} id Blocks id
     * @apiSuccess {string} generator Block Generator
     * @apiSuccess {numeric} height Height
     * @apiSuccess {numeric} date Block's date in UNIX TIMESTAMP format
     * @apiSuccess {string} nonce Mining nonce
     * @apiSuccess {string} signature Signature signed by the generator
     * @apiSuccess {numeric} difficulty The base target / difficulty
     * @apiSuccess {string} argon Mining argon hash
     */

    $current = $block->current();
    api_echo($current);
} elseif ($q == "getBlock") {
    /**
     * @api {get} /api.php?q=getBlock  11. getBlock
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
     * @apiSuccess {string} argon Mining argon hash
     */
    $height = san($data['height']);
	$ret = $block->export("", $height);
    if ($ret == false) {
        api_err("Invalid block");
    } else {
        api_echo($ret);
    }
} elseif ($q == "getBlockTransactions") {
    /**
     * @api {get} /api.php?q=getBlockTransactions  12. getBlockTransactions
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
     * @apiSuccess {string} signature  Transaction's signature
     * @apiSuccess {string} public_key  Account's public_key
     * @apiSuccess {string} src  Sender's address
     * @apiSuccess {string} type  "debit", "credit" or "mempool"
     * @apiSuccess {numeric} val Transaction value
     * @apiSuccess {numeric} version Transaction version
     */
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
} elseif ($q == "version") {
    /**
     * @api {get} /api.php?q=version  13. version
     * @apiName version
     * @apiGroup API
     * @apiDescription Returns the node's version.
     *
     *
     * @apiSuccess {string} data  Version
     */
    api_echo(VERSION);
} elseif ($q == "send") {
	_log("API send");
    /**
     * @api {get} /api.php?q=send  14. send
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
     * @apiParam {numeric} [type] The version of the transaction. 1 to send coins.
     *
     * @apiSuccess {string} data  Transaction id
     */
    $current = $block->current();

    $acc = new Account();
    $block = new Block();

    $type = intval($data['type']);
    $dst = san($data['dst']);

    if (!in_array($type, [TX_TYPE_SEND])) {
	    api_err("Invalid transaction type");
    }

  
    
    if ($type==TX_TYPE_SEND) {
        if (!Account::valid($dst)) {
            api_err("Invalid destination address");
        }
    }


    $public_key = san($data['public_key']);
    if (!Account::validKey($public_key)) {
        api_err("Invalid public key");
    }
    if ($_config['use_official_blacklist']!==false) {
        if (Blacklist::checkPublicKey($public_key)) {
            api_err("Blacklisted account");
        }
    }

    $signature = san($data['signature']);
    if (!Account::validKey($signature)) {
        api_err("Invalid signature");
    }
    $date = $data['date'] + 0;

    if ($date == 0) {
        $date = time();
    }
    if ($date < time() - (3600 * 24 * 48)) {
        api_err("The date is too old");
    }
    if ($date > time() + 86400) {
        api_err("Invalid Date");
    }


    $message=$data['message'];
    if (strlen($message) > 128) {
        api_err("The message must be less than 128 chars");
    }
    $val = $data['val'] + 0;

    if ($val < 0) {
        api_err("Invalid value");
    }

	$transaction = new Transaction($public_key,$dst,$val,$type,$date,$message);
	$transaction->signature = $signature;
	$hash = $transaction->_addToMemPool($error);

	if($hash === false) {
		api_err($error);
	}
    api_echo($hash);
} elseif ($q == "mempoolSize") {
    /**
     * @api {get} /api.php?q=mempoolSize  15. mempoolSize
     * @apiName mempoolSize
     * @apiGroup API
     * @apiDescription Returns the number of transactions in mempool.
     *
     * @apiSuccess {numeric} data  Number of mempool transactions
     */

    $res = $db->single("SELECT COUNT(1) FROM mempool");
    api_echo($res);
} elseif ($q == "checkSignature") {
    /**
     * @api {get} /api.php?q=checkSignature  17. checkSignature
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

    $public_key=san($data['public_key']);
    $signature=san($data['signature']);
    $data=$data['data'];
    api_echo(Account::checkSignature($data, $signature, $public_key));
} elseif ($q == "masternodes") {
    /**
     * @api {get} /api.php?q=masternodes  18. masternodes
     * @apiName masternodes
     * @apiGroup API
     * @apiDescription Returns all the masternode data
     *
     * @apiParam {string} [public_key] Optional public key
     *
     * @apiSuccess {json} data masternode date
     */
    api_err("Not supported");
    $bind=[];
    $whr='';
    $public_key=san($data['public_key']);
    if(!empty($public_key)){
        $whr="WHERE public_key=:public_key";
        $bind[':public_key']=$public_key;
    }
    $res=$db->run("SELECT * FROM masternode $whr ORDER by public_key ASC",$bind);

    api_echo(["masternodes"=>$res, "hash"=>md5(json_encode($res))]);
} elseif ($q == "getAlias") {
    /**
     * @api {get} /api.php?q=getAlias  19. getAlias
     * @apiName getAlias
     * @apiGroup API
     * @apiDescription Returns the alias of an account
     *
     * @apiParam {string} [public_key] Public key
     * @apiParam {string} [account] Account id / address
     *
     *
     * @apiSuccess {string} data alias
     */
	api_err("Not supported");
/*    $public_key = $data['public_key'];
    $account = $data['account'];
    if (!empty($public_key) && strlen($public_key) < 32) {
        api_err("Invalid public key");
    }
    if (!empty($public_key)) {
        $account = $acc->get_address($public_key);
    }

    if (empty($account)) {
        api_err("Invalid account id");
    }
    $account = san($account);

    api_echo($acc->account2alias($account));*/
} elseif ($q === 'sync') {
    /**
     * @api            {get} /api.php?q=sync  20. sync
     * @apiName        sync
     * @apiGroup       API
     * @apiDescription Returns details about the node's sync process.
     *
     * @apiSuccess {object}  data A collection of data about the sync process.
     * @apiSuccess {boolean} data.sync_running Whether the sync process is currently running.
     * @apiSuccess {number}  data.last_sync The timestamp for the last time the sync process was run.
     * @apiSuccess {boolean} data.sync Whether the sync process is currently synchronising.
     */
    $syncRunning = file_exists(Nodeutil::getSyncFile());

    $lastSync = (int)$db->single("SELECT val FROM config WHERE cfg='sync_last'");
    $sync = (bool)$db->single("SELECT val FROM config WHERE cfg='sync'");
    api_echo(['sync_running' => $syncRunning, 'last_sync' => $lastSync, 'sync' => $sync]);
} elseif ($q === 'node-info') {
    /**
     * @api            {get} /api.php?q=node-info  21. node-info
     * @apiName        node-info
     * @apiGroup       API
     * @apiDescription Returns details about the node.
     *
     * @apiSuccess {object}  data A collection of data about the node.
     * @apiSuccess {string} data.hostname The hostname of the node.
     * @apiSuccess {string} data.version The current version of the node.
     * @apiSuccess {string} data.dbversion The database schema version for the node.
     * @apiSuccess {number} data.accounts The number of accounts known by the node.
     * @apiSuccess {number} data.transactions The number of transactions known by the node.
     * @apiSuccess {number} data.mempool The number of transactions in the mempool.
     * @apiSuccess {number} data.masternodes The number of masternodes known by the node.
     * @apiSuccess {number} data.peers The number of valid peers.
     */
    $dbVersion = $db->single("SELECT val FROM config WHERE cfg='dbversion'");
    $hostname = $db->single("SELECT val FROM config WHERE cfg='hostname'");
    $acc = $db->single("SELECT COUNT(1) FROM accounts");
    $tr = $db->single("SELECT COUNT(1) FROM transactions");
    $masternodes = $db->single("SELECT COUNT(1) FROM masternode");
    $mempool = $db->single("SELECT COUNT(1) FROM mempool");
    $peers = Peer::getCount();
    $block = new Block();
    $current = $block->current();
    api_echo([
        'hostname'     => $hostname,
        'version'      => VERSION,
        'dbversion'    => $dbVersion,
        'accounts'     => $acc,
        'transactions' => $tr,
        'mempool'      => $mempool,
        'masternodes'  => $masternodes,
        'peers'        => $peers,
	    'height'       => $current['height'],
	    'block'       => $current['id'],
	    'time'       => time(),
    ]);
} elseif ($q === 'checkAddress') {
	/**
	 * @api            {get} /api.php?q=checkAddress  22. checkAddress
	 * @apiName        checkAddress
	 * @apiGroup       API
	 * @apiDescription Checks the validity of an address.
	 *
	 * @apiParam {string} address Address
	 * @apiParam {string} [public_key] Public key
	 *
	 * @apiSuccess {boolean} data True if the address is valid, false otherwise.
	 */

	$address = $data['address'];
	$public_key = $data['public_key'];
	$acc = new Account();
	if (!Account::valid($address)) {
		api_err(false);
	}

	if (!empty($public_key)) {
		if (Account::getAddress($public_key) != $address) {
			api_err(false);
		}
	}
	api_echo(true);
} else if ($q === 'getPeers') {
	$peers = Peer::getAll();
	api_echo($peers);
} else {
    api_err("Invalid request");
}
