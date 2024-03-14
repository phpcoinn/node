<?php


class Peer
{

	public const PRELOAD_LIST = REMOTE_PEERS_LIST_URL;
	public const PEER_PING_MAX_MINUTES = 2;
	public const MINIMUM_PEERS_REQUIRED = 2;

	static function getCount($live=false) {
		global $db;
		$sql="select count(*) as cnt from peers where blacklisted < ".DB::unixTimeStamp();
		if($live) {
			$sql.=" AND ping >".DB::unixTimeStamp()."-".(60*self::PEER_PING_MAX_MINUTES);
		}
		$row = $db->row($sql);
		return $row['cnt'];
	}

	static function getCountAll() {
		global $db;
		$sql="select count(*) as cnt from peers";
		$row = $db->row($sql);
		return $row['cnt'];
	}

	static function getAll($sorting="") {
		global $db;
		$sql="select * from peers $sorting";
		$rows = $db->run($sql);
		return $rows;
	}

	static function getActive($limit=100) {
		global $db;
		$sql="select * from peers WHERE  blacklisted < ".DB::unixTimeStamp()." ORDER by ".DB::random().(!empty($limit) ? " LIMIT $limit" : "");
		$rows = $db->run($sql);
		return $rows;
	}

	static function getPeersForSync($limit = null, $random=false, $order="response_time/response_cnt") {
		global $db;
		$sql="select * from peers 
			where blacklisted < ".DB::unixTimeStamp()."
			  and ping > ".DB::unixTimeStamp()."- 60*".self::PEER_PING_MAX_MINUTES."
			  and response_cnt>0 ";
		if($random) {
			$sql.=" order by ".DB::random();
		} else {
			$sql.=" order by $order";
		}
		if(!empty($limit)) {
			$sql.=" limit $limit";
		}
		$rows = $db->run($sql);
		return $rows;
	}

	static function findPeers($blacklisted, $live, $limit = null, $order="response_time/response_cnt") {
		global $db;

		$sql="select * from peers where 1=1 ";
		if($blacklisted === true) {
			$sql.=" and blacklisted > ".DB::unixTimeStamp();
		} else if ($blacklisted === false) {
			$sql.=" and blacklisted < ".DB::unixTimeStamp();
		}
		if($live === true) {
			$sql.= "and ping > ".DB::unixTimeStamp()."- 60*".self::PEER_PING_MAX_MINUTES;
		} else if ($live === false) {
			$sql.= "and ping < ".DB::unixTimeStamp()."- 60*".self::PEER_PING_MAX_MINUTES;
		}

		if(!empty($order)) {
			$sql.=" order by $order";
		}

		if(!empty($limit)) {
			$sql.=" limit $limit";
		}

		$rows = $db->run($sql);
		return $rows;
	}

	static function getPeersForPropagate($limit = null, $random=false) {
		return self::findPeers(false, null);
	}

    static function getLimitedPeersForPropagate() {
        global $_config;
        $peers_limit = $_config['peers_limit'];
        if(empty($peers_limit)) {
            $peers_limit = 500;
        }
        $peers =  self::findPeers(false, null, $peers_limit, DB::unixTimeStamp()." - ping desc");
        _log("getLimitedPeersForPropagate found=".count($peers), 5);
        return $peers;
    }

    static function getPeersForPropagate2($limit, $ignoreList = [],$internal=false, $add_cond="") {
        global $db;
        $hostnames = [];
        if(!empty($ignoreList)) {
            foreach ($ignoreList as $hostname) {
                $hostnames[]="'$hostname'";
            }
            $hostnames = implode(",", $hostnames);
        } else {
            $hostnames = "''";
        }
        $cond= "";
        if($internal) {
            $cond = " and p.hostname like '%phpcoin%' ";
        }
        $sql="select * from peers p 
            where p.blacklisted < unix_timestamp() and p.hostname not in ($hostnames)
            $cond
            $add_cond
            order by response_time/response_cnt " . (empty($limit) ? "" : " limit $limit");
        $rows = $db->run($sql);
        return $rows;
    }


    static function getPeersForMasternode($limit = null) {
		global $db;
		$sql="select * from peers p WHERE (p.blacklisted < ".DB::unixTimeStamp()." or p.generator is not null or p.miner is not null )
			and ping > ".DB::unixTimeStamp()."- 60*".self::PEER_PING_MAX_MINUTES."
			order by ".DB::random();
		if(!empty($limit)) {
			$sql.= " limit $limit";
		}
		$rows = $db->run($sql);
		return $rows;
	}

	static function delete($id) {
		global $db;
		$sql="delete from peers where id=:id";
		$db->run($sql, ["id"=>$id]);
	}

	static function validate($peer) {

		$bad_peers = ["127.", "localhost", "10.", "192.168.","172.16.","172.17.",
			"172.18.","172.19.","172.20.","172.21.","172.22.","172.23.","172.24.",
			"172.25.","172.26.","172.27.","172.28.","172.29.","172.30.","172.31."];

		$hostname = filter_var($peer, FILTER_SANITIZE_URL);

		$tpeer=str_replace(["https://","http://","//"], "", $hostname);
		foreach ($bad_peers as $bp) {
			if (strpos($tpeer, $bp)===0 && !DEVELOPMENT) {
				_log("bad peer: ", $peer);
				return false;
			}
		}

		if (!filter_var($hostname, FILTER_VALIDATE_URL) && !DEVELOPMENT) {
			return false;
		}

		return true;
	}

	static function validateIp($ip) {
		if(!DEVELOPMENT) {
			$ip = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_IPV4);
		}
		return $ip;
	}

	static function getInitialPeers() {
		global $_config;
		$arrContextOptions=array(
			"ssl"=>array(
				"verify_peer"=>!DEVELOPMENT,
				"verify_peer_name"=>!DEVELOPMENT,
			),
		);
		$list = file_get_contents(self::PRELOAD_LIST, false, stream_context_create($arrContextOptions));
		$list = explode(PHP_EOL, $list);
		$peerList = [];
		foreach ($list as $item) {
			if(strlen(trim($item))>0) {
				$peerList[]=$item;
			}
		}
		$initial_peer_list = $_config['initial_peer_list'];
		return array_unique(array_merge($peerList, $initial_peer_list));
	}

	static function deleteAll() {
		global $db;
		$db->run('delete from peers');
	}

	static function insert($ip,$hostname) {
		global $db;
        $peer = $db->run("select * from peers where ip=:ip or hostname=:hostname",[":ip" => $ip, ":hostname"=>$hostname]);
        _log("Peer:insert: find peer with ip=$ip or hostname=$hostname peer=".json_encode($peer), 5);
        if($peer) {
            $res = $db->run("update peers p set p.hostname = :hostname, p.ip = :ip where
                    p.hostname = :hostname2 or p.ip = :ip2", [":ip" => $ip, ":hostname"=>$hostname, ":ip2"=>$ip, ":hostname2"=>$hostname]);
            _log("Peer:insert: updated new peer res=$res", 5);
        } else {
            $res = $db->run("INSERT INTO peers
                (hostname, ping, ip)
                values  (:hostname, ".DB::unixTimeStamp().", :ip)", [":ip" => $ip, ":hostname" => $hostname]);
            _log("Peer:insert: peer not exists $ip $hostname res=$res", 4);
        }
		return $res;
	}

	static function getInfo() {
		global $_config;
		$appsHash = null;
		if(FEATURE_APPS) {
			$appsHashFile = Nodeutil::getAppsHashFile();
			$appsHash = @file_get_contents($appsHashFile);
		}
		$generator = isset($_config['generator_public_key']) && $_config['generator'] ? Account::getAddress($_config['generator_public_key']) :  null;
		$miner = isset($_config['miner_public_key']) && $_config['miner'] ? Account::getAddress($_config['miner_public_key']) :  null;
		$masternode = isset($_config['masternode_public_key']) && $_config['masternode'] ? Account::getAddress($_config['masternode_public_key']) : null;
		$current = Cache::get("current", function() {
			return Block::current();
		});
		$dapps_data = Cache::get("dapps_data", function() {
			return Dapps::getLocalData();
		});
//		_log("Cache: dapps_data = ".json_encode($dapps_data), 5);
		return [
			"height" => $current['height'],
			"appshash" => $appsHash,
			"score"=>$_config['node_score'],
			"version"=> VERSION . "." . BUILD_VERSION,
			"miner"=>$miner,
			"generator"=>$generator,
			"masternode"=>$masternode,
			"block"=>$current['id'],
			"hostname"=>$_config['hostname'],
			"dapps_id"=>$dapps_data['dapps_id'],
			"dapps_hash"=>$dapps_data['dapps_hash']
		];
	}

	static function blacklist($id, $reason = '', $min=1) {
		global $db;
		$hostname = $db->single("select hostname from peers where id = :id", [":id"=>$id]);
        if(!$hostname) {
            return;
        }
		_log("Blacklist $hostname reason=$reason");
		$db->run(
			"UPDATE peers SET fails=fails+1, blacklisted=".DB::unixTimeStamp()."+((fails+1)*60*$min), 
				blacklist_reason=:blacklist_reason WHERE id=:id",
			[":id" => $id, ':blacklist_reason'=>$reason]
		);
	}

	static function blacklistStuck($id, $reason = '') {
		global $db;
		$hostname = $db->single("select hostname from peers where id = :id", [":id"=>$id]);
		_log("Blacklist peer $hostname stuck reason=$reason");
		$db->run(
			"UPDATE peers SET stuckfail=stuckfail+1, blacklisted=".DB::unixTimeStamp()."+60*10,
			    blacklist_reason=:reason WHERE id=:id",
			[":id" => $id, ":reason" => $reason]
		);
	}

	static function blacklistBroken($host, $reason = '') {
		global $db;
		_log("Blacklist peer broken $host reason=$reason");
		$db->run("UPDATE peers SET blacklisted=".DB::unixTimeStamp()."+1800 
			blacklist_reason=:reason WHERE hostname=:host LIMIT 1",[':host'=>$host, ':reason'=>$reason]);
	}

	static function getPeers() {
		global $db;
		return $db->run("SELECT ip,hostname,height FROM peers WHERE blacklisted<".DB::unixTimeStamp()." ORDER by ".DB::random());
	}

	static function findByIp($ip) {
		global $db;
		$x = $db->row(
			"SELECT id,hostname FROM peers WHERE ip=:ip",
			[":ip" => $ip]
		);
		return $x;
	}


	static function cleanBlacklist() {
		global $db;
		$db->run("UPDATE peers SET blacklisted=0, fails=0, stuckfail=0");
	}

	static function deleteDeadPeers() {
		global $db;
		$db->run("DELETE from peers WHERE fails>100 OR stuckfail>100 OR (".DB::unixTimeStamp()."- ping > 60*60*24)");
	}

	static function blacklistInactivePeers() {
		global $db;
		$db->run(
			"UPDATE peers SET fails=fails+1, blacklisted=".DB::unixTimeStamp()."+((fails+1)*60*1), 
				blacklist_reason=:blacklist_reason where ".DB::unixTimeStamp()."-ping > 60*60*2",
			[':blacklist_reason'=>'Inactive']
		);
	}

	static function blacklistIncompletePeers() {
		global $db;
		$db->run(
			"UPDATE peers SET fails=fails+1, blacklisted=".DB::unixTimeStamp()."+((fails+1)*60*1), 
				blacklist_reason=:blacklist_reason where height is null or block_id is null",
			[':blacklist_reason'=>'Incomplete']
		);
	}

	static function resetResponseTimes() {
		global $db;
		$db->run("update peers set response_cnt = 0, response_time = 0 where response_cnt > 1000 or response_time > 3600");
	}

	static function clearStuck($id) {
		global $db;
		$db->run("UPDATE peers SET stuckfail=0 WHERE id=:id", [":id" => $id]);
	}

	static function clearBlacklist($id) {
		global $db;
        $db->run("UPDATE peers SET fails=0, blacklisted=".DB::unixTimeStamp().", stuckfail=0, blacklist_reason = null WHERE id=:id", [":id" => $id]);
	}

	static function clearFails($id) {
		global $db;
		$db->run("UPDATE peers SET fails=0, blacklist_reason = null WHERE id=:id", [":id" => $id]);
	}

	static function getSingle($hostname, $ip) {
		global $db;
		return $db->single(
			"SELECT COUNT(1) FROM peers WHERE hostname=:hostname AND ip=:ip",
			[":hostname" => $hostname, ":ip" => $ip]
		);
	}

	static function deleteByIp($ip) {
		global $db;
		$db->run("DELETE FROM peers WHERE ip=:ip", [":ip" => $ip]);
	}

	static function getByIp($ip) {
		global $db;
		return $db->row("SELECT * FROM peers WHERE ip=:ip", [":ip" => $ip]);
	}

	static function updateInfo($id, $info) {
		global $db;
		$miner = isset($info['miner']) && !empty($info['miner']) ? $info['miner'] : null ;
		$generator = isset($info['generator']) && !empty($info['generator']) ? $info['generator'] : null;
		$masternode = isset($info['masternode']) && !empty($info['masternode']) ? $info['masternode'] : null ;
//		_log("PeerSync: update peer data $id info=".json_encode($info));
		$db->run("UPDATE peers SET ping=".DB::unixTimeStamp().", height=:height, block_id=:block_id, appshash=:appshash, score=:score, version=:version,  
				miner=:miner, generator=:generator, masternode=:masternode
				WHERE id=:id",
			[":id" => $id, ':height'=>$info['height'], ':appshash'=>$info['appshash'],
				':score'=>$info['score'], ':version' => $info['version'],
				':miner' => $miner, ':generator' => $generator, ':masternode'=>$masternode,
				':block_id' => $info['block']]);
	}

	static function updatePeerInfo($ip, $info) {
		global $db;
		_log("PeerSync: Peer request: update info from $ip ".json_encode($info), 3);
		if(empty($info['height']) || empty($info['block'])) {
			_log("updatePeerInfo: EMPTY HEIGHT or BLOCK - peer not updated ", 3);
		}
		$db->run("UPDATE peers SET ping=".DB::unixTimeStamp().", height=:height, block_id=:block_id, appshash=:appshash, score=:score, version=:version,  
				miner=:miner, generator=:generator, masternode=:masternode, hostname=:hostname, dapps_id =:dapps_id, dappshash =:dapps_hash
				WHERE ip=:ip",
			[":ip" => $ip, ':height'=>$info['height'], ':appshash'=>@$info['appshash'],
				':score'=>$info['score'], ':version' => $info['version'],
				':miner' => @$info['miner'], ':generator' => @$info['generator'], ':masternode'=>@$info['masternode'],
				':block_id' => $info['block'], ":hostname"=>$info['hostname'], ":dapps_id"=>@$info['dapps_id'], ":dapps_hash"=>@$info['dapps_hash']]);
	}

	static function storeResponseTime($hostname, $connect_time) {
		global $db;
        if(empty($connect_time)) {
            return;
        }
		$res = $db->run("update peers set ping=".DB::unixTimeStamp().",
			response_cnt=response_cnt+1, response_time=response_time+:time 
			where hostname like :hostname",
			[ ":hostname"=>"%$hostname%",":time"=>$connect_time]);
	}

	public static function findByHostname($hostName)
	{
		global $db;
		$x = $db->row(
			"SELECT id,hostname,ip FROM peers WHERE hostname=:hostname",
			[":hostname" => $hostName]
		);
		return $x;
	}

	public static function updateTimeInfo($hostname, $connect_time)
	{
		global $db;
		if(empty($connect_time)) {
			return;
		}
		$sql="update peers 
		set response_cnt=response_cnt+1, response_time=response_time+:time 
		where hostname = :hostname";
		$res = $db->run($sql, [":time"=>$connect_time, ":hostname"=>$hostname]);
	}

	public static function updateHeight($ip, $data)
	{
		global $db;
		$height = $data['height'];
		$block_id = $data['id'];
		_log("Sync: update peer height ip=$ip height=$height block=$block_id", 5);
		$sql="update peers set ping = ".DB::unixTimeStamp().", height = :height, block_id=:block_id where ip=:ip";
		$res =$db->run($sql, [":height"=>$height, ":ip"=>$ip, ":block_id"=>$block_id]);
		return $res;
	}

	public static function findByDappsId($dapps_id)
	{
		global $db;
		$sql = "select * from peers p where p.dapps_id = :dapps_id";
		return $db->row($sql, [":dapps_id"=>$dapps_id]);
	}

	public static function updateDappsId($ip, $dapps_id, $dapps_hash)
	{
		global $db;
		$sql = "update peers set dapps_id = :dapps_id, dappshash = :dappshash where ip = :ip";
		return $db->run($sql, [":dapps_id"=>$dapps_id, ":ip"=>$ip, ":dappshash" => $dapps_hash]);
	}

	public static function getDappsIdPeer($dapps_id)
	{
		global $db;
		$sql = "select * from peers p where p.dapps_id = :dapps_id";
		$row = $db->row($sql, [":dapps_id"=>$dapps_id]);
		return $row;
	}

	public static function deleteBlacklisted() {
		global $db;
		$sql="delete from peers where blacklisted > ".DB::unixTimeStamp()." and substr(blacklist_reason, 1, 16) = 'Invalid hostname'";
		$db->run($sql);
	}

	static function deleteWrongHostnames() {
		global $db;
		$sql="delete from peers where hostname not like ".DB::concat("'%'", "ip", "'%'")."
			and blacklisted > ".DB::unixTimeStamp()." and substr(blacklist_reason, 1, 16) = 'Invalid hostname'";
		$db->run($sql);
	}

	static function getDappsPeers() {
		global $db;
		$sql="select * from peers p where p.dapps_id is not null";
		return $db->run($sql);
	}

	public static function getPeerByType($address, $type)
	{
		global $db;
		$sql="select * from peers p where p.$type = :address limit 1";
		$row = $db->row($sql, [":address"=>$address]);
		return $row;
	}


	static function getMaxBuildNumber() {
		global $db;
		$sql="select max(version) from peers";
		$max_version = $db->single($sql);
		$arr = explode(".",$max_version);
		$build_number = array_pop($arr);
		return $build_number;
	}

	static function getValidPeersForSync() {
		global $db;
		$sql="select p.*
		from peers p
		where p.height > (select max(height) from blocks)
		  and p.blacklisted < ".DB::unixTimeStamp()."
		  and ".DB::unixTimeStamp()." - p.ping < 2 * 60
		  and p.height not in (
		    select fp.height
		    from peers fp
		    group by fp.height
		    having count(distinct fp.block_id) > 1
		)
		  and p.height in (
		    select mp.height
		    from peers mp
		    group by mp.height
		    having count(mp.id) > 1
		)
		  and (p.height < (
		    select p.height
		    from peers p
		    group by p.height
		    having count(distinct p.block_id) > 1
		      order by p.height limit 1
		) or (
		    select p.height
		    from peers p
		    group by p.height
		    having count(distinct p.block_id) > 1
		    order by p.height limit 1
		       ) is null)
		order by p.height asc, p.response_time / p.response_cnt asc";
		return $db->run($sql);
	}

    static function getMiningNodes($limit = 10) {
        global $db;
        $sql="select * from peers where miner is not null 
            and blacklisted < ".DB::unixTimeStamp()." order by response_time/response_cnt
            limit $limit";
        $rows = $db->run($sql);
        $list = [];
        foreach ($rows as $row) {
            $list[]=$row['hostname'];
        }
        return $list;
    }

    static function updatePing($id) {
        global $db;
        $sql="update peers set ping = UNIX_TIMESTAMP() where id = :id";
        $db->run($sql, [":id"=>$id]);
    }
}
