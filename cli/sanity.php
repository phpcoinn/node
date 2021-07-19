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

require_once dirname(__DIR__).'/include/init.inc.php';

_log("Executing sanity", 2);
define("SANITY_LOCK_PATH", Nodeutil::getSanityFile());

// make sure there's only a single sanity process running at the same time
if (file_exists(SANITY_LOCK_PATH)) {
    $ignore_lock = false;
    if ($argv[1] == "force") {
        $res = intval(shell_exec("ps aux|grep sanity.php|grep -v grep|wc -l"));
        if ($res == 1) {
            $ignore_lock = true;
        }
    }
    $pid_time = filemtime(SANITY_LOCK_PATH);

    // If the process died, restart after 60 times the sanity interval
    if (time() - $pid_time > ($_config['sanity_interval'] * 10 ?? 900 * 10)) {
        @unlink(SANITY_LOCK_PATH);
    }

    if (!$ignore_lock) {
        die("Sanity lock in place".PHP_EOL);
    }
}

// set the new sanity lock
$lock = fopen(SANITY_LOCK_PATH, "w");
fclose($lock);
$arg = trim($argv[1]);
$arg2 = trim($argv[2]);
echo "Sleeping for 3 seconds\n";
// sleep for 3 seconds to make sure there's a delay between starting the sanity and other processes
if ($arg != "microsanity") {
    sleep(3);
}

if (DEVELOPMENT) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
    ini_set("display_errors", "on");
}

// the sanity can't run without the schema being installed
if ($_config['dbversion'] < 1) {
    die("DB schema not created");
    @unlink(SANITY_LOCK_PATH);
    exit;
}

ini_set('memory_limit', '2G');

$block = new Block();
$acc = new Account();
$trx= new Transaction();

$current = $block->current();





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

   

    $current = $block->current();
}
// the microsanity process is an anti-fork measure that will determine the best blockchain to choose for the last block
$microsanity = false;
if ($arg == "microsanity" && !empty($arg2)) {
    do {
        // the microsanity runs only against 1 specific peer
        $x = Peer::findByIp($arg2);

        if (!$x) {
            echo "Invalid node - $arg2\n";
            break;
        }
        $url = $x['hostname']."/peer.php?q=";
        $data = peer_post($url."getBlock", ["height" => $current['height']]);

        if (!$data) {
            echo "Invalid getBlock result\n";
            break;
        }
        $data['id'] = san($data['id']);
        $data['height'] = san($data['height']);
        // nothing to be done, same blockchain
        if ($data['id'] == $current['id']) {
            echo "Same block\n";
            break;
        }


	    $difficulty1 = $current['difficulty'];
	    $difficulty2 = $data['difficulty'];

	    _log("Comparing difficulty my=$difficulty1 peer=$difficulty2");

	    if($difficulty1 < $difficulty2) {
		    echo "Block difficulty lower than current\n";
		    _log("Block difficulty lower than current");
		    break;
	    }


	    // the blockchain with the most transactions wins the fork (to encourage the miners to include as many transactions as possible) / might backfire on garbage

        // transform the first 12 chars into an integer and choose the blockchain with the biggest value
/*        $no1 = hexdec(substr(coin2hex($current['id']), 0, 12));
        $no2 = hexdec(substr(coin2hex($data['id']), 0, 12));

        if (gmp_cmp($no1, $no2) != -1) {
            echo "Block hex larger than current\n";
            break;
        }*/
        
        // make sure the block is valid
        /*$prev = $block->get($current['height'] - 1);
        $public = $acc->public_key($data['generator']);
        if (!$block->mine(
            $public,
            $data['nonce'],
            $data['argon'],
            $block->difficulty($current['height'] - 1),
            $prev['id'],
            $prev['height'],
            $data['date']
        )) {
            echo "Invalid prev-block\n";
            break;
        }
        if (!$block->check($data)) {
            break;
        }*/

        // delete the last block
        $block->pop(1);


        // add the new block
        echo "Starting to sync last block from $x[hostname]\n";
        $b = $data;
		$prev = $block->prev();
        
        $res = $block->add(
            $b['height'],
            $b['public_key'],
            $b['nonce'],
            $b['data'],
            $b['date'],
            $b['signature'],
            $b['difficulty'],
            $b['reward_signature'],
            $b['argon'],
	        $prev['id']
        );
        if (!$res) {
            _log("Block add: could not add block - $b[id] - $b[height]");

            break;
        }

        _log("Synced block from ".$x[hostname]." - $b[height] $b[difficulty]");
    } while (0);

    @unlink(SANITY_LOCK_PATH);
    exit;
}


$t = time();
//if($t-$_config['sanity_last']<300) {@unlink("tmp/sanity-lock");  die("The sanity cron was already run recently"); }

_log("Starting sanity",4);

// update the last time sanity ran, to set the execution of the next run
$db->run("UPDATE config SET val=:time WHERE cfg='sanity_last'", [":time" => $t]);
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

$total_peers = Peer::getCount(true);

$peered = [];
// if we have no peers, get the seed list from the official site
if ($total_peers == 0) {
    $i = 0;
    _log('No peers found. Attempting to get peers from the initial list');

	$peers = Peer::getInitialPeers();

    _log("Checking peers: ".print_r($peers, 1));
    foreach ($peers as $peer) {
        // Peer with all until max_peers
        // This will ask them to send a peering request to our peer.php where we add their peer to the db.
        $peer = trim(san_host($peer));

        if(!Peer::validate($peer)) {
	        continue;
        }

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
            $res = peer_post($peer."/peer.php?q=peer", ["hostname" => $_config['hostname'], "repeer" => 1], 60, true);
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
    $total_peers = Peer::getCount(true);
    if ($total_peers == 0) {
        // something went wrong, could not add any peers -> exit
        @unlink(SANITY_LOCK_PATH);
        _log("There are no active peers");
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
        $data = peer_post($url."getPeers", [], 30, true);
        if ($data === false) {
            _log("Peer $x[hostname] unresponsive");
            // if the peer is unresponsive, mark it as failed and blacklist it for a while
	        _log("blacklist peer $url because is unresponsive");
            Peer::blacklist($x['id'], "Unresponsive");
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
                _log("Trying to peer with recommended peer: $peer[hostname]");
                $test = peer_post($peer['hostname']."/peer.php?q=peer", ["hostname" => $_config['hostname'], 'repeer'=>1], 5, true);
                if ($test !== false) {
                    $total_peers++;
                    echo "Peered with: $peer[hostname]\n";
                    // a single new peer per sanity
                    $_config['get_more_peers']=false;
                }
            }
        }
    }

    // get the current block and check it's blockchain
	$res = peer_post($url."currentBlock", [], 5);
    if ($res === false) {
        _log("Peer $x[hostname] unresponsive url=$url response=$res");
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

    _log("Received peer info ".json_encode($info), 0);
    $data['id'] = san($data['id']);
    $data['height'] = san($data['height']);

    if ($current['height'] > 1 && $data['height'] < $current['height'] - 500) {
	    _log("blacklist peer $url because is 500 blocks behind");
        Peer::blacklistStuck($x['id'],"500 blocks behind");
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
echo "Most common: $most_common\n";
echo "Most common block size: $most_common_size\n";
echo "Most common height: $most_common_height\n\n";
echo "Longest chain height: $largest_height\n";
echo "Longest chain size: $largest_size\n\n";
echo "Larger Most common: $largest_most_common\n";
echo "Larger Most common block size: $largest_most_common_size\n";
echo "Larger Most common height: $largest_most_common_height\n\n";
echo "Total size: $total_active_peers\n\n";

echo "Current block: $current[height]\n";

// if this is the node that's ahead, and other nodes are not catching up, pop 200

if($largest_height-$most_common_height>100&&$largest_size==1&&$current['id']==$largest_height_block){
    _log("Current node is alone on the chain and over 100 blocks ahead. Poping 200 blocks.");
    $db->run("UPDATE config SET val=1 WHERE cfg='sanity_sync'");
    $block->pop(200);
    $db->run("UPDATE config SET val=0 WHERE cfg='sanity_sync'");
    _log("Exiting sanity, next sanity will sync from 200 blocks ago.");

    @unlink(SANITY_LOCK_PATH);
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

$db->run("UPDATE config SET val=1 WHERE cfg='sanity_sync'");
$peers = $block_peers[$largest_height_block];
if(is_array($peers)) {
	$peers_count = count($peers);
	shuffle($peers);
}

$syncing = true;
$loop_cnt = 0;
while($syncing) {
	$loop_cnt++;
	if($loop_cnt > $largest_height) {
		break;
	}
	$failed_peer = 0;
	$failed_block = 0;
	$ok_block = 0;
	$height = $current['height'];
	foreach ($peers as $host) {
		if(!isset($peerBlocks[$host][$height])) {
			$url = $host."/peer.php?q=";
			_log("Reading blocks from $height from peer $host", 0);
			$peer_blocks = peer_post($url."getBlocks", ["height" => $height], 5);
			if ($peer_blocks === false) {
				_log("Could not get block from $host - " . $height,0);
				$failed_peer++;
				continue;
			}
			if(is_array($peer_blocks)) {
				foreach($peer_blocks as $peer_block) {
					$peerBlocks[$host][$peer_block['height']]=$peer_block;
				}
			}
		}

		if(isset($peerBlocks[$host][$height])) {
			$last_block = $peerBlocks[$host][$height];
			_log("last_block=".$last_block['id']. " current_id=".$current['id']);
			if ($last_block['id'] != $current['id']) {
				$failed_block++;
				_log("We have wrong block $height failed_block=$failed_block",0);
			} else {
				$ok_block++;
				_log("We have ok block $height ok_block=$ok_block",0);
			}
		}

	}

	$db->setConfig('node_score', ($ok_block / ($peers_count - $failed_peer))*100);

	if($failed_block > 0 && $failed_block > ($peers_count - $failed_peer) / 2 ) {
		_log("Failed block on blockchain $failed_block - remove", 0);
		$res=$block->pop();
	} else if ($ok_block == ($peers_count - $failed_peer)) {
		_log("last block $height is ok - get next", 0);
		$failed_next_peer = 0;
		$next_block_peers = [];
		$height++;
		foreach ($peers as $host) {
			if(!isset($peerBlocks[$host][$height])) {
				_log("Reading next blocks from $height from peer $host", 0);
				$url = $host."/peer.php?q=";
				$next_blocks = peer_post($url."getBlocks", ["height" => $height], 5);
				if (!$next_blocks) {
					_log("Could not get block from $host - " . $height);
					$failed_next_peer++;
					continue;
				}
				foreach($next_blocks as $next_block) {
					$peerBlocks[$host][$next_block['height']]=$next_block;
				}
			}


//			$next_blocks = peer_post($url."getBlocks", ["height" => $height], 5);
			$next_block = $peerBlocks[$host][$height];
			if (!$next_block) {
				_log("Could not get block from $host - " . $height);
				$failed_next_peer++;
				continue;
			}
			$next_block_peers[$next_block['id']][$host]=$next_block;
		}

		if(count(array_keys($next_block_peers))==1) {
			$id = array_keys($next_block_peers)[0];
			if(count(array_keys($next_block_peers[$id])) == $peers_count - $failed_next_peer) {
				_log("All peers return same block - checking block $height", 0);
				$host = array_keys($next_block_peers[$id])[0];
				$next_block = $next_block_peers[$id][$host];
				if (!$block->check($next_block)
				) {
					_log("Invalid block mined at height ".$height);
					$syncing = false;
					break;
				} else {
					$res = $block->add(
						$next_block['height'],
						$next_block['public_key'],
						$next_block['nonce'],
						$next_block['data'],
						$next_block['date'],
						$next_block['signature'],
						$next_block['difficulty'],
						$next_block['reward_signature'],
						$next_block['argon'],
						$next_block['prev_block_id']
					);
					if (!$res) {
						_log("Block add: could not add block at height $height", 3);
						$syncing = false;
						break;
					}
				}
			}
		}
	}

	$current = $block->current();
	$syncing = (($current['height'] < $largest_height && $largest_height > 1)
		|| ($current['height'] == $largest_height && $current['id']!=$most_common));
	_log("check syncing syncing=$syncing current_height=".$current['height']." largest_height=$largest_height current_id=".
		$current['id']." most_common=$most_common");
}




$db->run("UPDATE config SET val=0 WHERE cfg='sanity_sync'", [":time" => $t]);
if($syncing) {
	_log("Blockchain SYNCED",0);
}

$block_parse_failed=false;

$failed_syncs=0;
// if we're not on the largest height
if ($current['height'] < $largest_height && $largest_height > 1 && false) {
    // start  sanity sync / block all other transactions/blocks
    $db->run("UPDATE config SET val=1 WHERE cfg='sanity_sync'");
    sleep(10);
    _log("Longest chain rule triggered - $largest_height - $largest_height_block",2);
    // choose the peers which have the larget height block
    $peers = $block_peers[$largest_height_block];
    shuffle($peers);
    // sync from them
    foreach ($peers as $host) {
        _log("Starting to sync from $host", 3);
        $url = $host."/peer.php?q=";
        $data = peer_post($url."getBlock", ["height" => $current['height']], 60);
        // invalid data
        if ($data === false) {
            _log("Could not get block from $host - " . $current['height']);
            continue;
        }
        $data['id'] = san($data['id']);
        $data['height'] = san($data['height']);

        // if we're not on the same blockchain but the blockchain is most common with over 90% of the peers, delete the last 3 blocks and retry
        if ($data['id'] != $current['id'] && $data['id'] == $most_common && ($most_common_size / $total_active_peers) > 0.90) {
            $block->delete($current['height'] - 3);
            $current = $block->current();
            $data = peer_post($url."getBlock", ["height" => $current['height']]);

            if ($data === false) {
                _log("Could not get block from $host - $current[height]");
                break;
            }
        } elseif ($data['id'] != $current['id'] && $data['id'] != $most_common) {
            //if we're not on the same blockchain and also it's not the most common, verify all the blocks on on this blockchain starting at current-30 until current
            $invalid = false;
            $last_good = $current['height'];
	        $start = $current['height'] - 100;
            if($start < 1) {
	            $start = 1;
            }
            for ($i = $start; $i < $current['height']; $i++) {
                $data = peer_post($url."getBlock", ["height" => $i]);
                if ($data === false) {
                    $invalid = true;
                    _log("Could not get block from $host - $i");
                    break;
                }
                $ext = $block->get($i);
                if ($i == $current['height'] - 100 && $ext['id'] != $data['id']) {
                    _log("100 blocks ago was still on a different chain. Ignoring.");
                    $invalid = true;
                    break;
                }

                if ($ext['id'] == $data['id']) {
                    $last_good = $i;
                    _log("last good = $last_good", 3);
                } else {
	                $invalid = true;
	                break;
                }
            }

            if($invalid && $last_good < $current['height']) {
	            $try_pop=$block->pop($current['height'] - $last_good);
	            if($try_pop==false){
		            $block_parse_failed=true;
	            }
	            $current = $block->current();
            }
            // if last 10 blocks are good, verify all the blocks
            if ($invalid == false) {
                $cblock = [];
                for ($i = $last_good; $i <= $largest_height; $i++) {
                    $data = peer_post($url."getBlock", ["height" => $i]);
                    if ($data === false) {
                        _log("Could not get block from $host - $i");
                        $invalid = true;
                        break;
                    }
                    $cblock[$i] = $data;
                }
                // check if the block mining data is correct
                for ($i = $last_good + 1; $i <= $largest_height; $i++) {
                	_log("checking block $i",3);
//                    if (($i-1)%3==2&&$cblock[$i - 1]['height']<80458) {
//                        continue;
//                    }
                    if (!$block->mine(
                        $cblock[$i]['public_key'],
                        $cblock[$i]['nonce'],
                        $cblock[$i]['argon'],
                        $cblock[$i]['difficulty'],
                        $cblock[$i]['id'],
                        $cblock[$i]['height'],
                        $cblock[$i]['date']
                    )) {
                        $invalid = true;
                        break;
                    }
                }
            }
            // if the blockchain proves ok, delete until the last block
            if ($invalid == false) {
                _log("Changing fork, deleting $last_good", 1);
                $res=$block->delete($last_good);
                if($res==false){
                    $block_parse_failed=true;
                    break;
                }
                $current = $block->current();
                $data = $current;
            }
        }
        // if current still doesn't match the data, something went wrong
        if ($data['id'] != $current['id']) {
            if($largest_size==1){
                //blacklisting the peer if it's the largest height on a broken blockchain
	            _log("blacklisting the peer $url because it is the largest height on a broken blockchain");
                Peer::blacklistBroken($host, "Broken blockchain");
            }
//            continue;
        }
        // start syncing all blocks
        while ($current['height'] < $largest_height) {
            $data = peer_post($url."getBlocks", ["height" => $current['height'] + 1]);

            if ($data === false) {
                _log("Could not get blocks from $host - height: $current[height]");
                break;
            }
            $good_peer = true;
            foreach ($data as $b) {
                $b['id'] = san($b['id']);
                $b['height'] = san($b['height']);
                
                if (!$block->check($b)) {
                    $block_parse_failed=true;
                    _log("Block check: could not add block - $b[id] - $b[height]", 1);
                    $good_peer = false;
                    $failed_syncs++;
                    break;
                }
                $res = $block->add(
                    $b['height'],
                    $b['public_key'],
                    $b['nonce'],
                    $b['data'],
                    $b['date'],
                    $b['signature'],
                    $b['difficulty'],
                    $b['reward_signature'],
                    $b['argon'],
                    $b['prev_block_id']
                );
                if (!$res) {
                    $block_parse_failed=true;
                    _log("Block add: could not add block - $b[id] - $b[height]", 1);
                    $good_peer = false;
                    $failed_syncs++;
                    break;
                }

                _log("Synced block from $host - $b[height] $b[difficulty]", 3);
            }
            if (!$good_peer) {
                break;
            }
            $current = $block->current();
        }
        if ($good_peer) {
            break;
        }
        if ($failed_syncs>5) {
            break;
        }
    }

    if ($block_parse_failed==true||$argv[1]=="resync") {
        $last_resync=$db->single("SELECT val FROM config WHERE cfg='last_resync'");
        if ($last_resync<time()-(3600*24)||$argv[1]=="resync") {
            if ((($current['date']<time()-(3600*72)) && $_config['auto_resync']!==false) || $argv[1]=="resync") {
                $db->fkCheck(false);
                $tables = ["accounts", "transactions", "mempool", "masternode","blocks"];
                foreach ($tables as $table) {
                    $db->truncate($table);
                }
                $db->fkCheck(true);
                
                $resyncing=true;
                $db->run("UPDATE config SET val=0 WHERE cfg='sanity_sync'", [":time" => $t]);
                
                _log("Exiting sanity, next sanity will resync from scratch.");

                @unlink(SANITY_LOCK_PATH);
                exit;
            } elseif ($current['date']<time()-(3600*24)) {
                _log("Removing 200 blocks, the blockchain is stale.");
                $block->pop(200);

                $resyncing=true;
            }
        }
    }


    $db->run("UPDATE config SET val=0 WHERE cfg='sanity_sync'", [":time" => $t]);
}



    $resyncing=false;
    if ($block_parse_failed==true&&$current['date']<time()-(3600*24)) {
        _log("Rechecking reward transactions");
        $current = $block->current();
        $rwpb=$db->single("SELECT COUNT(1) FROM transactions WHERE type=0 AND message=''");
        if ($rwpb!=$current['height']) {
            $failed=$db->single("SELECT blocks.height FROM blocks LEFT JOIN transactions ON transactions.block=blocks.id and transactions.type=0 and transactions.message='' WHERE transactions.height is NULL ORDER by blocks.height ASC LIMIT 1");
            if ($failed>1) {
                _log("Found failed block - $faield");
                $block->delete($failed);
                $block_parse_failed==false;
            }
        }
    }
   

// deleting mempool transactions older than 14 days
$db->run("DELETE FROM `mempool` WHERE `date` < ".DB::unixTimeStamp()."-(3600*24*14)");


//rebroadcasting local transactions
if ($_config['sanity_rebroadcast_locals'] == true && $_config['disable_repropagation'] == false) {
    $r = $db->run(
        "SELECT id FROM mempool WHERE height<:current and peer='local' order by `height` asc LIMIT 20",
        [":current" => $current['height']]
    );
    _log("Rebroadcasting local transactions - ".count($r), 3);
    foreach ($r as $x) {
        $x['id'] = escapeshellarg(san($x['id'])); // i know it's redundant due to san(), but some people are too scared of any exec
	    $dir = __DIR__;
	    system("php $dir/propagate.php transaction $x[id]  > /dev/null 2>&1  &");
        $db->run(
            "UPDATE mempool SET height=:current WHERE id=:id",
            [":id" => $x['id'], ":current" => $current['height']]
        );
    }
}

//rebroadcasting transactions
if ($_config['disable_repropagation'] == false) {
    $forgotten = $current['height'] - $_config['sanity_rebroadcast_height'];
    $r1 = $db->run(
        "SELECT id FROM mempool WHERE height<:forgotten ORDER by val DESC LIMIT 10",
        [":forgotten" => $forgotten]
    );
    // getting some random transactions as well
    $r2 = $db->run(
        "SELECT id FROM mempool WHERE height<:forgotten ORDER by ".DB::random()." LIMIT 10",
        [":forgotten" => $forgotten]
    );
    $r=array_merge($r1, $r2);


    _log("Rebroadcasting external transactions - ".count($r),3);

    foreach ($r as $x) {
        $x['id'] = escapeshellarg(san($x['id'])); // i know it's redundant due to san(), but some people are too scared of any exec
	    $dir = __DIR__;
	    system("php $dir/propagate.php transaction $x[id]  > /dev/null 2>&1  &");
        $db->run("UPDATE mempool SET height=:current WHERE id=:id", [":id" => $x['id'], ":current" => $current['height']]);
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
    $data = peer_post($url."ping", [], 5);
    if ($data === false) {
    	_log("blakclist peer ".$x['hostname']." because it is not answering");
        Peer::blacklist($x['id'],"Not answer");
        _log("Random reserve peer test $x[hostname] -> FAILED");
    } else {
        _log("Random reserve peer test $x[hostname] -> OK");
	    Peer::clearFails($x['id']);
    }
}



//recheck the last blocks
if ($_config['sanity_recheck_blocks'] > 0) {
    _log("Rechecking blocks",3);
    $blocks = [];
    $all_blocks_ok = true;
    $start = $current['height'] - $_config['sanity_recheck_blocks'];
    if ($start < 2) {
        $start = 2;
    }
    $r = $db->run("SELECT * FROM blocks WHERE height>=:height ORDER by height ASC", [":height" => $start]);
    foreach ($r as $x) {
        $blocks[$x['height']] = $x;
        $max_height = $x['height'];
    }

    for ($i = $start + 1; $i <= $max_height; $i++) {
        $data = $blocks[$i];

        $key = $db->single("SELECT public_key FROM accounts WHERE id=:id", [":id" => $data['generator']]);

        if (!$block->mine(
            $key,
            $data['nonce'],
            $data['argon'],
            $data['difficulty'],
	        $data['id'],
	        $data['height'],
            $data['date']
        )) {
            $db->run("UPDATE config SET val=1 WHERE cfg='sanity_sync'");
            _log("Invalid block detected. Deleting everything after $data[height] - $data[id]");
            sleep(10);
            $all_blocks_ok = false;
            $block->delete($i);

            $db->run("UPDATE config SET val=0 WHERE cfg='sanity_sync'");
            break;
        }
    }
    if ($all_blocks_ok) {
        echo "All checked blocks are ok\n";
    }
}

// not too often to not cause load
/*if (rand(0, 10)==1) {
    // after 10000 blocks, clear asset internal transactions
//    $db->run("DELETE FROM transactions WHERE (version=".TX_VERSION_ASSETS_INTERNAL_GENERATE_ID." or version=".TX_VERSION_ASSETS_INTERNAL_MARKET." or version=".TX_VERSION_ASSETS_DISTRIBUTE_DIVIDENDS.") AND height<:height", [":height"=>$current['height']-10000]);

    // remove market orders that have been filled, after 10000 blocks
    $r=$db->run("SELECT id FROM assets_market WHERE val_done=val or status=2");
    foreach ($r as $x) {
        $last=$db->single("SELECT height FROM transactions WHERE (public_key=:id or dst=:id2) ORDER by height DESC LIMIT 1", [":id"=>$x['id'], ":id2"=>$x['id']]);
        if ($current['height']-$last>10000) {
            $db->run("DELETE FROM assets_market WHERE id=:id", [":id"=>$x['id']]);
        }
    }
}*/

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

/*        if ($blacklist) {
            echo "Blacklisting masternode $x[public_key]\n";
            $val='0.00000000';
            $fee=TX_MIN_FEE;
	        $fee=number_format($fee, 8, ".", "");
            $date=time();
            $version=TX_VERSION_MASTERNODE_BLACKLISTING;
            $msg=san($x['public_key']);
            $address=$acc->get_address($x['public_key']);
            $public_key=$_config['masternode_public_key'];
            $private_key=$_config['masternode_voting_private_key'];
            $info=$val."-".$fee."-".$address."-".$msg."-$version-".$public_key."-".$date;
            _log("TX info: ".$info);
            $signature=ec_sign($info, $private_key);


            $transaction = [
                "src"        => $acc->get_address($_config['masternode_public_key']),
                "val"        => $val,
                "fee"        => $fee,
                "dst"        => $address,
                "public_key" => $public_key,
                "date"       => $date,
                "version"    => $version,
                "message"    => $msg,
                "signature"  => $signature,
            ];

            $hash = $trx->hash($transaction);
            $transaction['id'] = $hash;
            if (!$trx->check($transaction)) {
                print("Blacklist transaction signature failed\n");
            }
            $res = $db->single("SELECT COUNT(1) FROM mempool WHERE id=:id", [":id" => $hash]);
            if ($res != 0) {
                print("Blacklist transaction already in mempool\n");
            }
            $trx->add_mempool($transaction, "local");
            $hash=escapeshellarg(san($hash));
	        $dir = __DIR__;
            system(RUN_ENV . "php $dir/propagate.php transaction $hash > /dev/null 2>&1  &");
            echo "Blacklist Hash: $hash\n";
        }*/
    }
}



Nodeutil::cleanTmpFiles();



_log("Finishing sanity",2);

@unlink(SANITY_LOCK_PATH);
