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
		$no = intval($argv[2]);
		if(empty($no)) {
			$no = 1;
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
		var_dump(Block::current());
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
	 * http://35.190.160.142   active
	 * ...
	 * http://php.master.hashpi.com    active
	 */
	static function peers() {
		$r = Peer::getAll();
		foreach ($r as $x) {
			$status = "active";
			if ($x['reserve'] == 1) {
				$status = "reserve";
			}
			echo "$x[hostname]\t$status\n";
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
		echo "Height:\t\t".$res['height']."\n";
		echo "Hash:\t\t".$res['hash']."\n\n";
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

		$prev_reward = 0;
		$total_supply = 0;

		$start_block = 1;
		$start_time = GENESIS_TIME;

		if($real) {
			$block = Block::current(true);
			$start_block = $block->height + 1;
			$start_time = $block->date;
			$total_supply = Account::getCirculation();
		}

		for($i=$start_block;$i<=PHP_INT_MAX;$i++) {
			$reward = Block::reward($i);
			$elapsed = ($i-$start_block) * BLOCK_TIME;
			$time = $start_time + $elapsed;
			$total_supply += $reward['total'];
			$days = $elapsed / 60 / 60 / 24;
			if($reward['key'] != $prev_reward) {
				echo str_pad($reward['phase'], 10);
				echo str_pad($i, 10);
				echo str_pad($reward['total'], 10);
				echo str_pad($reward['miner'], 10);
				echo str_pad($reward['generator'], 10);
				echo str_pad($reward['masternode'], 10);
				echo str_pad($reward['pos'], 10);
				echo str_pad($elapsed, 24);
				echo str_pad(round($days,2), 10);
				echo str_pad(date("Y-m-d H:i:s",$time), 24);
				echo str_pad($total_supply, 10);
				echo PHP_EOL;
			}
			if($reward['total']==0) {
				break;
			}
			$prev_reward = $reward['key'];
		}
	}

	static function downloadApps() {
		Nodeutil::downloadApps();
	}

	static function verifyBlocks($argv) {
		$range = $argv[2];
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
		$list = [];
		echo "Exporting blockchain to file: " . $file.PHP_EOL;
		$fp = fopen($file, "w");
		for($i=1;$i<=$height;$i++) {
			$block = Block::export("",$i);
			$list[]=$block;
			if($i % 100 == 0) {
				echo "Exporting block $i".PHP_EOL;
			}
			fwrite($fp, json_encode($block).PHP_EOL);
		}
		fclose($fp);
		echo "Export finished. File: $file".PHP_EOL;
	}

	static function exportdb($argv) {
		$file = $argv[2];
		if(empty($file)) {
			$file = getcwd() . "/blockchain.sql";
		}
		global $db;
		$db_name = $db->single('select database()');
		echo "Exporting database...".PHP_EOL;
		$cmd = "mysqldump --single-transaction --compatible=ansi --no-tablespaces $db_name accounts blocks transactions masternode > $file";
		shell_exec($cmd);
		echo "Database exported".PHP_EOL;
	}

	static function importdb($argv) {
		$file = trim($argv[2]);
		if(isset($argv[3])) {
			$options = $argv[3];
		}
		if(empty($file)) {
			die("Missing argument <file>".PHP_EOL."Command: importdb <file>".PHP_EOL);
		}
		if(!file_exists($file)) {
			die("Can not found file: $file".PHP_EOL);
		}
		$lockFile = Nodeutil::getSyncFile();
		if (file_exists($lockFile)) {
			die("Sync running. Wait for it to finish");
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
		$lockFile = Nodeutil::getSyncFile();
		if (file_exists($lockFile)) {
			die("Sync running. Wait for it to finish");
		}
		$handle = fopen($file, "r") or die("Couldn't open file $file".PHP_EOL);

		declare(ticks = 1);
		pcntl_signal(SIGINT, function () use ($lockFile) {
			@unlink($lockFile);
			exit;
		});
		if ($handle) {
			$i = 0;
			$imported = 0;
			@touch($lockFile);
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
					@unlink($lockFile);
					die("Failed decoding block at height ".$bl['height'].PHP_EOL);
				}
				if($bl['height'] == $prev_block['height']+1) {
					$prev_block_id = $prev_block['id'];
					$block = Block::getFromArray($bl);
					$block->prevBlockId = $prev_block_id;
					$res = $block->add(!$verify);
					if(!$res) {
						@unlink($lockFile);
						die("Failed importing block at height $prev_block".PHP_EOL);
					}
					$prev_block = Block::current();
					$imported++;
				}
			}
			fclose($handle);
			@unlink($lockFile);
			echo "Successfully imported $imported blocks from height $start_height".PHP_EOL;
		}
	}

	static function clearPeers() {
		Peer::deleteAll();
		echo "Deleted peers database".PHP_EOL;
	}

	static function emptyMempool() {
		Transaction::empty_mempool();
	}

	static function update() {
		$currentVersion = BUILD_VERSION;
		echo "Checking node update current version = ".BUILD_VERSION.PHP_EOL;
		$cmd= "curl -s https://raw.githubusercontent.com/phpcoinn/node/main/include/coinspec.inc.php | grep BUILD_VERSION";
		$res = shell_exec($cmd);
		$arr= explode(" ", $res);
		$version = $arr[3];
		$version = str_replace(";", "", $version);
		$version = intval($version);
		if($version > $currentVersion) {
			echo "There is new version: $version - updating node".PHP_EOL;
			$cmd="cd ".ROOT." && git pull origin main";
			$res = shell_exec($cmd);
			$cmd="cd ".ROOT." && php cli/util.php download-apps";
			$res = shell_exec($cmd);
			echo "Node updated".PHP_EOL;
		} else {
			echo "There is no new version".PHP_EOL;
		}
		echo "Finished".PHP_EOL;
	}

}
