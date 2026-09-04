<?php

/*
|--------------------------------------------------------------------------
| Main configuration file
| Here are overridden default settings from file: config.default.php
|--------------------------------------------------------------------------
*/

$_config['chain_id'] = trim(file_get_contents(dirname(__DIR__)."/chain_id"));
if(file_exists(__DIR__ . "/config." . $_config['chain_id'].".inc.php")) {
    require_once __DIR__ . "/config.".$_config['chain_id'].".inc.php";
    return;
}

/*
|--------------------------------------------------------------------------
| Database Configuration
|--------------------------------------------------------------------------
*/

// The database DSN
$_config['db_connect'] = 'mysql:host=localhost;dbname=ENTER-DB-NAME;charset=utf8';

// The database username
$_config['db_user'] = 'ENTER-DB-USER';

// The database password
$_config['db_pass'] = 'ENTER-DB-PASS';

/*
 * Automatic updates
 *
 * The recommended setting is true so the node stays compatible with the
 * network. Set false only when you accept responsibility for manually
 * updating the node; an outdated node may stop syncing or be rejected by
 * newer peers after a required version change.
 */
$_config['allow_auto_update'] = true;

/*
|--------------------------------------------------------------------------
| Node Configuration
|--------------------------------------------------------------------------
*/


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

$_config['dex_node']=true;
//$_config['dex_contract']="";
