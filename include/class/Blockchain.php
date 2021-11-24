<?php


class Blockchain
{

	static function getHashRate($blocks) {
		$blockCount = Block::getHeight();
		if( $blockCount < $blocks) {
			return 0;
		}
		$latestBlock = Block::getAtHeight($blockCount);
		$prev10block = Block::getAtHeight($blockCount - $blocks);
		$elapsed = $latestBlock['date'] - $prev10block['date'];
		$estimated = $blocks * BLOCK_TIME;
		$ratio = $elapsed / $estimated;
		$difficulty = $latestBlock['difficulty'];
		$hashRate = $ratio * $difficulty / BLOCK_START_DIFFICULTY;
		return $hashRate;
	}

	static function getAvgBlockTime($blocks) {
		$blockCount = Block::getHeight();
		if( $blockCount < $blocks) {
			return "-";
		}
		$latestBlock = Block::getAtHeight($blockCount);
		$prev10block = Block::getAtHeight($blockCount - $blocks);
		$elapsed = $latestBlock['date'] - $prev10block['date'];
		return $elapsed / $blocks;
	}

	static function getMineInfo() {
		global $_config;
		$diff = Block::difficulty();
		$current = Block::current();
		$data = Transaction::mempool(Block::max_transactions());
		$reward = Block::reward($current['height']+1);
		$res = [
			"difficulty" => $diff,
			"block"      => $current['id'],
			"height"     => $current['height'],
			"date"=>$current['date'],
			"data"=>$data,
			"time"=>time(),
			"reward"=>num($reward['miner']),
			"version"=>Block::versionCode($current['height']+1),
			"generator"=>Account::getAddress($_config['generator_public_key']),
			"ip"=>$_SERVER['REMOTE_ADDR']
		];
//		_log("getMineInfo: ".json_encode($res), 5);
		return $res;
	}

	static function addBlock(Block $block) {

	}
}
