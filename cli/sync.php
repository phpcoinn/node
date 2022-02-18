<?php
/*
The MIT License (MIT)
Copyright (c) 2018 AroDev
Copyright (c) 2021 PHPCoin

phpcoin.net

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE
OR OTHER DEALINGS IN THE SOFTWARE.
*/

global $_config;
//TODO: implement bootstrap feature
const BOOTSTRAPING = false;
const BOOTSTRAP_URL = "https://blockchain.phpcoin.net/dump/phpcoin.sql";

set_time_limit(0);
error_reporting(0);

// make sure it's not accessible in the browser
if (php_sapi_name() !== 'cli') {
    die("This should only be run as cli");
}

$lock_file = dirname(__DIR__)."/tmp/sync-lock";

// make sure there's only a single sync process running at the same time
if (!mkdir($lock_file, 0700)) {
    $ignore_lock = false;
    if ($argv[1] == "force") {
        $res = intval(shell_exec("ps aux|grep '".dirname(__DIR__)."/cli/sync.php'|grep -v grep|wc -l"));
        if ($res == 1) {
            $ignore_lock = true;
        }
    }
    $pid_time = filemtime($lock_file);

    // If the process died, restart after 1 hour
    if (time() - $pid_time > 60 * 60) {
        @rmdir($lock_file);
    }

    if (!$ignore_lock) {
	    echo "Lock file in place".PHP_EOL;
	    exit;
    }
}

register_shutdown_function(function () {
	$lock_file = dirname(__DIR__)."/tmp/sync-lock";
	@rmdir($lock_file);
});

require_once dirname(__DIR__).'/include/init.inc.php';

global $db;

define("SYNC_LOCK_PATH", Nodeutil::getSyncFile());

$arg = trim($argv[1]);
$arg2 = trim($argv[2]);

if (DEVELOPMENT) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
    ini_set("display_errors", "on");
}

// the sync can't run without the schema being installed
if ($_config['dbversion'] < 1) {
    die("DB schema not created");
    @rmdir(SYNC_LOCK_PATH);
    exit;
}

ini_set('memory_limit', '2G');

$current = Block::current();





// bootstrapping the initial sync
//TODO: implement bootstraping feature
if ($current['height']==1 && BOOTSTRAPING) {
    echo "Bootstrapping!\n";
    $db_name=substr($_config['db_connect'], strrpos($_config['db_connect'], "dbname=")+7);
    $db_host=substr($_config['db_connect'], strpos($_config['db_connect'], ":host=")+6);
    $db_host=substr($db_host, 0, strpos($db_host, ";"));

    echo "DB name: $db_name\n";
    echo "DB host: $db_host\n";
    echo "Downloading the blockchain dump from ".BOOTSTRAP_URL."\n";
    $phpfile=ROOT . '/tmp/php.sql';
    if (file_exists("/usr/bin/curl")) {
        system("/usr/bin/curl -o $phpfile '".BOOTSTRAP_URL."'", $ret);
    } elseif (file_exists("/usr/bin/wget")) {
        system("/usr/bin/wget -O $phpfile '".BOOTSTRAP_URL."'", $ret);
    } else {
        die("/usr/bin/curl and /usr/bin/wget not installed or inaccessible. Please install either of them.");
    }
    

    echo "Importing the blockchain dump\n";
    system("mysql -h ".escapeshellarg($db_host)." -u ".escapeshellarg($_config['db_user'])." -p".escapeshellarg($_config['db_pass'])." ".escapeshellarg($db_name). " < ".$phpfile);
    echo "Bootstrapping completed. Waiting 2mins for the tables to be unlocked.\n";
    
    while (1) {
        sleep(120);
  
        $res=$db->run("SHOW OPEN TABLES WHERE In_use > 0");
        if (count($res==0)) {
            break;
        }
        echo "Tables still locked. Sleeping for another 2 min. \n";
    }

   

    $current = Block::current();
}
//TODO check microsync feature
// the microsync process is an anti-fork measure that will determine the best blockchain to choose for the last block
$microsync = false;
if ($arg == "microsync" && !empty($arg2)) {
    do {
	    _log("Microsync: Find peer by ip = $arg2", 3);
        // the microsync runs only against 1 specific peer
        $x = Peer::findByIp($arg2);
	    $current = Block::current();

        if (!$x) {
            echo "Invalid node - $arg2\n";
            _log("Microsync: Invalid node $arg2");
            break;
        }
        _log("Microsync: Get block ".$current['height']." from peer ".$x['hostname'],3);
        $url = $x['hostname']."/peer.php?q=";
        $data = peer_post($url."getBlock", ["height" => $current['height']]);

        if (!$data) {
            echo "Invalid getBlock result\n";
            _log("Microsync: Invalid getBlock result");
            break;
        }
        $data['id'] = san($data['id']);
        $data['height'] = san($data['height']);
        // nothing to be done, same blockchain
        if ($data['id'] == $current['id']) {
            echo "Same block\n";
            _log("Microsync: nothing to be done, same blockchain",2);
            break;
        }

//	    $difficulty1 = $current['difficulty'];
//	    $difficulty2 = $data['difficulty'];
//
//	    _log("Comparing difficulty my=$difficulty1 peer=$difficulty2",3);
//
//	    if($difficulty1 < $difficulty2) {
//		    echo "Block difficulty lower than current\n";
//		    _log("Block difficulty lower than current");
//		    break;
//	    }

        // delete the last block
        Block::pop(1);


        // add the new block
        echo "Starting to sync last block from $x[hostname]\n";
        _log("Microsync: Starting to sync last block from $x[hostname]");
        $b = $data;
		$prev = Block::current();
        $block = Block::getFromArray($b);
	    $block->prevBlockId = $prev['id'];
	    $res = $block->check();
	    if (!$res) {
		    _log("Microsync: block check failed - $b[id] - $b[height]");
		    break;
	    }
	    $res = $block->add();
        if (!$res) {
            _log("Microsync: could not add block - $b[id] - $b[height]");
            break;
        }

        _log("Microsync: Synced block from ".$x[hostname]." - $b[height] $b[difficulty]", 2);
    } while (0);

    @rmdir(SYNC_LOCK_PATH);
    exit;
}


$t = time();
//if($t-$_config['sync_last']<300) {@unlink("tmp/sync-lock");  die("The sync cron was already run recently"); }

_log("Starting sync",3);

// update the last time sync ran, to set the execution of the next run
$db->run("UPDATE config SET val=:time WHERE cfg='sync_last'", [":time" => $t]);
$block_peers = [];
$longest_size = 0;
$longest = 0;
$blocks = [];
$blocks_count = [];
$most_common = "";
$most_common_size = 0;
$most_common_height = 0; 
$total_active_peers = 0;
$largest_most_common = "";
$largest_most_common_size = 0;
$largest_most_common_height = 0; 


// checking peers

// delete the dead peers
Peer::deleteDeadPeers();

$total_peers = Peer::getCount(false);
_log("Total peers: ".$total_peers, 3);

$peered = [];
// if we have no peers, get the seed list from the official site
if ($total_peers == 0) {
    $i = 0;
    _log('No peers found. Attempting to get peers from the initial list');

	$peers = Peer::getInitialPeers();

    _log("Checking peers: ".print_r($peers, 1), 3);
    foreach ($peers as $peer) {
        // Peer with all until max_peers
        // This will ask them to send a peering request to our peer.php where we add their peer to the db.
        $peer = trim(san_host($peer));

        if(!Peer::validate($peer)) {
	        continue;
        }

        _log("Process peer ".$peer, 4);

	    if($peer === $_config['hostname']) {
		    continue;
	    }

        // store the hostname as md5 hash, for easier checking
        $pid = md5($peer);
        // do not peer if we are already peered
        if ($peered[$pid] == 1) {
            continue;
        }
        $peered[$pid] = 1;
        
        if ($_config['passive_peering'] == true) {
            // does not peer, just add it to DB in passive mode
            $res=Peer::insert(md5($peer), $peer, 0);
        } else {
            // forces the other node to peer with us.
            $res = peer_post($peer."/peer.php?q=peer", ["hostname" => $_config['hostname'], "repeer" => 1]);
        }
        if ($res !== false) {
            $i++;
            echo "Peering OK - $peer\n";
        } else {
            echo "Peering FAIL - $peer\n";
        }
        if ($i > $_config['max_peers']) {
            break;
        }
    }
    // count the total peers we have
    $total_peers = Peer::getCountAll();
    if ($total_peers == 0) {
        // something went wrong, could not add any peers -> exit
        @rmdir(SYNC_LOCK_PATH);
        _log("There are no active peers");
	    $db->setConfig('node_score', 0);
        die("There are no active peers!\n");
    }
}

$peerBlocks = [];

// contact all the active peers
$r=Peer::getActive(10);

 $i = 0;
foreach ($r as $x) {
    _log("Contacting peer $x[hostname]",4);
    $url = $x['hostname']."/peer.php?q=";
    // get their peers list
    if ($_config['get_more_peers']==true && $_config['passive_peering']!=true) {
        $data = peer_post($url."getPeers", []);
        if ($data === false) {
            _log("Peer $x[hostname] unresponsive data=".json_encode($data), 2);
            // if the peer is unresponsive, mark it as failed and blacklist it for a while
	        _log("blacklist peer $url because is unresponsive");
            Peer::blacklist($x['id'], "Unresponsive");
            continue;
        }
        if(is_array($data) && count($data)==0) {
	        _log("blacklist peer $url because it has no peers");
	        Peer::blacklist($x['id'], "No peers");
	        continue;
        }
        foreach ($data as $peer) {
            // store the hostname as md5 hash, for easier checking
            $peer['hostname'] = san_host($peer['hostname']);
            $peer['ip'] = san_ip($peer['ip']);
            $pid = md5($peer['hostname']);
            // do not peer if we are already peered
            if ($peered[$pid] == 1) {
                continue;
            }
            $peered[$pid] = 1;

            if(!Peer::validate($peer['hostname'])) {
	            continue;
            }
            // if it's our hostname, ignore
            if ($peer['hostname'] == $_config['hostname']) {
                continue;
            }
            // make sure there's no peer in db with this ip or hostname
	        $single = Peer::getSingle($peer['hostname'], $peer['ip']);
            if (!$single) {
                $i++;
                // check a max_test_peers number of peers from each peer
                if ($i > $_config['max_test_peers']) {
                    break;
                }
                $peer['hostname'] = filter_var($peer['hostname'], FILTER_SANITIZE_URL);
                // peer with each one
                _log("Trying to peer with recommended peer: $peer[hostname]", 4);
                $test = peer_post($peer['hostname']."/peer.php?q=peer", ["hostname" => $_config['hostname'], 'repeer'=>1]);
                if ($test !== false) {
                    $total_peers++;
                    echo "Peered with: $peer[hostname]\n";
                    // a single new peer per sync
                    $_config['get_more_peers']=false;
                } else {
					_log("Can not peer with ".$peer["hostname"]);
                }
            }
        }
    }

    // get the current block and check it's blockchain
	$res = peer_post($url."currentBlock", []);
    if ($res === false) {
        _log("Peer $x[hostname] unresponsive url={$url}currentBlock response=$res");
        // if the peer is unresponsive, mark it as failed and blacklist it for a while
        Peer::blacklist($x['id'],"Unresponsive");
        continue;
    }

	$data = $res['block'];
    $info = $res['info'];

    // peer was responsive, mark it as good
    if ($x['fails'] > 0) {
        Peer::clearFails($x['id']);
    }

    Peer::updateInfo($x['id'], $info);

    _log("Received peer info ".json_encode($info), 3);
    $data['id'] = san($data['id']);
    $data['height'] = san($data['height']);

    if ($current['height'] > 1 && $data['height'] < $current['height'] - 100) {
	    _log("blacklist peer $url because is 100 blocks behind, our height=".$current['height']." peer_height=".$data['height']);
        Peer::blacklistStuck($x['id'],"100 blocks behind");
        continue;
    } else {
        if ($x['stuckfail'] > 0) {
            Peer::clearStuck($x['id']);
        }
    }
    $total_active_peers++;
    // add the hostname and block relationship to an array
    $block_peers[$data['id']][] = $x['hostname'];
    // count the number of peers with this block id
    $blocks_count[$data['id']]++;
    // keep block data for this block id
    $blocks[$data['id']] = $data;
    // set the most common block on all peers
    if ($blocks_count[$data['id']] > $most_common_size) {
        $most_common = $data['id'];
        $most_common_size = $blocks_count[$data['id']];
        $most_common_height = $data['height'];
    }
    if ($blocks_count[$data['id']] > $largest_most_common_size && $data['height'] > $current['height']) {
        $largest_most_common = $data['id'];
        $largest_most_common_size = $blocks_count[$data['id']];
        $largest_most_common_height = $data['height'];
    }
    // set the largest height block
    if ($data['height'] > $largest_height) {
        $largest_height = $data['height'];
        $largest_height_block = $data['id'];
    } elseif ($data['height'] == $largest_height && $data['id'] != $largest_height_block) {
        // if there are multiple blocks on the largest height, choose one with the smallest (hardest) difficulty
        if ($data['difficulty'] == $blocks[$largest_height_block]['difficulty']) {
            // if they have the same difficulty, choose if it's most common
            if ($most_common == $data['id']) {
                $largest_height = $data['height'];
                $largest_height_block = $data['id'];
            } else {
                    // if the blocks have the same number of transactions, choose the one with the highest derived integer from the first 12 hex characters
                $no1 = hexdec(substr(coin2hex($largest_height_block), 0, 12));
                $no2 = hexdec(substr(coin2hex($data['id']), 0, 12));
                if (gmp_cmp($no1, $no2) == 1) {
                    $largest_height = $data['height'];
                    $largest_height_block = $data['id'];
                }
            }
        } elseif ($data['difficulty'] > $blocks[$largest_height_block]['difficulty']) {
            // choose higher (hardest) difficulty
            $largest_height = $data['height'];
            $largest_height_block = $data['id'];
        }
    }
}
$largest_size=$blocks_count[$largest_height_block];
_log("Most common: $most_common\n");
_log( "Most common block size: $most_common_size\n");
_log( "Most common height: $most_common_height\n\n");
_log( "Longest chain height: $largest_height\n");
_log( "Longest chain size: $largest_size\n\n");
_log( "Larger Most common: $largest_most_common\n");
_log( "Larger Most common block size: $largest_most_common_size\n");
_log( "Larger Most common height: $largest_most_common_height\n\n");
_log( "Total size: $total_active_peers\n\n");

_log( "Current block: $current[height]\n");

// if this is the node that's ahead, and other nodes are not catching up, pop 200

if($largest_height-$most_common_height>100&&$largest_size==1&&$current['id']==$largest_height_block){
    _log("Current node is alone on the chain and over 100 blocks ahead. Poping 200 blocks.");
    Block::pop(200);
    _log("Exiting sync, next will sync from 200 blocks ago.");

    @rmdir(SYNC_LOCK_PATH);
	Config::setSync(0);
    exit;
}

// if there's a single node with over 100 blocks ahead on a single peer, use the most common block
if($largest_height-$most_common_height>100 && $largest_size==1){
    if($current['id']==$most_common && $largest_most_common_size>3){
        _log("Longest chain is way ahead, using largest most common block");
        $largest_height=$largest_most_common_height;
        $largest_size=$largest_most_common_size;
        $largest_height_block=$largest_most_common;
    } else {
        _log("Longest chain is way ahead, using most common block");
        $largest_height=$most_common_height;
        $largest_size=$most_common_size;
        $largest_height_block=$most_common;
    }
}

$peers = $block_peers[$largest_height_block];
if(is_array($peers)) {
	$peers_count = count($peers);
	shuffle($peers);
}

//if(count($peers)>=3) {
	$nodeSync = new NodeSync($peers);
	$nodeSync->start($largest_height, $most_common);
//} else {
//	_log("Can not sync - not enough number of peers");
//}

$block_parse_failed=false;

$failed_syncs=0;



    $resyncing=false;
    if ($block_parse_failed==true&&$current['date']<time()-(3600*24)) {
        _log("Rechecking reward transactions",1);
        $current = Block::current();
        $rwpb=$db->single("SELECT COUNT(1) FROM transactions WHERE type=0 AND message=''");
        if ($rwpb!=$current['height']) {
            $failed=$db->single("SELECT blocks.height FROM blocks LEFT JOIN transactions ON transactions.block=blocks.id and transactions.type=0 and transactions.message='' WHERE transactions.height is NULL ORDER by blocks.height ASC LIMIT 1");
            if ($failed>1) {
                _log("Found failed block - $failed");
                Block::delete($failed);
                $block_parse_failed==false;
            }
        }
    }
   

Mempool::deleteOldMempool();

//rebroadcasting local transactions
if ($_config['sync_rebroadcast_locals'] == true && $_config['disable_repropagation'] == false) {
	$r = Mempool::getForRebroadcast($current['height']);
    _log("Rebroadcasting local transactions - ".count($r), 1);
    foreach ($r as $x) {
        $x['id'] = escapeshellarg(san($x['id'])); // i know it's redundant due to san(), but some people are too scared of any exec
	    $dir = __DIR__;
	    system("php $dir/propagate.php transaction $x[id]  > /dev/null 2>&1  &");
		Mempool::updateMempool($x['id'], $current['height']);
    }
}

//rebroadcasting transactions
if ($_config['disable_repropagation'] == false) {
    $forgotten = $current['height'] - $_config['sync_rebroadcast_height'];
	$r=Mempool::getForgotten($forgotten);

    _log("Rebroadcasting external transactions - ".count($r),1);

    foreach ($r as $x) {
        $x['id'] = escapeshellarg(san($x['id'])); // i know it's redundant due to san(), but some people are too scared of any exec
	    $dir = __DIR__;
	    system("php $dir/propagate.php transaction $x[id]  > /dev/null 2>&1  &");
		Mempool::updateMempool($x['id'], $current['height']);
    }
}

//add new peers if there aren't enough active
if ($total_peers < $_config['max_peers'] * 0.7) {
    $res = $_config['max_peers'] - $total_peers;
    Peer::reserve($res);
}

//random peer check
$r = Peer::getReserved(intval($_config['max_test_peers']));
foreach ($r as $x) {
    $url = $x['hostname']."/peer.php?q=";
    $data = peer_post($url."ping", []);
    if ($data === false) {
    	_log("blakclist peer ".$x['hostname']." because it is not answering");
        Peer::blacklist($x['id'],"Not answer");
        _log("Random reserve peer test $x[hostname] -> FAILED");
    } else {
        _log("Random reserve peer test $x[hostname] -> OK",3);
	    Peer::clearFails($x['id']);
    }
}



NodeSync::recheckLastBlocks();

if ($_config['masternode']==true&&!empty($_config['masternode_public_key'])&&!empty($_config['masternode_voting_public_key'])&&!empty($_config['masternode_voting_private_key'])) {
    echo "Masternode votes\n";
    $r=$db->run("SELECT * FROM masternode WHERE status=1 ORDER by ".DB::random()." LIMIT 3");
    foreach ($r as $x) {
        $blacklist=0;
        $x['ip']=san_ip($x['ip']);
        $ip = $x['ip'];
//	    $ip = "peer1.localhost:8000";
        echo "Testing masternode: $ip\n";
        $f=file_get_contents("http://$ip/api.php?q=currentBlock");
        if ($f) {
            $res=json_decode($f, true);
            $res=$res['data'];
            if ($res['height']<$current['height']-10080) {
                $blacklist=1;
            }
            echo "Masternode Height: ".$res['height']."\n";
        } else {
            echo "---> Unresponsive\n";
            $blacklist=1;
        }

    }
}

Nodeutil::cleanTmpFiles();

Minepool::deleteOldEntries();

_log("Finishing sync",3);

@rmdir(SYNC_LOCK_PATH);
