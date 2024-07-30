<?php
$_config['chain_id'] = trim(@file_get_contents(dirname(__DIR__)."/chain_id"));
if($_config['chain_id'] != DEFAULT_CHAIN_ID && file_exists(__DIR__ . "/coinspec.".$_config['chain_id'].".inc.php")) {
	require_once __DIR__ . "/coinspec.".$_config['chain_id'].".inc.php";
	return;
}

// mainnet specification
const NETWORK = "mainnet";
const CHAIN_ID = "00";
const COIN_PORT = "";
const VERSION = "1.3.3";
const BUILD_VERSION = 372;
const MIN_VERSION = "1.3.0";
const DEVELOPMENT = false;
const XDEBUG = "";
const XDEBUG_CLI = "";
const GIT_BRANCH = "main";
const MIN_MINER_VERSION = "1.3";

const COIN = "phpcoin";
const COIN_NAME="PHPCoin";
const COIN_SYMBOL="PHP";
const CHAIN_PREFIX = "38";
const COIN_DECIMALS = 8;
const BASE_REWARD = 100;
const GENESIS_REWARD = 103200000;
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
const TX_TYPE_SC_SEND = 7;
const TX_TYPE_BURN = 8;

const HASHING_ALGO = PASSWORD_ARGON2I;
const HASHING_OPTIONS = ['memory_cost' => 2048, "time_cost" => 2, "threads" => 1];
const REMOTE_PEERS_LIST_URL = "https://main1.phpcoin.net/peers.php";

const MIN_NODE_SCORE = 80;

const FEATURE_MN = true;
const MN_MIN_RUN_BLOCKS = 1440*30;
const MN_START_HEIGHT = 20001;

const FEE_START_HEIGHT = PHP_INT_MAX;
const FEE_DIVIDER = 100;

# Smart contracts
const FEATURE_SMART_CONTRACTS = false;
const TX_SC_CREATE_FEE = 100;
const TX_SC_EXEC_FEE = 0.01;
const SC_START_HEIGHT = PHP_INT_MAX;
const TX_TYPE_BURN_START_HEIGHT = 0;
const STAKING_START_HEIGHT = 20001;

const SC_MAX_EXEC_TIME = 30;
const SC_MEMORY_LIMIT = "256M";

const GIT_URL = "https://github.com/phpcoinn/node";
const UPDATE_1_BLOCK_ZERO_TIME = 0;
const UPDATE_2_BLOCK_CHECK_IMPROVED = 0;
const UPDATE_3_ARGON_HARD = 0;
const UPDATE_4_NO_POOL_MINING = 0;
const UPDATE_5_NO_MASTERNODE = 0;
const UPDATE_6_CHAIN_ID = 0;
const UPDATE_7_MINER_CHAIN_ID = 0;
const UPDATE_8_FIX_CHECK_BURN_TX_DST_NULL = [0, 0];
const UPDATE_9_ADD_MN_COLLATERAL_TO_SIGNATURE = 0;
const UPDATE_10_ZERO_TX_NOT_ALLOWED = 27000;
const UPDATE_11_STAKING_MATURITY_REDUCE = 290000;
const UPDATE_12_STAKING_DYNAMIC_THRESHOLD = 290000;
const UPDATE_13_LIMIT_GENERATOR=536500;
const UPDATE_14_EXTENDED_SC_HASH=0;
const UPDATE_15_EXTENDED_SC_HASH_V2=0;
const UPDATE_16_SC_TXS_SORT=0;

const DEV_PUBLIC_KEY = "PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCyao5hHHJd9axKhC1c5emTgT4hT7k7EvXiZrjTJSGEPmz9K1swEDQi8j14vCRwUisMsvHr4P5kirrDawM3NJiknWR";
const FEATURE_APPS = false;

const MAIN_DAPPS_ID = "PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3";
const TOTAL_INITIAL_SUPPLY = 103200000;

const STOP_CHAIN_HEIGHT = PHP_INT_MAX;
const DELETE_CHAIN_HEIGHT = PHP_INT_MAX;

const MN_CREATE_IGNORE_HEIGHT = [30234];
const MN_COLD_START_HEIGHT = 290000;

const IGNORE_SC_HASH_HEIGHT = [];
