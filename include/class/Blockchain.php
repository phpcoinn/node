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
			"fee"=>Blockchain::getFee()
		];
//		_log("getMineInfo: ".json_encode($res), 5);
		return $res;
	}

	static function addBlock(Block $block) {

	}

	static function calculateRewardsScheme($real=true) {

		$prev_reward = null;
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
			$days = $elapsed / 60 / 60 / 24;
			if($reward['key'] != $prev_reward) {
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
					'segment'=>$reward['segment'],
					'key'=>$reward['key']
				];
			}
			if($reward['total']==0) {
				break;
			}
			$prev_reward = $reward['key'];
		}

		$rows = array_values($rows);
		foreach ($rows as $index => &$row) {
			if(isset($rows[$index+1])) {
				$row['end_block'] = $rows[$index+1]['block']-1;
				$row['blocks']=$row['end_block'] - $row['block'] + 1;
				$total_supply += $row['blocks'] * $row['total'];
				$row['supply'] = $total_supply;
			}
		}

		$rows2 = [];
		foreach ($rows as $row2) {
			$rows2[$row2['key']]=$row2;
		}

		return $rows2;
	}

	static function feeMultiplier($height = null) {
		if(empty($height)) {
			$height = Block::getHeight();
		}
		if($height < FEE_START_HEIGHT) {
			return 0;
		}
		return 1 / FEE_DIVIDER;
	}

	static function getFee($block_height = null) {
		if(empty($block_height)) {
			$height = Block::getHeight();
		} else {
			$height = $block_height;
		}
		if($height < FEE_START_HEIGHT) {
			return 0;
		}
		return 0;
	}

	static function standardFee($height) {
		$block = Block::get($height);
		$difficulty = $block['difficulty'];
		$max = gmp_hexdec("ffffffff");
		$fee_ratio = gmp_div(gmp_mul($difficulty, 100000000), $max);
		$fee_multiplier = self::feeMultiplier($height);
		$fee_ratio = round((intval($fee_ratio) / 100000000) * $fee_multiplier , 5);
		return $fee_ratio;
	}
}
