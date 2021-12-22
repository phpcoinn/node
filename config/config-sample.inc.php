<?php

/*
|--------------------------------------------------------------------------
| Database Configuration
|--------------------------------------------------------------------------
*/
$_config['testnet'] = file_exists(dirname(__DIR__)."/testnet");
// The database DSN
$_config['db_connect'] = 'mysql:host=localhost;dbname=ENTER-DB-NAME';
// Alternative sqlite db
//$_config['db_connect'] = 'sqlite:DB-PATH';

// The database username
$_config['db_user'] = 'ENTER-DB-USER';

// The database password
$_config['db_pass'] = 'ENTER-DB-PASS';

/*
|--------------------------------------------------------------------------
| General Configuration
|--------------------------------------------------------------------------
*/

// Maximum number of connected peers
$_config['max_peers'] = 30;

// Allow others to connect to the node api (if set to false, only the below 'allowed_hosts' are allowed)
$_config['public_api'] = true;

// Hosts that are allowed to mine on this node
$_config['allowed_hosts'] = [
	'*',
];

// Disable transactions and block repropagation
$_config['disable_repropagation'] = false;


/*
|--------------------------------------------------------------------------
| Peer Configuration
|--------------------------------------------------------------------------
*/

// The number of peers to send each new transaction to
$_config['transaction_propagation_peers'] = 5;

// How many new peers to check from each peer
$_config['max_test_peers'] = 5;

// The initial peers to sync from
$_config['initial_peer_list'] = [
    'https://node1.phpcoin.net',
    'https://node2.phpcoin.net',
    'https://node3.phpcoin.net'
];

// does not peer with any of the peers. Uses the seed peers and syncs only from those peers. Requires a cronjob on sync.php
$_config['passive_peering'] = false;

// set node to offline, do not send or receive peer requests
$_config['offline']=false;

// set ip restriction on miner
$_config['minepool']=false;

/*
|--------------------------------------------------------------------------
| Mempool Configuration
|--------------------------------------------------------------------------
*/

// The maximum transactions to accept from a single peer
$_config['peer_max_mempool'] = 100;

// The maximum number of mempool transactions to be rebroadcasted
$_config['max_mempool_rebroadcast'] = 5000;

// The number of blocks between rebroadcasting transactions
$_config['sync_rebroadcast_height'] = 30;

// Block accepting transfers from addresses blacklisted by the PHPCoin devs
$_config['use_official_blacklist'] = true;

/*
|--------------------------------------------------------------------------
| Sync Configuration
|--------------------------------------------------------------------------
*/

// Recheck the last blocks
$_config['sync_recheck_blocks'] = 0;

// The interval to run the sync in seconds
$_config['sync_interval'] = 60;

// Enable setting a new hostname (should be used only if you want to change the hostname)
$_config['allow_hostname_change'] = false;

// Rebroadcast local transactions when running sync
$_config['sync_rebroadcast_locals'] = true;

// Get more peers?
$_config['get_more_peers'] = true;

// Allow automated resyncs if the node is stuck. Enabled by default
$_config['auto_resync'] = true;

/*
|--------------------------------------------------------------------------
| Logging Configuration
|--------------------------------------------------------------------------
*/

// Enable log output to the specified file
$_config['enable_logging'] = true;

// Log to server log, e.g. error_log to apache
$_config['server_log'] = false;

// The specified file to write to (this should not be publicly visible)
$_config['log_file'] = 'tmp/phpcoin.log';

// Log verbosity (default 0, maximum 3)
$_config['log_verbosity'] = 0;


/**
 * Miner config
 */
$_config['miner']=false;
$_config['miner_public_key']="";
$_config['miner_private_key']="";
$_config['miner_cpu']=0;

/**
 * Generator config
 */
$_config['generator']=false;
$_config['generator_public_key']="";
$_config['generator_private_key']="";

/**
 * Allow web admin of node
 */
$_config['admin']=false;
$_config['admin_password']='';

/**
 * Allow wallet app on node
 */
$_config['wallet']=false;
$_config['wallet_public_key']="";
$_config['wallet_private_key']="";

