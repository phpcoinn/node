<?php

class Util
{

	/**
	 * @api {php util.php} clean Clean
	 * @apiName clean
	 * @apiGroup UTIL
	 * @apiDescription Cleans the entire database
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php clean
	 */
	static function clean() {
		Nodeutil::clean();
	}

	/**
	 * @api {php util.php} pop Pop
	 * @apiName pop
	 * @apiGroup UTIL
	 * @apiDescription Delete n last blocks
	 *
	 * @apiParam {Number} arg2 Number of blocks to delete
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php pop 1
	 */
	static function pop($argv) {
		$no = intval(@$argv[2]);
		if(empty($no)) {
			$no = 1;
		}
		Nodeutil::deleteLatestBlocks($no);
	}

	static function clearBlocks($argv) {
		$height = intval($argv[2]);
		if(empty($height)) {
			return;
		}
		$current_height = Block::getHeight();
		$no = $current_height - $height;
		if($no <= 0) {
			return;
		}
		Nodeutil::deleteLatestBlocks($no);
	}

	/**
	 * @api {php util.php} block-time Block-time
	 * @apiName block-time
	 * @apiGroup UTIL
	 * @apiDescription Shows the block time of the last 100 blocks
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php block-time
	 *
	 * @apiSuccessExample {text} Success-Response:
	 * 16830 -> 323
	 * ...
	 * 16731 -> 302
	 * Average block time: 217 seconds
	 */
	static function blockTime() {
		global $db;
		$t = time();
		$r = $db->run("SELECT * FROM blocks ORDER by height DESC LIMIT 100");
		$start = 0;
		foreach ($r as $x) {
			if ($start == 0) {
				$start = $x['date'];
			}
			$time = $t - $x['date'];
			$t = $x['date'];
			echo "$x[height]\t\t$time\t\t$x[difficulty]\n";
			$end = $x['date'];
		}
		$count = count($r);
		echo "Average block time: ".ceil(($start - $end) / $count)." seconds\n";
	}

	/**
	 * @api {php util.php} peer Peer
	 * @apiName peer
	 * @apiGroup UTIL
	 * @apiDescription Creates a peering session with another node
	 *
	 * @apiParam {text} arg2 The Hostname of the other node
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php peer http://peer1.phpcoin.net
	 *
	 * @apiSuccessExample {text} Success-Response:
	 * Peering OK
	 */
	static function peer($argv) {
		global $_config;
		$peer = $argv[2];
		if(empty($peer)) {
			die("Missing <peer> argument. Command: peer <peer>".PHP_EOL);
		}
		$res = peer_post($argv[2]."/peer.php?q=peer", ["hostname" => $_config['hostname'], 'repeer'=>1]);
		if ($res !== false) {
			echo "Peering OK\n";
		} else {
			echo "Peering FAIL\n";
		}
	}

	/**
	 * @api {php util.php} current Current
	 * @apiName current
	 * @apiGroup UTIL
	 * @apiDescription Prints the current block in var_dump
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php current
	 *
	 * @apiSuccessExample {text} Success-Response:
	 * array(9) {
	 *  ["id"]=>
	 *  string(88) "4khstc1AknzDXg8h2v12rX42vDrzBaai6Rz53mbaBsghYN4DnfPhfG7oLZS24Q92MuusdYmwvDuiZiuHHWgdELLR"
	 *  ["generator"]=>
	 *  string(88) "5ADfrJUnLefPsaYjMTR4KmvQ79eHo2rYWnKBRCXConYKYJVAw2adtzb38oUG5EnsXEbTct3p7GagT2VVZ9hfVTVn"
	 *  ["height"]=>
	 *  int(16833)
	 *  ["date"]=>
	 *  int(1519312385)
	 *  ["nonce"]=>
	 *  string(41) "EwtJ1EigKrLurlXROuuiozrR6ICervJDF2KFl4qEY"
	 *  ["signature"]=>
	 *  string(97) "AN1rKpqit8UYv6uvf79GnbjyihCPE1UZu4CGRx7saZ68g396yjHFmzkzuBV69Hcr7TF2egTsEwVsRA3CETiqXVqet58MCM6tu"
	 *  ["difficulty"]=>
	 *  string(8) "61982809"
	 *  ["argon"]=>
	 *  string(68) "$SfghIBNSHoOJDlMthVcUtg$WTJMrQWHHqDA6FowzaZJ+O9JC8DPZTjTxNE4Pj/ggwg"
	 *  ["transactions"]=>
	 *  int(0)
	 * }
	 *
	 */
	static function current() {
		echo json_encode(Block::current(), JSON_PRETTY_PRINT) . PHP_EOL;
	}

	/**
	 * @api {php util.php} blocks Blocks
	 * @apiName blocks
	 * @apiGroup UTIL
	 * @apiDescription Prints the id and the height of the blocks >=arg2, max 100 or arg3
	 *
	 * @apiParam {number} arg2 Starting height
	 *
	 * @apiParam {number} [arg3] Block Limit
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php blocks 10800 5
	 *
	 * @apiSuccessExample {text} Success-Response:
	 * 10801   2yAHaZ3ghNnThaNK6BJcup2zq7EXuFsruMb5qqXaHP9M6JfBfstAag1n1PX7SMKGcuYGZddMzU7hW87S5ZSayeKX
	 * 10802   wNa4mRvRPCMHzsgLdseMdJCvmeBaCNibRJCDhsuTeznJh8C1aSpGuXRDPYMbqKiVtmGAaYYb9Ze2NJdmK1HY9zM
	 * 10803   3eW3B8jCFBauw8EoKN4SXgrn33UBPw7n8kvDDpyQBw1uQcmJQEzecAvwBk5sVfQxUqgzv31JdNHK45JxUFcupVot
	 * 10804   4mWK1f8ch2Ji3D6aw1BsCJavLNBhQgpUHBCHihnrLDuh8Bjwsou5bQDj7D7nV4RsEPmP2ZbjUUMZwqywpRc8r6dR
	 * 10805   5RBeWXo2c9NZ7UF2ubztk53PZpiA4tsk3bhXNXbcBk89cNqorNj771Qu4kthQN5hXLtu1hzUnv7nkH33hDxBM34m
	 *
	 */
	static function blocks($argv) {
		global $db;
		$height = intval($argv[2]);
		if(empty($height)) {
			die("Missing height argument.".PHP_EOL);
		}
		$limit = intval($argv[3]);
		if ($limit < 1) {
			$limit = 100;
		}
		$r = $db->run("SELECT * FROM blocks WHERE height>:height ORDER by height LIMIT :limit", [":height" => $height, ":limit"=>$limit]);
		foreach ($r as $x) {
			echo "$x[height]\t$x[id]\n";
		}
	}

	/**
	 * @api {php util.php} recheck-blocks Recheck-Blocks
	 * @apiName recheck-blocks
	 * @apiGroup UTIL
	 * @apiDescription Recheck all the blocks to make sure the blockchain is correct
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php recheck-blocks
	 *
	 */
	static function recheckBlocks() {
		global $db;
		$blocks = [];
		$r = $db->run("SELECT * FROM blocks ORDER by height");
		foreach ($r as $x) {
			$blocks[$x['height']] = $x;
			$max_height = $x['height'];
		}
		for ($i = 2; $i <= $max_height; $i++) {
			self::log("Checking block $i / $max_height") ;
			$data = $blocks[$i];

			$block = Block::export($data['id']);
			if (!Block::getFromArray($block)->verifyBlock()) {
				self::log("Invalid block detected. We should delete everything after $data[height] - $data[id]");
				break;
			}
		}
	}

	static function log($s) {
		echo $s . PHP_EOL;
	}

	/**
	 * @api {php util.php} peers Peers
	 * @apiName peers
	 * @apiGroup UTIL
	 * @apiDescription Prints all the peers and their status
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php peers
	 *
	 * @apiSuccessExample {text} Success-Response:
	 * http://35.190.160.142   1.2.3.4
	 * ...
	 * http://php.master.hashpi.com    active
	 */
	static function peers() {
		$r = Peer::getAll();
		foreach ($r as $x) {
			echo "$x[hostname]\t$x[ip]\n";
		}
	}

	/**
	 * @api {php util.php} mempool Mempool
	 * @apiName mempool
	 * @apiGroup UTIL
	 * @apiDescription Prints the number of transactions in mempool
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php mempool
	 *
	 * @apiSuccessExample {text} Success-Response:
	 * Mempool size: 12
	 */
	static function mempool() {
		$res = Mempool::getSize();
		echo "Mempool size: $res\n";
	}

	/**
	 * @api {php util.php} delete-peer Delete-peer
	 * @apiName delete-peer
	 * @apiGroup UTIL
	 * @apiDescription Removes a peer from the peerlist
	 *
	 * @apiParam {text} arg2 Peer's hostname
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php delete-peer http://peer1.phpcoin.net
	 *
	 * @apiSuccessExample {text} Success-Response:
	 * Peer removed
	 */
	static function deletePeer($argv) {
		$peer = trim($argv[2]);
		if (empty($peer)) {
			die("Invalid peer");
		}
		Peer::deleteByIp($peer);
		echo "Peer removed\n";
	}

	static function recheckPeers() {
		$r = Peer::getAll();
		foreach ($r as $x) {
			$a = peer_post($x['hostname']."/peer.php?q=ping");
			if ($a != "pong") {
				echo "$x[hostname] -> failed\n";
				Peer::delete($x['id']);
			} else {
				echo "$x[hostname] ->ok \n";
			}
		}
	}

	/**
	 * @api {php util.php} peers-block Peers-Block
	 * @apiName peers-block
	 * @apiGroup UTIL
	 * @apiDescription Prints the current height of all the peers
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php peers-block
	 *
	 * @apiSuccessExample {text} Success-Response:
	 * http://peer5.phpcoin.net       16849
	 * ...
	 * http://peer10.phpcoin.net        16849
	 */
	static function peersBlock($argv) {
		global $db;
		$only_diff = false;
		if ($argv[2] == "diff") {
			$current = $db->single("SELECT height FROM blocks ORDER by height DESC LIMIT 1");
			$only_diff = true;
		}
		$r = Peer::getActive();
		foreach ($r as $x) {
			$a = peer_post($x['hostname']."/peer.php?q=currentBlock", []);
			$enc = base58_encode($x['hostname']);
			if ($argv[2] == "debug") {
				echo "$enc\t";
			}
			if ($only_diff == false || $current != $a["block"]['height']) {
				echo "$x[hostname]\t$a[height]\n";
			}
		}
	}

	/**
	 * @api {php util.php} balance Balance
	 * @apiName balance
	 * @apiGroup UTIL
	 * @apiDescription Prints the balance of an address or a public key
	 *
	 * @apiParam {text} arg2 address or public_key
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php balance 5WuRMXGM7Pf8NqEArVz1NxgSBptkimSpvuSaYC79g1yo3RDQc8TjVtGH5chQWQV7CHbJEuq9DmW5fbmCEW4AghQr
	 *
	 * @apiSuccessExample {text} Success-Response:
	 * Balance: 2,487
	 */
	static function balance($argv) {
		global $db;
		$id = san($argv[2]);
		if(empty($id)) {
			echo "Missing arguments: balance <address>".PHP_EOL;
			exit;
		}
		$res = $db->single(
			"SELECT balance FROM accounts WHERE id=:id OR public_key=:id2 LIMIT 1",
			[":id" => $id, ":id2" => $id]
		);

		echo "Balance: ".num($res)."\n";
	}

	/**
	 * @api {php util.php} block Block
	 * @apiName block
	 * @apiGroup UTIL
	 * @apiDescription Returns a specific block
	 *
	 * @apiParam {text} arg2 block id
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php block 4khstc1AknzDXg8h2v12rX42vDrzBaai6Rz53mbaBsghYN4DnfPhfG7oLZS24Q92MuusdYmwvDuiZiuHHWgdELLR
	 *
	 * @apiSuccessExample {text} Success-Response:
	 * array(9) {
	 *  ["id"]=>
	 *  string(88) "4khstc1AknzDXg8h2v12rX42vDrzBaai6Rz53mbaBsghYN4DnfPhfG7oLZS24Q92MuusdYmwvDuiZiuHHWgdELLR"
	 *  ["generator"]=>
	 *  string(88) "5ADfrJUnLefPsaYjMTR4KmvQ79eHo2rYWnKBRCXConYKYJVAw2adtzb38oUG5EnsXEbTct3p7GagT2VVZ9hfVTVn"
	 *  ["height"]=>
	 *  int(16833)
	 *  ["date"]=>
	 *  int(1519312385)
	 *  ["nonce"]=>
	 *  string(41) "EwtJ1EigKrLurlXROuuiozrR6ICervJDF2KFl4qEY"
	 *  ["signature"]=>
	 *  string(97) "AN1rKpqit8UYv6uvf79GnbjyihCPE1UZu4CGRx7saZ68g396yjHFmzkzuBV69Hcr7TF2egTsEwVsRA3CETiqXVqet58MCM6tu"
	 *  ["difficulty"]=>
	 *  string(8) "61982809"
	 *  ["argon"]=>
	 *  string(68) "$SfghIBNSHoOJDlMthVcUtg$WTJMrQWHHqDA6FowzaZJ+O9JC8DPZTjTxNE4Pj/ggwg"
	 *  ["transactions"]=>
	 *  int(0)
	 * }
	 */
	static function block($argv) {
		global $db;
		$id = san($argv[2]);
		if(empty($id)) {
			echo "Missing argument: block <height|id>".PHP_EOL;
			exit;
		}
		$res = $db->row("SELECT * FROM blocks WHERE id=:id OR height=:id2 LIMIT 1", [":id" => $id, ":id2" => $id]);

		var_dump($res);
	}

	/**
	 * @api {php util.php} check-address Check-Address
	 * @apiName check-address
	 * @apiGroup UTIL
	 * @apiDescription Checks a specific address for validity
	 *
	 * @apiParam {text} arg2 block id
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php check-address 4khstc1AknzDXg8h2v12rX42vDrzBaai6Rz53mbaBsghYN4DnfPhfG7oLZS24Q92MuusdYmwvDuiZiuHHWgdELLR
	 *
	 * @apiSuccessExample {text} Success-Response:
	 * The address is valid
	 */
	static function checkAddress($argv) {
		$dst = trim($argv[2]);
		if(empty($dst)) {
			echo "Missing argument: check-address <address>".PHP_EOL;
			exit;
		}
		if (!Account::valid($dst)) {
			die("Invalid address: $dst".PHP_EOL);
		}
		echo "The address $dst is valid\n";
	}

	/**
	 * @api {php util.php} get-address Get-Address
	 * @apiName get-address
	 * @apiGroup UTIL
	 * @apiDescription Converts a public key into an address
	 *
	 * @apiParam {text} arg2 public key
	 *
	 * @apiExample {cli} Example usage:
	 * php util.php get-address PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCwQr8cE5s6APWAE1SWAmH6NM1nJTryBURULEsifA2hLVuW5GXFD1XU6s6REG1iPK7qGaRDkGpQwJjDhQKVoSVkSNp
	 *
	 * @apiSuccessExample {text} Success-Response:
	 * 5WuRMXGM7Pf8NqEArVz1NxgSBptkimSpvuSaYC79g1yo3RDQc8TjVtGH5chQWQV7CHbJEuq9DmW5fbmCEW4AghQr
	 */
	static function getAddress($argv) {
		$public_key = trim($argv[2]);
		if(empty($public_key)) {
			echo "Missing argument: get-address <public-key>".PHP_EOL;
			exit;
		}
		if (strlen($public_key) < 32) {
			die("Invalid public key: $public_key".PHP_EOL);
		}
		print(Account::getAddress($public_key));
		echo PHP_EOL;
	}

	static function cleanBlacklist() {
		Peer::cleanBlacklist();
		echo "All the peers have been removed from the blacklist\n";
	}

	static function compareBlocks($argv) {
		$current=Block::current();
		$peer=trim($argv[2]);
		if(empty($peer)) {
			die("Missing arguments: compare-blocks <peer> [<limit>]".PHP_EOL);
		}
		$limit=intval($argv[3]);
		if ($limit==0) {
			$limit=5000;
		}
		for ($i=$current['height']-$limit;$i<=$current['height'];$i++) {
			$data=peer_post($peer."/peer.php?q=getBlock", ["height" => $i]);
			if ($data==false) {
				continue;
			}
			$our=Block::export(false, $i);
			if ($data!=$our) {
				echo "Failed block -> $i\n";
				if ($argv[4]=="dump") {
					echo "\n\n  ---- Internal ----\n\n";
					var_dump($our);
					echo "\n\n  ---- External ----\n\n";
					var_dump($data);
				}
			}
		}
	}

	static function compareAccounts($argv) {
		global $db;
		$peer=trim($argv[2]);
		if(empty($peer)) {
			die("Missing arguments: compare-accounts <peer>".PHP_EOL);
		}
		$r=$db->run("SELECT id,balance FROM accounts");
		foreach ($r as $x) {
			$data=peer_post($peer."/api.php?q=getBalance", ["address" => $x['id']]);
			if ($data==false) {
				continue;
			}
			if ($data!=$x['balance']) {
				echo "$x[id]\t\t$x[balance]\t$data\n";
			}
		}
	}

	static function masternodeHash() {
		global $db;
		$res=$db->run("SELECT * FROM masternode ORDER by public_key");
		$current=Block::current();
		echo "Height:\t\t$current[height]\n";
		echo "Hash:\t\t".md5(json_encode($res))."\n\n";
	}

	static function accountsHash() {
		$res=Nodeutil::calculateAccountsHash();
        global $argv;
        if(in_array("--json", $argv)) {
            echo json_encode($res);
            exit;
        }
		echo "Height:\t\t".$res['height']."\n";
		echo "Hash:\t\t".$res['hash']."\n\n";
	}

	static function scStateHash($argv) {
        $height=intval(@$argv[2]);
		$res=Nodeutil::calculateSmartContractsHashV2($height);
        echo json_encode($res);
        exit;
	}

	static function blocksHash($argv) {
		$height=intval($argv[2]);
		$res=Nodeutil::calculateBlocksHash($height);
		echo "Height:\t\t".$res['height']."\n";
		echo "Hash:\t\t".$res['hash']."\n\n";
	}

	static function version() {
		echo "\n\n".VERSION."\n\n";
	}

	static function sendblock($argv) {
		$height=intval($argv[2]);
		if(empty($height)) {
			die("Missing arguments: sendblock <height> <peer>".PHP_EOL);
		}
		$peer=trim($argv[3]);
		if(empty($peer)) {
			die("Missing arguments: sendblock <height> <peer>".PHP_EOL);
		}
		if (!filter_var($peer, FILTER_VALIDATE_URL)) {
			die("Invalid peer hostname".PHP_EOL);
		}
		$peer = filter_var($peer, FILTER_SANITIZE_URL);
		$data = Block::export("", $height);


		if ($data===false) {
			die("Could not find this block");
		}
		$response = peer_post($peer."/peer.php?q=submitBlock", $data);
		var_dump($response);
	}

	static function recheckExternalBlocks($argv) {
		$peer=trim($argv[2]);
		if(empty($peer)) {
			die("Missing arguments: recheck-external-blocks <peer> [<height>]".PHP_EOL);
		}
		$height=intval($argv[3]);
		if (!filter_var($peer, FILTER_VALIDATE_URL)) {
			die("Invalid peer hostname".PHP_EOL);
		}
		$peer = filter_var($peer, FILTER_SANITIZE_URL);

		if(empty($height)) {
			$height=1;
		}

		$last=peer_post($peer."/peer.php?q=currentBlock");

		$b=peer_post($peer."/peer.php?q=getBlock", ["height"=>$height]);

		for ($i = $height+1; $i <= $last['block']['height']; $i++) {
			$c=peer_post($peer."/peer.php?q=getBlock", ["height"=>$i]);
			$block = Block::getFromArray($c);
			if (!$block->mine()) {
				print("Invalid block detected. $c[height] - $c[id]\n");
				break;
			}
			echo "Block $i -> ok\n";
			$b=$c;
		}
	}

	static function checkBlock($argv) {
		$peer=trim($argv[2]);
		if(empty($peer)) {
			die("Missing arguments: check-block <peer> <height>".PHP_EOL);
		}
		$height=intval($argv[3]);
		if(empty($height)) {
			die("Missing arguments: check-block <peer> <height>".PHP_EOL);
		}
		if (!filter_var($peer, FILTER_VALIDATE_URL)) {
			die("Invalid peer hostname");
		}
		$peer = filter_var($peer, FILTER_SANITIZE_URL);
		$b=peer_post($peer."/peer.php?q=getBlock", ["height"=>$height]);
		$block = Block::getFromArray($b);
		if (!$block->mine()) {
			print("Block is invalid\n");
		} else {
			print("Block is valid\n");
		}
	}

	static function findForkedBlock($argv) {
		$peer = trim($argv[2]);
		if(empty($peer)) {
			die("Missing arguments: find-forked-block <peer>".PHP_EOL);
		}
		if (!filter_var($peer, FILTER_VALIDATE_URL)) {
			die("Invalid peer hostname".PHP_EOL);
		}
		$peer = filter_var($peer, FILTER_SANITIZE_URL);
		$invalid_block = Nodeutil::checkBlocksWithPeer($peer);
		if (empty($invalid_block)) {
			echo("No invalid block".PHP_EOL);
		} else {
			echo("Invalid block $invalid_block".PHP_EOL);
		}
	}

	static function validatePublicKey($argv) {
		$public_key = trim($argv[2]);
		if (empty($public_key)) {
			die("Missing arguments: validate-public-key <public-key>".PHP_EOL);
		}
		$res = Nodeutil::validatePublicKey($public_key);
		if ($res) {
			echo "Public key is valid" . PHP_EOL;
		} else {
			echo "Public key is INVALID" . PHP_EOL;
		}
	}

	static function rewardsScheme($real=true) {
		$rows = Blockchain::calculateRewardsScheme($real);


		echo str_pad("phase", 10);
		echo str_pad("block", 10);
		echo str_pad('total', 10);
		echo str_pad('miner', 10);
		echo str_pad('gen', 10);
		echo str_pad('mn', 10);
		echo str_pad('pos', 10);
		echo str_pad('elapsed', 24);
		echo str_pad('days', 10);
		echo str_pad('time', 24);
		echo str_pad('supply', 10);
		echo PHP_EOL;

		foreach ($rows as $row) {
			echo str_pad($row['phase'], 10);
			echo str_pad($row['block'], 10);
			echo str_pad($row['total'], 10);
			echo str_pad($row['miner'], 10);
			echo str_pad($row['gen'], 10);
			echo str_pad($row['mn'], 10);
			echo str_pad($row['pos'], 10);
			echo str_pad($row['elapsed'], 24);
			echo str_pad(round($row['days'],2), 10);
			echo str_pad(date("Y-m-d H:i:s",$row['time']), 24);
			echo str_pad($row['supply'], 10);
			echo PHP_EOL;
		}
	}

	static function downloadApps() {
		Nodeutil::downloadApps();
	}

	static function verifyBlocks($argv) {
		$range = @$argv[2];
		if(empty($range)) {
			$start=1;
			$stop= Block::getHeight();
		} else if (strpos($range, "-")!== false) {
			$arr=explode("-", $range);
			$start = $arr[0];
			if(empty($start)) {
				$start = 1;
			}
			$stop = $arr[1];
			if(empty($stop)) {
				$stop= Block::getHeight();
			}
		} else {
			$start = intval($range);
			$stop = $start;
		}

		for($i=$start;$i<=$stop;$i++) {
			$block = Block::export("",$i);
			$res = Block::getFromArray($block)->verifyBlock($error);
			echo "Verify block $i / $stop res=$res error=$error".PHP_EOL;
			if(!$res) {
				return;
			}
		}
	}

	static function exportchain($argv) {
		$file = $argv[2];
		if(empty($file)) {
			$file = getcwd() . "/blockchain.txt";
		}
		$height = Block::getHeight();
		echo "Exporting blockchain to file: " . $file.PHP_EOL;
		@file_put_contents($file,"");
		for($i=1;$i<=$height;$i++) {
			$block = Block::export("",$i);
			if($i % 100 == 0) {
				echo "Exporting block $i".PHP_EOL;
			}
			@file_put_contents($file, json_encode($block).PHP_EOL, FILE_APPEND);
		}
		echo "Export finished. File: $file".PHP_EOL;
	}

	static function exportdb($argv) {

		if(isset($argv[2])) {
			$file = $argv[2];
			if(isset($argv[3])) {
				$options = $argv[3];
			}
		}
		if(empty($file)) {
			$file = getcwd() . "/blockchain.sql";
		}
		global $db;
		$db_name = $db->single('select database()');
		echo "Exporting database...".PHP_EOL;
		$cmd = "mysqldump $options --single-transaction $db_name accounts blocks transactions masternode smart_contracts smart_contract_state > $file";
		shell_exec($cmd);
		echo "Database exported".PHP_EOL;
	}

	static function importdb($argv) {
		$file = trim($argv[2]);
        $options = "";
		if(isset($argv[3])) {
			$options = $argv[3];
		}
		if(empty($file)) {
			die("Missing argument <file>".PHP_EOL."Command: importdb <file>".PHP_EOL);
		}
		if(!file_exists($file)) {
			die("Can not found file: $file".PHP_EOL);
		}
		$namedAgs = process_cmdline_args($argv);
		if(isset($namedAgs['compress'])) {
			echo "Converting tables to compressed...".PHP_EOL;
			$sed_cmd="sed -i 's/ROW_FORMAT=DYNAMIC/ROW_FORMAT=COMPRESSED/g' $file";
			shell_exec($sed_cmd);
		}
		echo "Importing database...".PHP_EOL;
		global $db;
		$db_name = $db->single('select database()');
		$cmd = "mysql $options $db_name < $file";
		shell_exec($cmd);
		echo "Database imported".PHP_EOL;
	}

	static function importchain($argv) {
		global $db;
		$file = trim($argv[2]);
		if(isset($argv[3])) {
			$verify = $argv[3];
		} else {
			$verify = true;
		}
		if(empty($file)) {
			die("Missing argument <file>".PHP_EOL."Command: importchain <file>".PHP_EOL);
		}
		if(!file_exists($file)) {
			die("Can not found file: $file".PHP_EOL);
		}
		$sync = Config::isSync();
		if ($sync) {
			die("Sync running. Wait for it to finish");
		}
		$handle = fopen($file, "r") or die("Couldn't open file $file".PHP_EOL);

		declare(ticks = 1);
		if ($handle) {
			$i = 0;
			$imported = 0;
			$prev_block = Block::current();
			$start_height = $prev_block['height'];
			while (!feof($handle)) {
				$line = fgets($handle);
				if(empty($line)) {
					continue;
				}
				$i++;
				if($i % 100 == 0) {
					echo "Importing block $i".PHP_EOL;
				}
				$bl = json_decode($line, true);
				if(!$bl) {
					die("Failed decoding block at height ".$bl['height'].PHP_EOL);
				}
				if($bl['height'] == $prev_block['height']+1) {
					$prev_block_id = $prev_block['id'];
					$block = Block::getFromArray($bl);
					$block->prevBlockId = $prev_block_id;
					$res = $block->add($error);
					if(!$res) {
						die("Failed importing block at height {$prev_block['height']}: $error".PHP_EOL);
					}
					$prev_block = Block::current();
					$imported++;
				}
			}
			Propagate::blockToAll('current');
			fclose($handle);
			echo "Successfully imported $imported blocks from height $start_height".PHP_EOL;
		}
	}

	static function clearPeers() {
		Peer::deleteAll();
		echo "Deleted peers database".PHP_EOL;
	}

	static function emptyMempool() {
		Transaction::empty_mempool();
		SmartContract::cleanState(Block::getHeight()+1);
	}

	static function update($argv) {
		global $_config;
		$branch = @trim($argv[2]);
		$force = @trim($argv[3]);
		if(empty($branch)) {
            $branch = GIT_BRANCH;
		}
        $currentVersion = BUILD_VERSION;
		echo "Checking node branch=$branch force=$force update current version = ".BUILD_VERSION.PHP_EOL;
		$maxPeerBuildNumber = Peer::getMaxBuildNumber();

        $check_url = "https://phpcoin.net/version.php?branch={branch}";
        $check_url=str_replace("{branch}",$branch,$check_url);
        $cmd= "curl -m 30 -H 'Cache-Control: no-cache, no-store' -s $check_url";
        $res = shell_exec($cmd);
        $version = intval($res);
        _log("AUTO_UPDATE: check url $check_url version=$version",4);

        $user = shell_exec("whoami");

//        if(trim($user)=="root" && $currentVersion >= 317) {
//            _log("AUTO_UPDATE: Run as root is deprecated");
//            return;
//        }

        _log("AUTO_UPDATE: call php util check_url=$check_url branch=$branch force=$force node version=$currentVersion git version=$version maxPeerBuildNumber=$maxPeerBuildNumber user=$user");
		if($version > $currentVersion || $maxPeerBuildNumber > $currentVersion || !empty($force)) {
			echo "There is new version: $version - updating node".PHP_EOL;
            _log("AUTO_UPDATE: Updating node");

            if(trim($user)=="root") {
                $cmd="cd ".ROOT." && git config --global --unset-all safe.directory ".ROOT;
                $res = shell_exec($cmd);
                _log("AUTO_UPDATE: cmd=$cmd res=$res",4);

                $cmd="cd ".ROOT." && git config --global --add safe.directory ".ROOT;
                $res = shell_exec($cmd);
                _log("AUTO_UPDATE: cmd=$cmd res=$res",4);

                $cmd = "crontab -l";
                $res = shell_exec($cmd);
                _log("AUTO_UPDATE: cron cmd=$cmd res=$res");

                $cmd="crontab -l | grep -v 'cd ".ROOT." && php cli/util.php update' | crontab -";
                $res = shell_exec($cmd);
                _log("AUTO_UPDATE: cron cmd=$cmd res=$res");

            }

            $cmd="git remote set-url origin https://git.phpcoin.net/node";
            $res = shell_exec($cmd);
            _log("AUTO_UPDATE: cmd=$cmd res=$res",4);

            $cmd="cd ".ROOT." && git config --unset-all safe.directory ".ROOT;
            $res = shell_exec($cmd);
            _log("AUTO_UPDATE: cmd=$cmd res=$res",4);

            $cmd="cd ".ROOT." && git config --add safe.directory ".ROOT;
            $res = shell_exec($cmd);
            _log("AUTO_UPDATE: cmd=$cmd res=$res",4);

            $cmd="cd ".ROOT." && git config core.fileMode false";
            $res = shell_exec($cmd);
            _log("AUTO_UPDATE: cmd=$cmd res=$res",4);

            $cmd="cd ".ROOT." && git status  2>&1";
            $res = shell_exec($cmd);
            _log("AUTO_UPDATE: cmd=$cmd res=$res",4);

            $cmd="cd ".ROOT." && git fetch  2>&1";
            $res = shell_exec($cmd);
            _log("AUTO_UPDATE: cmd=$cmd res=$res",4);

			$cmd="cd ".ROOT." && git restore .  2>&1";
			$res = shell_exec($cmd);
			_log("AUTO_UPDATE: cmd=$cmd res=$res",4);

			$cmd="cd ".ROOT." && git checkout -b $branch 2>&1";
			$res = shell_exec($cmd);
			_log("AUTO_UPDATE: cmd=$cmd res=$res",4);
//
//			$cmd="cd ".ROOT." && git reset --hard origin/$branch";
//			$res = shell_exec($cmd);
//			_log("AUTO_UPDATE: cmd=$cmd res=$res");

			$cmd="cd ".ROOT." && git pull origin $branch  2>&1";
			$res = shell_exec($cmd);
			_log("AUTO_UPDATE: cmd=$cmd res=$res",4);

            $cmd = "cd ".ROOT." && echo \"". CHAIN_ID ."\" 2>&1 > chain_id ";
            $res = shell_exec($cmd);
            _log("AUTO_UPDATE: cmd=$cmd res=$res", 5);

            _log("AUTO_UPDATE: Set node folder user permissions",4);

            $cmd="chown -R www-data:www-data ".ROOT ."/  2>&1";
            $res = shell_exec($cmd);
            _log("AUTO_UPDATE: cmd=$cmd res=$res",4);

            $cmd="chmod -R 755 ".ROOT ."/  2>&1";
            $res = shell_exec($cmd);
            _log("AUTO_UPDATE: cmd=$cmd res=$res",4);

            Util::cleanTmpFolder();

//			Util::recalculateMasternodes();

//			$cmd="cd ".ROOT." && chown -R www-data:www-data web";
//			$res = shell_exec($cmd);
//			_log("cmd=$cmd res=$res", 5);
			//$cmd="cd ".ROOT." && php cli/util.php download-apps";
			//$res = shell_exec($cmd);
			echo "Node updated".PHP_EOL;
            _log("AUTO_UPDATE: Node updated");
		} else {
			echo "There is no new version".PHP_EOL;
            _log("AUTO_UPDATE: No new version",2);
		}
		echo "Finished".PHP_EOL;
	}

	static function checkMasternode() {
		Masternode::checkMasternode();
	}

	static function resetMasternode() {
		Masternode::resetMasternode();
	}

	static function importPrivateKey($argv) {
		$privateKey = trim($argv[2]);
		if(empty($privateKey)) {
			echo "Missing private key".PHP_EOL;
			exit;
		}
		$private_key = coin2pem($privateKey, true);
		$pkey = openssl_pkey_get_private($private_key);
		if(!$pkey) {
			echo "Invalid private key $privateKey".PHP_EOL;
			exit;
		}
		$k = openssl_pkey_get_details($pkey);
		$public_key = pem2coin($k['key']);

		echo "phpcoin".PHP_EOL;
		echo $privateKey.PHP_EOL;
		echo $public_key.PHP_EOL;

	}

	static function masternodeSign($argv) {
		global $_config;
		$message = trim($argv[2]);
		if(empty($message)) {
			echo "Missing message".PHP_EOL;
			exit;
		}
		if(!Masternode::isLocalMasternode()) {
			echo "Local node is not masternode".PHP_EOL;
		}
		$signature = ec_sign($message, $_config['masternode_private_key']);
		echo $signature . PHP_EOL;
	}

	static function verify($argv) {
		$message = trim($argv[2]);
		$signature = trim($argv[3]);
		$key = trim($argv[4]);
		if(empty($message)) {
			echo "Missing message".PHP_EOL;
			exit;
		}
		if(empty($signature)) {
			echo "Missing signature".PHP_EOL;
			exit;
		}
		if(empty($key)) {
			echo "Missing public key or address".PHP_EOL;
			exit;
		}
		if(Account::valid($key)) {
			$address = $key;
			$publicKey = Account::publicKey($address);
		} else {
			$publicKey = $key;
		}
		$res = @ec_verify($message, $signature, $publicKey);
		if($res) {
			echo "Signature valid".PHP_EOL;
		} else {
			echo "Signature not valid".PHP_EOL;
		}
	}

    static function getMorePeers() {
        global $_config;
        _log("Sync: Util: get-more-peers", 3);
        $peers=Peer::getPeers();
        $peered = [];
        foreach ($peers as $peer) {
            $hostname = $peer['hostname'];
            $peered[$hostname]=true;
        }
        $cnt=count($peers);
        _log("Totally peered with " . $cnt." peers",3);

        $forker= new Forker();
        $t1 = microtime(true);
        $limit = PHP_INT_MAX;
        $new_peers = [];
        define("FORKED_PROCESS", getmypid());
        $info = Peer::getInfo();
        foreach ($peers as $ix => $peer) {
            if($ix > $limit) break;
            $forker->fork(function ($peer) use ($ix, $cnt, $peered,$info) {
                $hostname = $peer['hostname'];
                _log("Getting peers from $hostname [$ix / $cnt]",5);
                $url = $hostname."/peer.php?q=";
                $data = peer_post($url."getPeers", [], 30, $err, $info);
                if ($data === false) {
                    return [$peer, null, "Error getting peers from $hostname: $err"];
                }
                if(is_array($data) && count($data)==0) {
                    return [$peer, null, "No peers on $hostname"];
                }
                $new_peers = [];
                foreach ($data as $ix1 => $peer1) {
                    $hostname1 = $peer1['hostname'];
                    if ($peered[$hostname1]) {
                        continue;
                    } else {
                        $new_peers[]=$peer1;
                    }
                    $peered[$hostname1] = true;
                }
                _log("Found ".count($data)." peers on $hostname new=".count($new_peers),5);
                return [$peer, $new_peers, null];
            }, $peer);
        }
        $forker->on(function($data) use (&$new_peers) {
            _log("Received data ".json_encode($data),5);
            $peer = $data[0];
            $res = $data[1];
            $err = $data[2];
            if($res) {
                foreach($res as $item) {
                    $new_peers[$item['hostname']]=$item;
                }
            } else {
                Peer::blacklist($peer['id'], $err);
            }
        });
        $forker->exec();
        _log("Received total new peers ".json_encode($new_peers), 5);

        $discovered = 0;
        $added = 0;
        $forker= new Forker();
        foreach ($new_peers as $new_peer) {
            $hostname1 = $new_peer['hostname'];
            _log("Peer with new peer $hostname1", 3);
            if($hostname1 === $_config['hostname']) {
                _log("Peer is me - skip", 5);
                continue;
            }
            if(!Peer::validate($hostname1)) {
                _log("Peer with new peer ".$hostname1." - not valid",2);
                return;
            }
            $discovered++;
            $single = Peer::getSingle($new_peer['hostname'], $new_peer['ip']);
            $forker->fork(function($new_peer) use ($single, $info){
                global $_config;
                $hostname1 = $new_peer['hostname'];
                _log("Fork peer with new peer $hostname1", 3);
                if (!$single) {
                    $res = peer_post($new_peer['hostname']."/peer.php?q=peer", ["hostname" => $_config['hostname'], 'repeer'=>1], 30, $err, $info);
                    if($res !== false ){
                        _log("GMP: peered new peer ". $new_peer['hostname'], 3);
                        return true;
                    } else {
                        _log("Error peering new peer ".$new_peer['hostname']." err=$err",2);
                    }
                } else {
                    _log("New peer ".$new_peer['hostname']." already in our db",5);
                }
                return false;
            }, $new_peer);
        }
        $forker->on(function($res) use (&$added) {
            if($res) {
                $added++;
            }
            _log("Fork response $res", 5);
        });
        $forker->exec();

        $t2 = microtime(true);
        $diff=round($t2-$t1,2);
        _log("Get more peers: peers=$cnt discovered=$discovered added=$added Completed in $diff");
    }

    /**
     * @deprecated
     * @return void
     */
	static function getMorePeersOld() {

		global $_config;

		_log("Sync: Util: get-more-peers", 3);
		$peers=Peer::getPeers();
		$peered = [];

		foreach ($peers as $ix => $peer) {
			$hostname = $peer['hostname'];
			$peered[$hostname]=true;
		}

        _log("Totally peered with " . count($peered)." peers",3);

        $cnt=count($peers);
        $added = 0;
        $start = microtime(true);
		foreach ($peers as $ix => $peer) {
			$hostname = $peer['hostname'];
			$url = $hostname."/peer.php?q=";
            _log("Getting peers from $hostname [$ix / $cnt]",5);
			$data = peer_post($url."getPeers", [], 30, $err);
			if ($data === false) {
                _log("Error getting peers from $hostname: $err",2);
				Peer::blacklist($peer['id'], "Unresponsive");
				continue;
			}
			if(is_array($data) && count($data)==0) {
                _log("No peers on $hostname",2);
				Peer::blacklist($peer['id'], "No peers");
				continue;
			}

            _log("Found ".count($data)." peers on $hostname",5);

			$new_peers = [];
			foreach ($data as $ix1 => $peer1) {
				$hostname1 = $peer1['hostname'];
				// do not peer if we are already peered
				if ($peered[$hostname1]) {
					continue;
				} else {
					$new_peers[]=$peer1;
				}
				$peered[$hostname1] = true;
			}

            _log("Found ".count($data)." peers on $hostname new=".count($new_peers),5);

			foreach ($new_peers as $ix1 => $new_peer)  {
				if(!Peer::validate($new_peer['hostname'])) {
                    _log("Peer with new peer ".$new_peer['hostname']." - not valid",2);
					continue;
				}
				if ($new_peer['hostname'] == $_config['hostname']) {
                    _log("Peer with new peer ".$new_peer['hostname']." - is me",2);
					continue;
				}
				$single = Peer::getSingle($new_peer['hostname'], $new_peer['ip']);
				if (!$single) {
					$res = peer_post($new_peer['hostname']."/peer.php?q=peer", ["hostname" => $_config['hostname'], 'repeer'=>1], 30, $err);
					if($res !== false ){
						_log("GMP: peered new peer ". $new_peer['hostname']);
                        $added++;
					} else {
                        _log("Error peerign new peer ".$new_peer['hostname']." err=$err",2);
                    }
				} else {
                    _log("New peer ".$new_peer['hostname']." already in our db",5);
                }
			}

		}
        $end = microtime(true);
        $diff = $end - $start;
        _log("Added $added new peers in $diff");
	}

	static function setConfig($argv) {
		$config_name = $argv[2];
		$config_value = $argv[3];
		global $db;
		$db->setConfig($config_name, $config_value);
	}

	static function propagate($argv) {
		global $_config, $db;
		$message = trim($argv[2]);
		if(empty($message)) {
			echo "Message not specified".PHP_EOL;
			exit;
		}
		$private_key = $_config['masternode_private_key'];
		$public_key = $_config['masternode_public_key'];
		if(empty($private_key)) {
			echo "No masternode private key".PHP_EOL;
			exit;
		}
        $db->setConfig('propagate_msg', $message);

        $payload = [
            "message"=>$message,
            "type"=>"propagate",
            "limit"=>3,
            "maxTime"=>30,
            "maxHops"=>5,
            "internal"=>true,
            "add_cond"=>"",
            "onlyLatestVersion"=>true,
            "notifySent"=>true,
            "notifyReceived"=>true,
        ];

        $base = [
            "id"=>time().uniqid(),
            "origin"=>$_config['hostname'],
            "time"=>microtime(true),
            "public_key"=>$public_key,
            "payload"=>json_encode($payload),
        ];
        $signature = ec_sign(json_encode($base), $private_key);
        $envelope = $base;
        $envelope['signature']=$signature;
        $envelope['hops']=[];
        _log("PM2: created envelope ".json_encode($envelope));
        Propagate::propagateSocketEvent2("messageCreated", ['time'=>microtime(true)]);
        Propagate::message($envelope);
	}

	static function smartContractCompile($argv) {
		$file = $argv[2];
		if(empty($file)) {
			echo "Smart contract file or folder not specified".PHP_EOL;
			exit;
		}
		$phar_file = $argv[3];
		if(empty($phar_file)) {
			echo "Output phar file not specified".PHP_EOL;
			exit;
		}
		$res = SmartContract::compile($file, $phar_file, $error);
		if(!$res) {
			echo "Smart Contract can not be compiled to file: $error".PHP_EOL;
			exit;
		}
		$code = file_get_contents($phar_file);
		$code = base64_encode($code);
        $interface = SmartContractEngine::verifyCode($code, $error);
		if(!$interface) {
			echo "Smart Contract can not be verified: $error".PHP_EOL;
			exit;
		}
		echo "Created compiled smart contract file".PHP_EOL;
	}

	static function smartContractView($argv)
	{
		$sc_address = $argv[2];
		if(empty($sc_address)) {
			echo "Smart contract address not specified".PHP_EOL;
			exit;
		}
		$method = $argv[3];
		if(empty($method)) {
			echo "Smart contract view not specified".PHP_EOL;
			exit;
		}
        $namedAgs = process_cmdline_args($argv);
        $params=$namedAgs['params'];
        if(!empty($params)) {
            $params=SmartContractEngine::parseCmdLineArgs($params);
        }
		$res = SmartContractEngine::view($sc_address, $method, $params, $error);
		if($res === false) {
			echo "Error calling Smart Contract view: $error".PHP_EOL;
		}
		echo $res . PHP_EOL;
	}

	static function smartContractGet($argv) {
		$sc_address = $argv[2];
		if(empty($sc_address)) {
			echo "Smart contract address not specified".PHP_EOL;
			exit;
		}
		$property = $argv[3];
		if(empty($property)) {
			echo "Smart contract property not specified".PHP_EOL;
			exit;
		}
        $namedAgs = process_cmdline_args($argv);
        $key=$namedAgs['key'];
		$res = SmartContractEngine::get($sc_address, $property, $key, $error);
		if($res === false) {
			echo "Error getting Smart Contract property: $error".PHP_EOL;
		}
		echo $res . PHP_EOL;
	}

	static function propagateDapps() {
		Dapps::process(true);
	}

	static function downloadDapps($argv) {
		$dapps_id = $argv[2];
		Dapps::downloadDapps($dapps_id);
	}

    static function recalculateMasternodes() {
        global $db;
        _log("start recalculateMasternodes");
        $db->beginTransaction();
        try {

            $sql="select * from transactions t where t.type = :mncreate or t.type=:mnremove order by t.height, t.id";
            $txs = $db->run($sql, [":mncreate"=>TX_TYPE_MN_CREATE, ":mnremove"=>TX_TYPE_MN_REMOVE]);

            $calc_masternodes = [];
            foreach($txs as $tx) {
                if($tx['type']==TX_TYPE_MN_CREATE) {
                    if($tx['message']=="mncreate" || $tx['message']=="") {
                        $masternode['id'] = $tx['dst'];
                        $masternode['height'] = $tx['height'];
                        $masternode['collateral'] = $tx['val'];
                    } else if (Account::valid($tx['message'])) {
                        $masternode['id']=$tx['message'];
                        $masternode['height']=$tx['height'];
                        $masternode['collateral']=$tx['val'];
                    } else {
                        throw new Error("invalid tx ".$tx['id']);
                    }
                    $calc_masternodes[$masternode['id']] = $masternode;
                } else if ($tx['type']==TX_TYPE_MN_REMOVE) {
                    if($tx['message']=="mnremove" || $tx['message']=="") {
                        $masternode['id']=$tx['src'];
                    } else if (Account::valid($tx['message'])) {
                        $masternode['id']=$tx['message'];
                    } else {
                        throw new Error("invalid tx ".$tx['id']);
                    }
                    if(!isset($calc_masternodes[$masternode['id']])) {
                        throw new Error("can not find masternode " . $masternode['id']);
                    }
                    unset ($calc_masternodes[$masternode['id']]);
                }
            }

            foreach($calc_masternodes as &$mn) {
                $public_key = Account::publicKey($mn['id']);
                if(!$public_key) {
                    throw new Error("not found public key fro masternode ".$mn['id']);
                }
                $mn['public_key']=$public_key;
                $sql="select max(b.height) from blocks b where b.masternode = :mn_address";
                $height = $db->single($sql, [":mn_address"=>$mn['id']]);
                $mn['win_height']=$height;
                $mn['collateral']=floatval($mn['collateral']);
            }

            $sql = "select * from masternode";
            $mns = $db->run($sql);

            $calc_mn_count=count($calc_masternodes);
            $real_mn_count=count($mns);
            _log("calc_mn_count=$calc_mn_count real_mn_count=$real_mn_count");

            $mns_map = [];
            $recreate = false;
            unset($mn);
            foreach ($mns as $mn) {
                $id = $mn['id'];
                if(!isset($calc_masternodes[$id])) {
                    _log("missing masternode ".$id);
                    $recreate = true;
                    break;
                }
                $calc_masternode = $calc_masternodes[$id];
                if($calc_masternode['public_key']!=$mn['public_key']) {
                    _log("different public_key for masternode ".$id);
                    $recreate = true;
                    break;
                }
                if($calc_masternode['win_height']!=$mn['win_height']) {
                    _log("different win_height for masternode ".$id);
                    $recreate = true;
                    break;
                }
                if($calc_masternode['height']!=$mn['height']) {
                    _log("different height for masternode ".$id);
                    $recreate = true;
                    break;
                }
                if($calc_masternode['collateral']!=$mn['collateral']) {
                    _log("different collateral for masternode ".$id);
                    $recreate = true;
                    break;
                }
                $mns_map[$id]=$mn;
            }

            foreach ($calc_masternodes as $calc_masternode) {
                $id = $calc_masternode['id'];
                if(!isset($mns_map[$id])) {
                    _log("missing masternode ".$id);
                    $recreate = true;
                    break;
                }
                $mn = $mns_map[$id];
                if($calc_masternode['public_key']!=$mn['public_key']) {
                    _log("different public_key for masternode ".$id);
                    $recreate = true;
                    break;
                }
                if($calc_masternode['win_height']!=$mn['win_height']) {
                    _log("different win_height for masternode ".$id);
                    $recreate = true;
                    break;
                }
                if($calc_masternode['height']!=$mn['height']) {
                    _log("different height for masternode ".$id);
                    $recreate = true;
                    break;
                }
                if($calc_masternode['collateral']!=$mn['collateral']) {
                    _log("different collateral for masternode ".$id);
                    $recreate = true;
                    break;
                }
            }

            if($recreate) {
                _log("Need to recreate masternodes");
                $db->exec("delete from masternode");
                foreach ($calc_masternodes as $calc_mn) {
                    $mn1=new Masternode();
                    $mn1->public_key=$calc_mn['public_key'];
                    $mn1->height=$calc_mn['height'];
                    $mn1->win_height=$calc_mn['win_height'];
                    $mn1->id=$calc_mn['id'];
                    $mn1->verified=0;
                    $mn1->collateral=$calc_mn['collateral'];
                    $res = $mn1->add();
                    if(!$res) {
                        throw new Error("Can not add masternode ".$calc_mn['id']);
                    }
                }
                _log("Masternodes recreated");
            } else {
                _log("No need to recreate masternodes");
            }

            _log("finish recalculateMasternodes");
            $db->commit();
        } catch (Error $e) {
            _log("error recalculateMasternodes ". $e->getMessage());
            $db->rollBack();
        }
    }

	static function recalculateMasternodesOld() {
		global $db;
		_log("start recalculateMasternodes");
		$db->beginTransaction();

		try {
			$sql="select count(*) from (
                    select mn_created.mn_address, mn_created.dst, case when mn_created.mn_address = mn_created.dst then 'hot' else 'cold' end as mn_type,
                    count(mn_created.id) as created,
                    (select count(tr.id) from transactions tr where tr.src = mn_created.dst and tr.type = 3) as removed
                    from (
                        select case when t.message != 'mncreate' and t.message != '' then t.message else t.dst end as mn_address, t.id,
                        t.dst, t.height
                        from transactions t where t.type=2
                        ) as mn_created
                    group by mn_created.mn_address
                    having created - removed > 0
                ) as mnc";

			$calc_mn_count = $db->single($sql);
			$sql="select count(*) from masternode";
			$real_mn_count = $db->single($sql);

			_log("calc_mn_count=$calc_mn_count real_mn_count=$real_mn_count");

			if($calc_mn_count != $real_mn_count) {
				_log("Different number of masternodes - recreate");
				$db->exec("delete from masternode");
				$db->exec("insert into masternode (public_key,height,win_height, id, verified, collateral)
                            select (select a.public_key from accounts a where a.id = active_mn.mn_address) as public_key,
                            active_mn.height, (select max(b.height) from blocks b where b.masternode = active_mn.mn_address)  as win_height,
                            active_mn.mn_address as id, 0 as verified,
                            (select tc.val from transactions tc where tc.dst = active_mn.dst and tc.type = 2 and tc.height = active_mn.height) as collateral
                            from (
                                select mn_created.mn_address, max(mn_created.height) as height, mn_created.dst, count(mn_created.id) as created,
                                (select count(tr.id) from transactions tr where tr.src = mn_created.dst and tr.type = 3) as removed
                                from (
                                    select case when t.message != 'mncreate' and t.message != '' then t.message else t.dst end as mn_address,
                                    t.id, t.dst, t.height
                                    from transactions t where t.type = 2
                                ) as mn_created
                                group by mn_created.mn_address
                                having created - removed > 0
                            ) as active_mn");
			} else {
				$sql="select calc_mn.*, m.* from (
                        select active_mn.height, (select max(b.height) from blocks b where b.masternode = active_mn.mn_address) as win_height,
                        active_mn.mn_address as id,
                        (select tc.val from transactions tc where tc.dst = active_mn.dst and tc.type = 2 and tc.height = active_mn.height) as collateral
                        from (
                            select mn_created.mn_address, max(mn_created.height) as height, mn_created.dst, count(mn_created.id) as created,
                            (select count(tr.id) from transactions tr where tr.src = mn_created.dst and tr.type = 3) as removed
                            from (
                                select case when t.message != 'mncreate' and t.message != '' then t.message else t.dst end as mn_address,
                                t.id, t.dst, t.height
                                from transactions t where t.type = 2
                            ) as mn_created
                            group by mn_created.mn_address
                            having created - removed > 0
                        ) as active_mn
                    ) as calc_mn
                    left join masternode m on (calc_mn.id = m.id)
                    where calc_mn.height <> m.height
                       or calc_mn.win_height <> m.win_height
                       or calc_mn.collateral <> m.collateral";
				$rows = $db->run($sql);
				$diff_rows = count($rows);
				_log("Check different rows = $diff_rows");
				if($diff_rows > 0) {
					$sql="update (
                            select active_mn.height,
                            (select max(b.height) from blocks b where b.masternode = active_mn.mn_address)  as win_height,
                            active_mn.mn_address as id,
                            (select tc.val from transactions tc where tc.dst = active_mn.dst and tc.type = 2 and tc.height = active_mn.height) as collateral
                            from (
                                select mn_created.mn_address, max(mn_created.height) as height, mn_created.dst, count(mn_created.id) as created,
                                (select count(tr.id) from transactions tr where tr.src = mn_created.dst and tr.type = 3) as removed
                                from (
                                    select case when t.message != 'mncreate' and t.message != '' then t.message else t.dst end as mn_address,
                                    t.id, t.dst, t.height
                                    from transactions t where t.type = 2
                                ) as mn_created
                                group by mn_created.mn_address
                                having created - removed > 0
                            ) as active_mn
                        ) as calc_mn
                        left join masternode m on (calc_mn.id = m.id)
                            set m.height = calc_mn.height, m.win_height = calc_mn.win_height, m.collateral = calc_mn.collateral
                            where calc_mn.height <> m.height
                       or calc_mn.win_height <> m.win_height
                       or calc_mn.collateral <> m.collateral";
					$db->run($sql);
					_log("Updated masternodes");
				} else {
					_log("No need to update masternodes");
				}
			}
			_log("finish recalculateMasternodes");
			$db->commit();
		} catch (Exception $e) {
			_log("error recalculateMasternodes");
			$db->rollBack();
		}
	}

	static function propagateApps($argv) {
		require_once ROOT."/web/apps/apps.inc.php";
		$peer = $argv[2];
		if(empty($peer)) {
			echo "Peer not specified".PHP_EOL;
			exit;
		}
		if(!isRepoServer()) {
			echo "Only repo server can propagate apps".PHP_EOL;
			exit;
		}
		$appsHashFile = Nodeutil::getAppsHashFile();
		$appsHash = file_get_contents($appsHashFile);
		Propagate::appsToPeer($peer, $appsHash);
	}

	static function peerCall($argv) {
		$peer = $argv[2];
		if(empty($peer)) {
			echo "Empty peer".PHP_EOL;
			exit;
		}
		$method = $argv[3];
		if(empty($method)) {
			echo "Empty method".PHP_EOL;
			exit;
		}
		$data = null;
		if(isset($argv[4])) {
			$data = json_decode($argv[4], true);
		}
		$url = $peer . "/peer.php?q=$method";
		$res = peer_post($url, $data, 30, $err, null, $curl_info);
		if($res) {
            echo "Response:".PHP_EOL;
			print_r($res);
            echo PHP_EOL;
            echo "Curl info:".PHP_EOL;
            print_r($curl_info);
		} else {
			api_err($err);
		}
	}

	static function refreshPeers() {
        _log("Refresh peers start", 5);
        $t1=microtime(true);
		$peers = Peer::findPeers(false, false);
		$cnt = count($peers);

        $forker=new Forker();
        $refreshed = 0;
        define("FORKED_PROCESS", getmypid());
        $info = Peer::getInfo();
		foreach($peers as $peer) {
            $forker->fork(function($peer) use ($info){
                _log("Ping peer ".$peer['hostname'], 5);
                $url = $peer['hostname']. "/peer.php?q=ping";
                $res = peer_post($url, [], 30, $err, $info);
                _log("Ping ".$peer['hostname']." response=$res err=$err", 5);
                if($res !== "pong") {
                    return [false, $peer['id']];
                }
                return [true, $peer['id']];
            }, $peer);
		}
        $forker->on(function($res) use (&$refreshed) {
            $success = $res[0];
            $peer_id = $res[1];
            if($success) {
                $refreshed++;
                Peer::updatePing($peer_id);
            } else {
                Peer::blacklist($peer_id, "Unresponsive");
            }
        });

        $forker->exec();

        $t2=microtime(true);
        $diff = round($t2-$t1,2);
        _log("Refresh peers=$cnt refreshed=$refreshed completed in $diff");
	}

	static function checkAccounts() {
		global $db, $argv;
		Config::setSync(1);

		try {
			_log("start check accounts");
			$db->beginTransaction();

			$sql="select count(*) from (
			 select distinct t.src as id
			 from transactions t
			 where t.src is not null
			 union
			 select distinct t.dst as id
			 from transactions t
			 where t.dst is not null) as ids";
			$calc_acc_cnt = $db->single($sql);

			$sql="select count(*) from accounts";
			$real_acc_cnt = $db->single($sql);

			$sql= "select sum(val) from (
		 select sum((t.val+t.fee)*(-1)) as val
		 from transactions t
		 where t.src is not null
		 union
		 select sum(t.val) as val
		 from transactions t
		 where t.dst is not null) as vals";

			$calc_acc_sum = $db->single($sql);

			$sql= "select sum(a.balance) from accounts a";
			$real_acc_sum = $db->single($sql);

			_log("calc_acc_cnt=$calc_acc_cnt real_acc_cnt=$real_acc_cnt calc_acc_sum=$calc_acc_sum real_acc_sum=$real_acc_sum");

			if($calc_acc_cnt <>  $real_acc_cnt || (!empty($argv) && in_array("--force", $argv))) {
				_log("Accounts rows are different");
				_log("delete accounts");
//				$db->exec("lock tables transactions t write, transactions ts write, blocks b write, accounts write");
				$sql="delete from accounts";
				$db->exec($sql);
				$sql="insert into accounts (id, public_key, block, balance, height)
			select id, case when public_key is null then '' else public_key end, block, balance, height
			from (
			         select ids.id,
		                (select case when tp.public_key is null then '' else tp.public_key end from transactions tp where tp.src = ids.id limit 1) as public_key,
		                (select b.id from blocks b where b.height = min(min_height)) as block,
		                sum(val) as balance,
		                max(max_height) as height
		         from (
		                  select t.dst as id, max(t.height) as max_height, sum(t.val) as val, min(t.height) as min_height
		                  from transactions t
		                  where t.dst is not null
		                  group by t.dst
		                  union
		                  select t.src as id, max(t.height) as max_height, sum((t.val + t.fee)*(-1)) as val, min(t.height) as min_height
		                  from transactions t
		                  where t.src is not null
		                  group by t.src
		              ) as ids
		         group by ids.id
			     ) as calc";
				$db->exec($sql);
//				$db->exec("unlock tables");
			}

			_log("Check differences");
			$sql="select calc.*, a.*
				from (
		         select ids.id,
		                (select case when tp.public_key is null then '' else tp.public_key end from transactions tp where tp.src = ids.id limit 1) as public_key,
		                (select b.id from blocks b where b.height = min(min_height)) as block,
		                sum(val) as balance,
		                max(max_height) as height
		         from (
		                  select t.dst as id, max(t.height) as max_height, sum(t.val) as val, min(t.height) as min_height
		                  from transactions t
		                  where t.dst is not null
		                  group by t.dst
		                  union
		                  select t.src as id, max(t.height) as max_height, sum((t.val + t.fee)*(-1)) as val, min(t.height) as min_height
		                  from transactions t
		                  where t.src is not null
		                  group by t.src
		              ) as ids
		         group by ids.id
				     ) as calc
				         left join accounts a on (calc.id = a.id)
				where calc.public_key <> a.public_key
				   or calc.block <> a.block
				   or calc.balance <> a.balance
				   or calc.height <> a.height";
			$res = $db->run($sql);
			$diff_rows = count($res);
			_log("Found $diff_rows different rows");

			if($diff_rows > 0) {
				_log("Update accounts table");
				$sql="update (
			         select ids.id,
			                (select case when tp.public_key is null then '' else tp.public_key end from transactions tp where tp.src = ids.id limit 1) as public_key,
			                (select b.id from blocks b where b.height = min(min_height)) as block,
			                sum(val) as balance,
			                max(max_height) as height
			         from (
			                  select t.dst as id, max(t.height) as max_height, sum(t.val) as val, min(t.height) as min_height
			                  from transactions t
			                  where t.dst is not null
			                  group by t.dst
			                  union
			                  select t.src as id, max(t.height) as max_height, sum((t.val + t.fee)*(-1)) as val, min(t.height) as min_height
			                  from transactions t
			                  where t.src is not null
			                  group by t.src
			              ) as ids
			         group by ids.id
			) as calc
			    left join accounts a on (calc.id = a.id)
			set a.public_key = case when calc.public_key is null then '' else calc.public_key end, a.block = calc.block, a.balance = calc.balance, a.height = calc.height
			where calc.public_key <> a.public_key
			   or calc.block <> a.block
			   or calc.balance <> a.balance
			   or calc.height <> a.height";
				$res=$db->run($sql);
				_log("Accounts updated res=$res");
			} else {
				_log("No need to update accounts");
			}

            //check generators without public_key
            $sql= "select gen.generator, a.public_key,
                   (select distinct tp.public_key
                    from transactions tp where tp.dst = gen.generator and tp.type = 0 and (tp.message = '' or tp.message = 'generator' or tp.message = 'nodeminer')
                                           and exists (select 1 from blocks b where b.height = tp.height and b.generator = tp.dst)) as fuund_public_key
            from (select distinct b.generator
            from blocks b
            where b.generator is not null) as gen
            left join accounts a on (gen.generator = a.id)
            having a.public_key = '' and a.public_key != fuund_public_key;";
            $res = $db->run($sql);
            $diff_rows = count($res);
            _log("Check generators public keys - found $diff_rows diffs");
            if($diff_rows > 0) {
                $sql="update(
                select gen.generator, a.public_key,
                       (select distinct tp.public_key
                        from transactions tp where tp.dst = gen.generator and tp.type = 0 and (tp.message = '' or tp.message = 'generator' or tp.message = 'nodeminer')
                                               and exists (select 1 from blocks b where b.height = tp.height and b.generator = tp.dst)) as fuund_public_key
                from (select distinct b.generator
                      from blocks b
                      where b.generator is not null) as gen
                         left join accounts a on (gen.generator = a.id)
                having a.public_key = '' and a.public_key != fuund_public_key) as calc
                    left join accounts a on (calc.generator = a.id)
                set a.public_key = calc.fuund_public_key
                where a.public_key = '' and a.public_key != calc.fuund_public_key;";
                $res=$db->run($sql);
                _log("Generators accounts updated res=$res");
            }

			$db->commit();
			Config::setSync(0);
		} catch (Exception $e) {
			_log("Error checking accounts ".$e->getMessage());
			$db->rollBack();
			Config::setSync(0);
		}



//		$sql="update (
//	         select dst_txs.id,
//	                src_txs.public_key,
//	                b.id                                                                                as block,
//	                dst_txs.balance + src_txs.balance                                                   as balance,
//	                if(src_txs.max_height > dst_txs.max_height, src_txs.max_height, dst_txs.max_height) as height
//	         from (select t.dst         as id,
//	                      null          as public_key,
//	                      min(t.height) as min_height,
//	                      sum(t.val)    as balance,
//	                      max(height)   as max_height
//	               from transactions t
//	               where t.dst is not null
//	               group by t.dst) as dst_txs
//	                  left join (select t.src                       as id,
//	                                    max(t.public_key)           as public_key,
//	                                    min(t.height)               as min_height,
//	                                    sum((t.val + t.fee) * (-1)) as balance,
//	                                    max(height)                 as max_height
//	                             from transactions t
//	                             where t.src is not null
//	                             group by t.src) as src_txs on (src_txs.id = dst_txs.id)
//	                  left join blocks b on (b.height = dst_txs.min_height)
//	     ) as calc
//	         left join accounts a on (calc.id = a.id)
//	set a.public_key = calc.public_key, a.block = calc.block, a.balance = calc.balance, a.height = calc.height
//	where calc.public_key <> a.public_key
//	   or calc.block <> a.block
//	   or calc.balance <> a.balance
//	   or calc.height <> a.height;";
//		Config::setSync(0);
//		$res = $db->run($sql);
	}

//	static function emptyMasternodes() {
//		global $db;
//		$sql="delete from masternode";
//		$db->run($sql);
//	}

    static function runJobs() {
        Cron::run();
    }

    static function discoverPeers() {
        Nodeutil::discoverPeers();
    }

    static function initPeers() {
        Nodeutil::initPeers();
    }

    static function syncScState($argv) {
        $node = $argv[2];
        if(empty($node)) {
            api_err("Missing node");
        }
        _log("Sync sc state with peer $node");

        synchronized("sync-sc-state", function() use ($node) {
            global $db;

            $db->beginTransaction();

            $sql="delete from smart_contract_state";
            $db->run($sql);
            $url=$node."/api.php?q=getScState";
            $res = url_get($url);
            $res = json_decode($res, true);
            if($res!==false) {
                $top_height = Block::getHeight();
                $data=$res['data'];
                foreach ($data as $row) {
                    $sc_address=$row['sc_address'];
                    $variable=$row['variable'];
                    $var_key=$row['var_key'];
                    $var_value=$row['var_value'];
                    $height=$row['height'];
                    if($height > $top_height) {
                        break;
                    }
                    _log("Syncing sc state at height $height");
                    $sql="replace into smart_contract_state set sc_address = ?, variable =?, var_key=?, var_value=?, height = ?";
                    $db->run($sql, [$sc_address,$variable,$var_key,$var_value,$height], false);
                }
            }
            _log("Sync sc state finished");
            $db->commit();
        });


    }

    static function checkPeerStatus() {
        $peers = Peer::getAll();
        $forker = new Forker();
        define("FORKED_PROCESS", getmypid());
        $status=[];
        $peerInfo = Peer::getInfo();
        foreach ($peers as $peer) {
            $forker->fork(function ($peer) use ($peerInfo) {
                $url = $peer['hostname'] . "/peer.php?q=checkMyPeer";
                $res = peer_post($url,[], 5, $err, $peerInfo);
                return ["peer"=>$peer, "res"=>$res, "error"=>$err];
            }, $peer);
        }
        $forker->on(function ($res) use (&$status){
            $peer=$res['peer'];
            if($peer) {
                if($peer['blacklisted']>time()) {
                    @$status['blacklisted']++;
                    @$status['blacklist_reasons'][$peer['blacklist_reason']]++;
                } else {
                    if($peer['ping']>time() - Peer::PEER_PING_MAX_MINUTES * 60) {
                        @$status['live']++;
                    } else {
                        @$status['active']++;
                    }
                }
            } else {
                @$status['failed']++;
            }
        });
        $forker->exec();
        _log("peer status total=".count($peers)." failed=" . $status['failed'] . " blacklisted=".$status['blacklisted']
            ." live=".$status['live']. " active=".$status['active']);
        if(isset($status['blacklist_reasons'])) {
            _log("blacklist reasons: ".json_encode($status['blacklist_reasons']));
        }
    }

    static function checkBlocks()
    {
        $res = NodeSync::checkBlocks();
        if (!$res) {
            _log("Block database is invalid");
            Config::setVal("blockchain_invalid", 1);
        }
    }

    static function compareCheckPoints() {
        $res = NodeSync::compareCheckPoints();
        if(!$res) {
            _log("Blockchain is invalid - checkpoints are not correct");
            Config::setVal("blockchain_invalid", 1);
        }
    }

    static function recheckLastBlocks() {
        NodeSync::recheckLastBlocks();
    }

    static function cleanTmpFolder() {
        $cmd = "rm -rf ".ROOT."/tmp/*";
        $res = shell_exec($cmd);
    }

    static function checkCron(){
        $res = Nodeutil::psAux(ROOT . "/cli/cron.php");
        _log("check cron res=".json_encode($res), 3);
        if($res === false) {
            _log("ps uax error",2);
        } else {
            $start = false;
            if($res === null) {
                _log("ps uax not found",3);
                $start = true;
            } else {
                if(count($res)==1) {
                    $arr = preg_split("/\s+/", $res[0]);
                    $pid = $arr[1];
                    $mypid=getmypid();
                    _log("pid=$pid mypid=$mypid", 2);
                    if($pid == $mypid) {
                        $start = true;
                    }
                }
            }
            if($start) {
                _log("start cron", 2);
                $cmd = "(sleep 60 && php ".ROOT."/cli/cron.php)";
                Nodeutil::runSingleProcess($cmd);
            }
        }
    }
}
