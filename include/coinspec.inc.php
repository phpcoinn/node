<?php
if(file_exists(dirname(__DIR__)."/testnet")) {
	require_once __DIR__ . "/testnet.coinspec.inc.php";
	return;
}

// mainnet specification
const NETWORK = "mainnet-alpha";
const VERSION = "1.0.5";
const BUILD_VERSION = 81;
const MIN_VERSION = "1.0.4";
const DEVELOPMENT = false;
const XDEBUG = "";
const XDEBUG_CLI = "";

const COIN = "phpcoin";
const COIN_NAME="PHPCoin";
const COIN_SYMBOL="PHP";
const NETWORK_PREFIX = "38";
const COIN_DECIMALS = 8;
const BASE_REWARD = 100;
const GENESIS_REWARD = 4900010;
const BLOCKCHAIN_CHECKPOINT = 1;

const BLOCK_TIME = 60;
const BLOCK_TARGET_MUL = 1000;
const BLOCK_START_DIFFICULTY = "60000";

const TX_FEE = 0;
const TX_TYPE_REWARD = 0;
const TX_TYPE_SEND = 1;
const TX_TYPE_MN_CREATE = 2;
const TX_TYPE_MN_REMOVE = 3;
const TX_TYPE_FEE = 4;
const TX_TYPE_SC_CREATE = 5;
const TX_TYPE_SC_EXEC = 6;

const HASHING_ALGO = PASSWORD_ARGON2I;
const HASHING_OPTIONS = ['memory_cost' => 2048, "time_cost" => 2, "threads" => 1];
const REMOTE_PEERS_LIST_URL = "https://node1.phpcoin.net/peers.php";

const REWARD_SCHEME = [
	'genesis' => [
		'reward'=> GENESIS_REWARD
	],
	'launch'=>[
		'blocks' => 100000 - 1,
		'reward'=> 10,
	],
	'mining'=>[
		'block_per_segment'=>[
			10000,10000,10000,10000,10000,2000,2000,2000,2000,2000
		]
	],
	'combined'=>[
		'block_per_segment'=>[
			50000,50000,50000,50000,50000,50000,50000,50000,50000,50000
		]
	],
	'deflation'=>[
		'block_per_segment'=>[
			100000,100000,100000,100000,100000,100000,100000,100000,100000,100000
		]
	]
];

const MIN_NODE_SCORE = 80;

const FEATURE_MN = true;
const MN_COLLATERAL = 10000;
const MN_MIN_RUN_BLOCKS = 1440*30;

const FEE_START_HEIGHT = PHP_INT_MAX;
const FEE_DIVIDER = 100;

# Smart contracts
const TX_SC_CREATE_FEE = 100;
const TX_SC_EXEC_FEE = 0.01;
const SC_START_HEIGHT = PHP_INT_MAX;

const SC_MAX_EXEC_TIME = 30;
const SC_MEMORY_LIMIT = "256M";

const TOTAL_SUPPLY = 103200000;
const GIT_URL = "https://github.com/phpcoinn/node";
const UPDATE_1_BLOCK_ZERO_TIME = 9000;
const UPDATE_2_BLOCK_CHECK_IMPROVED = 25000;
const UPDATE_3_ARGON_HARD = 45000;
const UPDATE_4_NO_POOL_MINING = 45400;
const UPDATE_5_NO_MASTERNODE = 280000;
