<?php

class Mempool
{

	static function deleteOldMempool() {
		global $db;
		$height = Block::getHeight();
		$height = $height - 60;
		$db->run("DELETE FROM `mempool` WHERE height < :height", [":height"=>$height]);
	}

	static function getForRebroadcast($height) {
		global $db;
		$r = $db->run(
			"SELECT id FROM mempool WHERE height<=:current and peer='local' order by `height` asc LIMIT 20",
			[":current" => $height]
		);
		return $r;
	}

	static function updateMempool($id, $height) {
		global $db;
		$db->run(
			"UPDATE mempool SET height=:current WHERE id=:id",
			[":id" => $id, ":current" => $height]
		);
	}

	static function getForgotten($forgotten) {
		global $db;
		$r = $db->run(
			"SELECT id FROM mempool WHERE height<:forgotten ORDER by val, ".DB::random()." LIMIT 10",
			[":forgotten" => $forgotten]
		);
		return $r;
	}

	static function getSourceMempoolBalance($id) {
		global $db;
		$mem = $db->single("SELECT SUM(val+fee) FROM mempool WHERE src=:id", [":id" => $id]);
		return $mem;
	}

	public static function mempoolBalance($id, $exceptTxid = null)
	{
		global $db;
        $params = [":id1" => $id,":id2" => $id,":id3" => $id];
        $sql="SELECT SUM(case when src=:id1 then -(val+fee) else (val+fee) end) FROM mempool WHERE (src=:id2 or dst=:id3)";
        if(!empty($exceptTxid)) {
            $sql.=" and id != :exceptTxid";
            $params[":exceptTxid"]=$exceptTxid;
        }
		$mem = $db->single($sql, $params);
		return num($mem);
	}

	static function getSize() {
		global $db;
		$res = $db->single("SELECT COUNT(1) FROM mempool");
		return $res;
	}

	static function existsTx($hash) {
		global $db;
		$res = $db->single("SELECT COUNT(1) FROM mempool WHERE id=:id", [":id" => $hash]);
		return $res;
	}

	static function getSourceTxCount($src) {
		global $db;
		$res = $db->single("SELECT COUNT(1) FROM mempool WHERE src=:src", [":src" => $src]);
		return $res;
	}

	static function getPeerTxCount($ip) {
		global $db;
		$res = $db->single("SELECT COUNT(1) FROM mempool WHERE peer=:peer", [":peer" => $ip]);
		return $res;
	}

	static function deleteToeight($limit) {
		global $db;
		$db->run("DELETE FROM mempool WHERE height<:limit", [":limit" => $limit]);
	}

	public static function empty_mempool()
	{
		global $db;
		$db->run("DELETE FROM mempool");
	}

	public static function getTxs($height, $max) {
		global $db;
		// only get the transactions that are not locked with a future height
//		$r = $db->run(
//			"SELECT * FROM mempool WHERE height<=:height ORDER by val/fee DESC LIMIT :max",
//			[":height" => $height, ":max" => $max + 50]
//		);
		$r = $db->run(
			"SELECT * FROM mempool WHERE height<=:height ORDER by height, date, val DESC LIMIT :max",
			[":height" => $height, ":max" => $max + 50]
		);
		return $r;
	}

	public static function delete($id) {
		global $db;
		$db->run("DELETE FROM mempool WHERE id=:id", [":id" => $id]);
	}

	public static function getById($id) {
		global $db;
		$r = $db->row("SELECT * FROM mempool WHERE id=:id", [":id" => $id]);
		return $r;
	}

	public static function getByDstAndType($dst, $type) {
		global $db;
		$sql="select count(1) from mempool where dst=:dst and type=:type";
		return $db->single($sql, [":dst"=>$dst, ":type"=>$type]);
	}

	public static function getBySrcAndType($src, $type) {
		global $db;
		$sql="select count(1) from mempool where src=:src and type=:type";
		return $db->single($sql, [":src"=>$src, ":type"=>$type]);
	}

	public static function getAll() {
		global $db;
		$sql = "select * from mempool ORDER by height, date, val DESC";
		return $db->run($sql);
	}

    static function checkMempoolBalance(Transaction $transaction, &$error) {
        $mempool_txs = Transaction::mempool(Block::max_transactions(), false);
        $transactions = [];
        foreach ($mempool_txs as $mempool_tx) {
            $mempool_tx = Transaction::getFromArray($mempool_tx);
            $transactions[$mempool_tx->id]=$mempool_tx;
        }
        $transactions[$transaction->id]=$transaction;
        ksort($transactions);

        try {

            $balances = [];

            foreach ($transactions as $transaction) {
                $src = $transaction->src;
                $dst = $transaction->dst;
                if(!isset($balances[$src])) $balances[$src]=floatval(Account::getBalance($src));
                if(!isset($balances[$dst])) $balances[$dst]=floatval(Account::getBalance($dst));
                $type = $transaction->type;
                if($type == TX_TYPE_REWARD) {
                    throw new Error("Invalid transaction in mempool");
                } else if ($type == TX_TYPE_SEND || $type == TX_TYPE_MN_CREATE || $type == TX_TYPE_MN_REMOVE || $type == TX_TYPE_SC_CREATE
                    || $type == TX_TYPE_SC_EXEC || $type == TX_TYPE_SC_SEND) {
                    $balances[$src] -= $transaction->val + $transaction->fee;
                    $balances[$dst] += $transaction->val;
                } else if ($type == TX_TYPE_FEE) {
                    $balances[$dst] += $transaction->val;
                } else if ($type == TX_TYPE_BURN) {
                    $balances[$src] -= $transaction->val + $transaction->fee;
                }
                foreach ($balances as $address => $balance) {
                    if($balance <0 ) {
                        throw new Exception("Invalid future balance for address $address = $balance");
                    }
                }
            }

            return true;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            return false;
        }


    }

}
