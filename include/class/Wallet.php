<?php


class Wallet
{

	public $wallet = "phpcoin.dat";
	public $public_key;
	public $private_key;
	public $address;
	public $command;
	private $create = false;

	function __construct($argv) {
		$env_wallet = getenv("WALLET");
		if(!empty($env_wallet)) {
			$this->wallet = $env_wallet;
		} else {
			$this->wallet = getcwd() . DIRECTORY_SEPARATOR . $this->wallet;
		}
		if(!empty($argv) && count($argv)>1) {
			for($i=1; $i<count($argv); $i++) {
				$val = trim($argv[$i]);
				$argName = "arg{$i}";
				$this->{$argName} = $val;
			}
		}
		if ((empty($this->arg1) && file_exists($this->wallet))
			|| @$this->arg1 == "help" || @$this->arg1 == "-h" || @$this->arg1 == "--help") {
			$this->help();
		}
		$this->openWallet();
		$this->processCommand();
	}

	function openWallet() {
		if (!file_exists($this->wallet)) {
			echo "No ".COIN_SYMBOL." wallet found. Generating a new wallet!\n";
			$q=readline("Would you like to encrypt this wallet? (y/N) ");
			$encrypt=false;
			if (substr(strtolower(trim($q)), 0, 1)=="y") {
				do {
					$pass=$this->readPasswordSilently("Password:");
					if (strlen($pass)<8) {
						echo "The password must be at least 8 characters long\n";
						continue;
					}
					$pass2=$this->readPasswordSilently("Confirm Password:");
					if ($pass==$pass2) {
						break;
					} else {
						echo "The passwords did not match!\n";
					}
				} while (1);
				$encrypt=true;
			}

			$res = Account::generateAcccount();

			$this->private_key=$res['private_key'];
			$this->public_key= $res['public_key'];
			$this->address = $res['address'];

			$wallet=COIN."\n".$this->private_key."\n".$this->public_key;
			if (strlen($this->private_key)<20||strlen($this->public_key)<20) {
				die("Could not generate the EC key pair. Please check the openssl binaries.");
			}
			if ($encrypt===true) {
				$wallet = $this->encryptWallet($wallet, $pass);
			}

			$res=file_put_contents($this->wallet, $wallet);
			if ($res===false||$res<30) {
				die("Could not write the wallet file! Please check the permissions on the current directory.\n");
			}
			echo "Your Address is: ".$this->address."\n";
			echo "Your Public Key is: $this->public_key\n";
			echo "Your Private Key is: $this->private_key\n";
			$this->create = true;
		} else {
			$wallet=trim(file_get_contents($this->wallet));
			if (substr($wallet, 0, strlen(COIN))!=COIN) {
				echo "This wallet is encrypted.\n";
				do {
					$pass=$this->readPasswordSilently("Password:");
					$decrypted = $this->decryptWallet($wallet, $pass);
					if (substr($decrypted, 0, strlen(COIN))==COIN) {
						$wallet=$decrypted;
						break;
					}
					echo "Invalid password!\n";
				} while (1);
			}
			$a=explode("\n", $wallet);
			$this->public_key=trim($a[2]);
			$this->private_key=trim($a[1]);

			$this->address=Account::getAddress($this->public_key);
			echo "Your address is: ".$this->address."\n\n";
		}
	}

	function encryptWallet($wallet, $pass) {
		$passphrase = substr(hash('sha256', $pass, false),0,32);
		$iv = substr(bin2hex(random_bytes(16)), 0, 16);
		$enc = openssl_encrypt($wallet, 'aes-256-cbc', $passphrase, OPENSSL_RAW_DATA, $iv);
		$enc2 = $iv . base64_encode($enc);
		$enc3 = base64_encode($enc2);
		return $enc3;
	}

	function decryptWallet($encrypted, $pass) {
		$passphrase = substr(hash('sha256', $pass, false),0,32);
		$enc2 = base64_decode($encrypted);
		$iv = substr($enc2, 0, 16);
		$enc = base64_decode(substr($enc2, 16));
		$wallet = openssl_decrypt($enc, 'aes-256-cbc', $passphrase, OPENSSL_RAW_DATA, $iv);
		return $wallet;
	}

	function processCommand() {
		$this->command = @$this->arg1;
		switch ($this->command) {
			case "balance":
				$this->balance(@$this->arg2);
				break;
			case "export":
				$this->export();
				break;
			case "block":
				$this->block();
				break;
			case "encrypt":
				$this->encrypt();
				break;
			case "decrypt":
				$this->decrypt();
				break;
			case "transactions":
				$this->transactions();
				break;
			case "transaction":
				$this->transaction(@$this->arg2);
				break;
			case "send":
				$this->send(@$this->arg2,@$this->arg3,@$this->arg4);
				break;
			case "login-link":
				$this->loginLink();
				break;
			case "masternode-create":
				$this->createMasternode(@$this->arg2);
				break;
			case "masternode-remove":
				$this->removeMasternode(@$this->arg2, @$this->arg3);
				break;
			default:
				echo !$this->create ? "Invalid command\n" : "";
		}
	}

	function balance($address = null) {
		if (!empty($address)) {
			echo "Checking balance of the specified address: {$address}" . PHP_EOL;
			if (!Account::valid($address)) {
				die("Error: invalid address format." . PHP_EOL);
			}
		} else {
			$address = $this->address;
		}
		$res=$this->wallet_peer_post("/api.php?q=getPendingBalance&" . XDEBUG, array("address"=>$address));
		$this->checkApiResponse($res);
		echo "Balance: {$res['data']}\n";
	}

	function export() {
		echo "Your Public Key is: $this->public_key\n";
		echo "Your Private Key is: $this->private_key\n";
	}

	function block() {
		$res=$this->wallet_peer_post("/api.php?q=currentBlock");
		$this->checkApiResponse($res);
		foreach ($res['data'] as $x=>$l) {
			echo "$x = $l\n";
		}
	}

	function encrypt() {
		do {
			$pass=$this->readPasswordSilently("Password:");
			if (strlen($pass)<8) {
				echo "The password must be at least 8 characters long\n";
				continue;
			}
			$pass2=$this->readPasswordSilently("Confirm Password:");
			if ($pass==$pass2) {
				break;
			} else {
				echo "The passwords did not match!\n";
			}
		} while (1);
		$wallet=COIN."\n{$this->private_key}\n{$this->public_key}";
		$wallet = $this->encryptWallet($wallet, $pass);
		$res=file_put_contents($this->wallet, $wallet);
		if ($res===false||$res<30) {
			echo "Your Public Key is: $this->public_key\n";
			echo "Your Private Key is: $this->private_key\n";
			die("Could not write the wallet file! Please check the permissions on the current directory and save a backup of the above keys.\n");
		}
	}

	function decrypt() {
		$wallet=COIN."\n$this->private_key\n$this->public_key";
		$res=file_put_contents($this->wallet, $wallet);
		if ($res===false||$res<30) {
			echo "Your Public Key is: $this->public_key\n";
			echo "Your Private Key is: $this->private_key\n";
			die("Could not write the wallet file! Please check the permissions on the current directory and save a backup of the above keys.\n");
		}
		echo "The wallet has been decrypted!\n";
	}

	function transactions() {
		$res=$this->wallet_peer_post("/api.php?q=getTransactions&".XDEBUG, array("address"=>$this->address));
		$this->checkApiResponse($res);
		echo "ID\tTo\tType\tSum\n";
		foreach ($res['data'] as $x) {
			printf("%4s %4s %4s %4.f\n", str_pad($x['id'], 90, " ", STR_PAD_RIGHT),
				$x['dst'], str_pad($x['type'],10," ", STR_PAD_RIGHT), $x['val']);
		}
	}

	function transaction($transactionId) {
		if (empty($transactionId)) {
			die("Error: missing transaction id" . PHP_EOL);
		}
		$res=$this->wallet_peer_post("/api.php?q=getTransaction", array("transaction"=>$transactionId));
		$this->checkApiResponse($res);
		foreach ($res['data'] as $x=>$l) {
			echo "$x = $l\n";
		}
	}

	function send($address, $amount, $msg) {
		if (empty($address)) {
			die("ERROR: Missing destination address");
		}
		if (empty($amount)) {
			die("ERROR: Invalid amount");
		}

		$res=$this->wallet_peer_post("/api.php?q=getPendingBalance", array("address"=>$this->address));
		$this->checkApiResponse($res);
		$balance=$res['data'];
		$fee=$amount*TX_FEE;
//		if ($fee<TX_MIN_FEE) {
//			$fee=TX_MIN_FEE;
//		}
//		if ($fee>TX_MAX_FEE) {
//			$fee=TX_MAX_FEE;
//		}
		$total=$amount+$fee;

		$val=num($amount);
		$fee=num($fee);
		if ($balance<$total) {
			die("ERROR: Not enough funds in balance\n");
		}
		$date=time();
		if(empty($msg)) $msg = "";
		$info=$val."-".$fee."-".$address."-".$msg."-".TX_TYPE_SEND."-".$this->public_key."-".$date;
		$signature=ec_sign($info, $this->private_key);

		$res = $this->wallet_peer_post("/api.php?q=send&" . XDEBUG,
			array("dst" => $address, "val" => $val, "signature" => $signature,
				"public_key" => $this->public_key, "type" => TX_TYPE_SEND,
				"message" => $msg, "date" => $date));
		$this->checkApiResponse($res);
		echo "Transaction sent! Transaction id: {$res['data']}\n";
	}

	function loginLink() {
		$loginCode = rand(100000, 999999);
		$signature = ec_sign($loginCode, $this->private_key);
		$url=$this->get_peer_server()."/apps/wallet/login.php?action=login-link&login_code={$loginCode}&public_key={$this->public_key}&login_key={$signature}";
		echo "Your login link:".PHP_EOL;
		echo $url;
		echo PHP_EOL;
	}

	function checkApiResponse($res) {
		if ($res['status']!="ok") {
			die("ERROR: {$res['data']}\n");
		}
	}


	function get_peer_server() {
		$f=file(REMOTE_PEERS_LIST_URL);

		shuffle($f);

		foreach ($f as $x) {
			if (strlen(trim($x))>5) {
				$peer=trim($x);
				break;
			}
		}

		if (empty($peer)) {
			return false;
		}

		if(DEVELOPMENT) {
			return "https://spectre:8000";
		} else {
			return $peer;
		}
	}

	function wallet_peer_post($url, $data = [], $timeout = 60, $debug = false) {
		$url = $this->get_peer_server() . $url;
		if ($debug) {
			echo "\nPeer post: $url\n";
		}
		if (!isValidURL($url)) {
			return false;
		}
		$postdata = http_build_query(
			[
				'data' => json_encode($data),
				"coin" => COIN,
				"version"=> VERSION,
				"network"=> NETWORK,
			]
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$postdata );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, DEVELOPMENT ? 0 : 2);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, !DEVELOPMENT);
		$result = curl_exec($ch);
		curl_close ($ch);

		$res = json_decode($result, true);





		return $res;
	}

	function read() {

	}

	function save () {

	}

	function readPasswordSilently(string $prompt = ''): string
	{
		if ($this->checkSystemFunctionAvailability('shell_exec') && rtrim(shell_exec("/usr/bin/env bash -c 'echo OK'")) === 'OK') {
			$password = rtrim(
				shell_exec(
					"/usr/bin/env bash -c 'read -rs -p \""
					. addslashes($prompt)
					. "\" mypassword && echo \$mypassword'"
				)
			);
			echo PHP_EOL;
		} else {
			/**
			 * Can't invoke bash or shell_exec is disabled, let's do it with a regular input instead.
			 */
			$password = readline($prompt . ' ');
		}

		return $password;
	}

	function checkSystemFunctionAvailability(string $function_name): bool
	{
		return !in_array(
			$function_name,
			explode(',', ini_get('disable_functions'))
		);
	}

	//php wallet.php masternode-create LWNkKuU41paSgzFtnKgjiJsKkoo3HwV39C
	function createMasternode($mnAddress) {
		$date=time();
		$msg = "mncreate";
		$tx = new Transaction($this->public_key, $mnAddress, MN_COLLATERAL, TX_TYPE_MN_CREATE, $date, $msg);
		$signature = $tx->sign($this->private_key);

		$res = $this->wallet_peer_post("/api.php?q=send&" . XDEBUG,
			array("dst" => $mnAddress, "val" => MN_COLLATERAL, "signature" => $signature,
				"public_key" => $this->public_key, "type" => TX_TYPE_MN_CREATE,
				"message" => $msg, "date" => $date));
		$this->checkApiResponse($res);
		echo "Transaction created: ".$res['data'];
	}

	function removeMasternode($payoutAddress) {
		$mnAddress = Account::getAddress($this->public_key);
		$date=time();
		$msg = "mnremove";
		$tx = new Transaction($this->public_key, $payoutAddress, MN_COLLATERAL, TX_TYPE_MN_REMOVE, $date, $msg);
		$signature = $tx->sign($this->private_key);

		$res = $this->wallet_peer_post("/api.php?q=send&" . XDEBUG,
			array("dst" => $payoutAddress, "val" => MN_COLLATERAL, "signature" => $signature,
				"public_key" => $this->public_key, "type" => TX_TYPE_MN_REMOVE,
				"message" => $msg, "date" => $date));
		$this->checkApiResponse($res);
		echo "Transaction created: ".$res['data'];
	}

	function help() {
		die("wallet <command> <options>

Commands:

balance                         prints the balance of the wallet 
balance <address>               prints the balance of the specified address
export                          prints the wallet data
block                           show data about the current block
encrypt                         encrypts the wallet
decrypt                         decrypts the wallet
transactions                    show the latest transactions
transaction <id>                shows data about a specific transaction
send <address> <value> <msg>    sends a transaction (message optional)
login-link                      generate login link
masternode-create <address>     create masternode with address
masternode-remove <payoutaddress>    remove masternode with address
");
	}

}
