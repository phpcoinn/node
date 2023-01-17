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
		$r1 = $db->run(
			"SELECT id FROM mempool WHERE height<:forgotten ORDER by val DESC LIMIT 10",
			[":forgotten" => $forgotten]
		);
		$r2 = $db->run(
			"SELECT id FROM mempool WHERE height<:forgotten ORDER by ".DB::random()." LIMIT 10",
			[":forgotten" => $forgotten]
		);
		$r=array_merge($r1, $r2);
		return $r;
	}

	static function getSourceMempoolBalance($id) {
		global $db;
		$mem = $db->single("SELECT SUM(val+fee) FROM mempool WHERE src=:id", [":id" => $id]);
		return $mem;
	}

	public static function mempoolBalance($id)
	{
		global $db;
		if($db->isSqlite()) {
			$mem = $db->single("SELECT SUM(case when src=:id1 then -(val+fee) else (val+fee) end) FROM mempool WHERE src=:id2 or dst=:id3", [":id1" => $id,":id2" => $id,":id3" => $id]);
		} else {
			$mem = $db->single("SELECT SUM(if(src=:id1, -(val+fee), (val+fee))) FROM mempool WHERE src=:id2 or dst=:id3", [":id1" => $id,":id2" => $id,":id3" => $id]);
		}
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

}
