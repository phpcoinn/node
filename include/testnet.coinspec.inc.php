<?php
// testnet specification

const NETWORK = "testnet";
const VERSION = "0.0.1";
const BUILD_VERSION = 23;
const MIN_VERSION = "0.0.1";
const DEVELOPMENT = true;
const XDEBUG = "XDEBUG_SESSION_START=PHPSTORM";
//const XDEBUG_CLI = "-dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9000 -dxdebug.start_with_request=yes";
//const XDEBUG = "";
const XDEBUG_CLI = "";

const COIN = "phpcoin";
const COIN_NAME="PHPCoin";
const COIN_SYMBOL="PHP";
const NETWORK_PREFIX = "30";
const COIN_DECIMALS = 8;
const BASE_REWARD = 10;
const GENESIS_REWARD = 10000;
const BLOCKCHAIN_CHECKPOINT = 1;

const BLOCK_TIME = 30;
const BLOCK_TARGET_MUL = 1000;
const BLOCK_START_DIFFICULTY = "30000";

const TX_FEE = 0;
const TX_TYPE_REWARD = 0;
const TX_TYPE_SEND = 1;
const TX_TYPE_MN_CREATE = 2;
const TX_TYPE_MN_REMOVE = 3;

const TX_TYPE_FEE = 4;
const TX_TYPE_SC_CREATE = 5;

const HASHING_ALGO = PASSWORD_ARGON2I;
const HASHING_OPTIONS = ['memory_cost' => 2048, "time_cost" => 2, "threads" => 1];
//const REMOTE_PEERS_LIST_URL = "https://node1.testnet.phpcoin.net:8001/peers.php";
const REMOTE_PEERS_LIST_URL = "http://spectre:8001/peers.php";

const REWARD_SCHEME = [
	'genesis' => [
		'reward'=> GENESIS_REWARD
	],
	'launch'=>[
		'blocks' => 10 - 1,
		'reward'=> 10,
	],
	'mining'=>[
		'block_per_segment'=>[
			10, 10, 10, 10, 10, 10, 10, 10, 10, 10
		]
	],
	'combined'=>[
		'block_per_segment'=>[
			1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000
		]
	],
	'deflation'=>[
		'block_per_segment'=>[
			1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000
		]
	]
];

const MIN_NODE_SCORE = 30;

const FEATURE_MN = true;
const MN_COLLATERAL = 1000;
const MN_MIN_RUN_BLOCKS = 4;

//Smart contracts feature
const FEATURE_SC = true;
const TX_SC_CREATE_FEE = 0;
const SC_START_HEIGHT = 100;

const FEE_START_HEIGHT = 10;

const TOTAL_SUPPLY = 714990;
const GIT_URL = "https://github.com/phpcoinn/node";
const UPDATE_1_BLOCK_ZERO_TIME = 5;
const UPDATE_2_BLOCK_CHECK_IMPROVED = 10;
const UPDATE_3_ARGON_HARD = 15;
const UPDATE_4_NO_POOL_MINING = 20;
