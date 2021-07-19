<?php


class Blockchain
{

	static function getHashRate($blocks) {
		$blockCount = Block::getHeight();
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
		$latestBlock = Block::getAtHeight($blockCount);
		$prev10block = Block::getAtHeight($blockCount - $blocks);
		$elapsed = $latestBlock['date'] - $prev10block['date'];
		return $elapsed / $blocks;
	}

}
