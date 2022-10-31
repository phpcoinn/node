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
			"fee"=>Blockchain::getFee(),
			"network"=>NETWORK,
			"chain_id"=>CHAIN_ID
		];
//		_log("getMineInfo: ".json_encode($res), 5);
		return $res;
	}

	static function addBlock(Block $block) {

	}

	static function getPhases() {
		$phases = [];
		$phases[] = ["name"=>"genesis", "blocks"=>1, "reward"=>REWARD_SCHEME['genesis']['reward']];
		$phases[] = ["name"=>"launch", "blocks"=>REWARD_SCHEME['launch']['blocks'], "reward"=>REWARD_SCHEME['launch']['reward']];
		foreach(REWARD_SCHEME['mining']['block_per_segment'] as $s => $segment_blocks) {
			$phases[] = ["name"=>"mining","blocks"=>$segment_blocks, "reward"=>REWARD_SCHEME['mining']['reward_per_segment'][$s],"segment"=>$s+1];
		}
		foreach(REWARD_SCHEME['combined']['block_per_segment'] as $s => $segment_blocks) {
			$phases[] = ["name"=>"combined","blocks"=>$segment_blocks, "reward"=>REWARD_SCHEME['combined']['reward'],"segment"=>$s+1];
		}
		foreach(REWARD_SCHEME['deflation']['block_per_segment'] as $s => $segment_blocks) {
			$phases[] = ["name"=>"deflation","blocks"=>$segment_blocks, "reward"=>REWARD_SCHEME['deflation']['reward_per_segment'][$s],"segment"=>$s+1];
		}
		$total = 0;
		$block = 0;
		foreach($phases as &$phase) {
			$block+=$phase['blocks'];
			$phase['total_blocks']=$block;
			$phase['start']=$block - $phase['blocks'] +1 ;
			$phase['end']=$phase['start'] + $phase['blocks'] - 1 ;
			$total += ($phase['blocks']*$phase['reward']);
			$phase['total']=$total;
		}
		return $phases;
	}

	static function calculateRewardsScheme($real=true) {
		$start_time = GENESIS_TIME;
		$rows = [];

		$phases = self::getPhases();

		$block = Block::current();

		$total_supply = 0;
		foreach($phases as  $phase) {
			$key = $phase['name']."-".$phase['segment'];
			$reward = Block::reward($phase['start']);
			$elapsed = ($phase['start']-1) * BLOCK_TIME;
			$time = $start_time + $elapsed;
			$days = $elapsed / 60 / 60 / 24;
			$total_supply += $phase['blocks'] * $reward['total'];
			if($phase['start'] < $block['height']) {
				$real_block = Block::get($phase['start']);
				$real_start_time = $real_block['date'];
			} else {
				$real_start_time = ($phase['start'] - $block['height']) * BLOCK_TIME + $block['date'];
			}
			$rows[$key] = [
				'phase' => $phase['name'],
				'block' => $phase['start'],
				'total' => $reward['total'],
				'miner' => $reward['miner'],
				'gen' => $reward['generator'],
				'mn' => $reward['masternode'],
				'pos' => $reward['pos'],
				'elapsed' => $elapsed,
				'days' => $days,
				'time' => $time,
				'segment'=>$reward['segment'],
				'key'=>$phase['name']."-".$phase['segment'],
				'end_block'=>$phase['end'],
				'blocks'=>$phase['blocks'],
				'supply'=>$total_supply,
				'real_start_time'=>$real_start_time
			];
		}
		return $rows;
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


	static function getTotalSupply() {
		$total = self::getTotalGeneratedSupply();
		$burnedAmount = Transaction::getBurnedAmount();
		return $total - $burnedAmount;
	}

	static function getTotalGeneratedSupply() {
		$phases = self::getPhases();
		$last = array_pop($phases);
		return $last['total'];
	}
}
