<?php

class Sync extends Task
{

	static $name = "sync";
	static $title = "Sync";

	static $run_interval = 30;

	static function isEnabled() {
		global $_config;
		return !isset($_config["sync_disabled"]) || $_config["sync_disabled"]==0;
	}

	static function enable() {
		global $db;
		$db->setConfig("sync_disabled", 0);
	}

	static function disable() {
		global $db;
		$db->setConfig("sync_disabled", 1);
	}

	static function processNew() {
		global $db, $_config;
		ini_set('memory_limit', '2G');
		$t1 = microtime(true);

		$now = time();
		$sync_last = Config::getVal('sync_last');
		_log("Check sync last: $sync_last elapsed = ".($now - $sync_last), 3);
		if(Config::isSync() && ($now - $sync_last > 60*60*2)) {
			_log("Set sync = 0");
			Config::setSync(0);
			return;
		}

        $height = Block::getHeight();
        if($height >= DELETE_CHAIN_HEIGHT) {
            $diff = $height - DELETE_CHAIN_HEIGHT;
            if($diff > 0) {
                _log("Pop $diff blocks at demand");
                Block::pop($diff);
                return;
            }
        }

		Peer::deleteDeadPeers();
		Peer::blacklistInactivePeers();
		Peer::blacklistIncompletePeers();
		Peer::resetResponseTimes();

		$res = self::checkPeers();
        if(!$res) {
            _log("Wait to initialize peers");
            return;
        }

        Nodeutil::runAtInterval("checkBlocks", 60*10, function() {
            Nodeutil::runSingleProcess("php " .ROOT."/cli/util.php check-blocks");
        });
        Nodeutil::runAtInterval("compareCheckPoints", 60*60, function() {
            Nodeutil::runSingleProcess("php " .ROOT."/cli/util.php compare-check-points");
        });
        Nodeutil::runAtInterval("recheckLastBlocks", 60*10, function() {
            Nodeutil::runSingleProcess("php " .ROOT."/cli/util.php recheck-last-blocks");
        });


//		Config::setVal("blockchain_invalid", 0);
		Mempool::deleteOldMempool();
//		NodeSync::checkForkedBlocks();
        NodeSync::verifyLastBlocks(); //switch to run on hour
		NodeSync::syncBlocks();
		$peersForSync = Peer::getValidPeersForSync();
		$nodeSync = new NodeSync($peersForSync);
		$nodeSync->calculateNodeScoreNew();


		//rebroadcasting local transactions
		$current = Block::current();
        $r = Mempool::getForRebroadcast($current['height']);
        _log("Rebroadcasting local transactions - ".count($r), 1);
        foreach ($r as $x) {
            Propagate::transactionToAll($x['id']);
        }

		//rebroadcasting transactions
        $forgotten = $current['height'] - 30;
        $r=Mempool::getForgotten($forgotten);
        _log("Rebroadcasting external transactions - ".count($r),1);
        foreach ($r as $x) {
            Propagate::transactionToAll($x['id']);
        }


		Nodeutil::cleanTmpFiles();
		Minepool::deleteOldEntries();
		Cache::clearOldFiles();

        $cmd='find '.ROOT.'/tmp -name "*.lock" ! -name "cli-*.lock" -mmin +1 -exec rm -rf {} +';
        _log("Remove lock files cmd=$cmd", 3);
        shell_exec($cmd);

		_log("Finishing sync",3);
		$t2 = microtime(true);
		Config::setVal("sync_last", time());
		_log("Sync process finished in time ".round($t2-$t1, 3));
	}

	static function process() {
		self::processNew();
	}

	static function checkPeers() {
		global $_config, $db;
		$total_peers = Peer::getCount(false);
		_log("Total peers: ".$total_peers, 3);
		$peered = [];
		// if we have no peers, get the seed list from the official site
		if ($total_peers == 0) {

            $dir = ROOT."/cli";
            $cmd = "php $dir/util.php init-peers";
            Nodeutil::runSingleProcess($cmd);
            $db->setConfig('node_score', 0);
            return false;
		}
        return true;
	}

}
