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
		$data = Transaction::mempool(Block::max_transactions(), false);
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
			"ip"=>$_SERVER['REMOTE_ADDR'],
			"hashingOptions"=>Block::hashingOptions($current['height']+1),
		];
//		_log("getMineInfo: ".json_encode($res), 5);
		return $res;
	}

	static function addBlock(Block $block) {

	}

	static function calculateRewardsScheme($real=true) {

		$prev_reward = null;
		$prev_block = null;
		$total_supply = 0;

		$start_block = 1;
		$start_time = GENESIS_TIME;

		$rows = [];

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
				if($prev_reward) {
					$rows[$prev_reward]['end_block']=$i-1;
				}
				$rows[$reward['key']] = [
					'phase' => $reward['phase'],
					'block' => $i,
					'total' => $reward['total'],
					'miner' => $reward['miner'],
					'gen' => $reward['generator'],
					'mn' => $reward['masternode'],
					'pos' => $reward['pos'],
					'elapsed' => $elapsed,
					'days' => $days,
					'time' => $time,
					'supply' => $total_supply,
				];
			}
			if($reward['total']==0) {
				break;
			}
			$prev_reward = $reward['key'];
		}
		return $rows;
	}
}
