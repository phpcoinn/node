<?php
// mainnet specification
const VERSION = "1.0.0";
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

const HASHING_ALGO = PASSWORD_ARGON2I;
const HASHING_OPTIONS = ['memory_cost' => 2048, "time_cost" => 1, "threads" => 1];
const REMOTE_PEERS_LIST_URL = "https://node1.phpcoin.net/peers.php";

const REWARD_SCHEME = [
	'genesis' => [
		'reward'=> GENESIS_REWARD
	],
	'launch'=>[
		'blocks' => 10000,
		'reward'=> 10,
	],
	'mining'=>[
		'segments'=>10,
		'block_per_segment'=>20000
	],
	'combined'=>[
		'segments'=>10,
		'block_per_segment'=>50000
	],
	'deflation'=>[
		'segments'=>10,
		'block_per_segment'=>100000
	]
];

const TOTAL_SUPPLY = 210000000;
const GIT_URL = "https://github.com/phpcoinn/node";
