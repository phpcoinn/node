<?php
/**
 * Default config file
 */
// Default database connection
$_config['chain_id'] = trim(file_get_contents(dirname(__DIR__)."/chain_id"));
$_config['db_connect'] = 'mysql:host=localhost;dbname=phpcoin;charset=utf8';
$_config['db_user'] = 'phpcoin';
$_config['db_pass'] = 'phpcoin';

// Allow others to connect to the node api (if set to false, only the below 'allowed_hosts' are allowed)
$_config['public_api'] = true;

// Hosts that are allowed to mine on this node
$_config['allowed_hosts'] = ['*'];

// The initial peers to sync from
$_config['initial_peer_list'] = [
    'https://main1.phpcoin.net',
    'https://main2.phpcoin.net',
    'https://main3.phpcoin.net'
];

// does not peer with any of the peers. Uses the seed peers and syncs only from those peers. Requires a cronjob on sync.php
$_config['passive_peering'] = false;

// limit number of peers for propagate
$_config['peers_limit']=30;

// set custom interface to which server listens
//$_config['interface']="";

// set custom proxy for outgoing requests
//$_config['proxy']="";

// set node to offline, do not send or receive peer requests
$_config['offline']=false;

// set outgoing proxy for peer requests
$_config['proxy']=null;

// The maximum transactions to accept from a single peer
$_config['peer_max_mempool'] = 100;

// Block accepting transfers from addresses blacklisted by the PHPCoin devs
$_config['use_official_blacklist'] = true;

// Recheck the last blocks
$_config['sync_recheck_blocks'] = 10;

// Enable setting a new hostname (should be used only if you want to change the hostname)
$_config['allow_hostname_change'] = false;

// Enable log output to the specified file
$_config['enable_logging'] = true;

// The specified file to write to (this should not be publicly visible)
$_config['log_file'] = 'tmp/phpcoin.log';

// Log verbosity (default 0, maximum 5)
$_config['log_verbosity'] = 0;

// Log to server log, e.g. error_log to apache
$_config['server_log'] = false;

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
//login to admin panel with private key
$_config['admin_public_key']='';

/**
 * Masternode configuration
 */
$_config['masternode']=false;
$_config['masternode_public_key']="";
$_config['masternode_private_key']="";

/**
 * Configuration for decentralized apps
 */
$_config['dapps']=false;
$_config['dapps_public_key']="";
$_config['dapps_private_key']="";
$_config['dapps_anonymous']=false;
$_config['dapps_disable_auto_propagate']=true;

// set server to maintenance mode
//$_config['maintenance']=1;
