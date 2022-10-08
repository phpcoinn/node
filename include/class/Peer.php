<?php


class Peer
{

	public const PRELOAD_LIST = REMOTE_PEERS_LIST_URL;
	public const MINIMUM_PEERS_REQUIRED = 2;

	static function getCount($live=false) {
		global $db;
		$sql="select count(*) as cnt from peers where blacklisted < ".DB::unixTimeStamp();
		if($live) {
			$sql.=" AND ping >".DB::unixTimeStamp()."-86400";
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
		$sql="select * from peers WHERE  blacklisted < ".DB::unixTimeStamp()." ORDER by ".DB::random()." LIMIT :limit";
		$rows = $db->run($sql, ["limit"=>$limit]);
		return $rows;
	}

	static function getPeersForSync($limit = null, $random=false) {
		global $db;
		$sql="select * from peers 
			where blacklisted < ".DB::unixTimeStamp()."
			  and ping > ".DB::unixTimeStamp()."- 60*2
			  and response_cnt>0 ";
		if($random) {
			$sql.=" order by ".DB::random();
		} else {
			$sql.=" order by response_time/response_cnt";
		}
		if(!empty($limit)) {
			$sql.=" limit $limit";
		}
		$rows = $db->run($sql);
		return $rows;
	}

	static function getPeersForMasternode($limit = null) {
		global $db;
		$sql="select * from peers p WHERE (p.blacklisted < ".DB::unixTimeStamp()." or p.generator is not null or p.miner is not null )
			and ping > ".DB::unixTimeStamp()."- 60*2
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
			$ip = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
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
		if($db->isSqlite()) {
			$row = $db->run("select * from peers where ip=:ip",[":ip" => $ip]);
			if($row) {
				$db->run("update peers set hostname=:hostname where ip=:ip", [":ip" => $ip, ":hostname" => $hostname]);
			} else {
				$res = $db->run(
					"INSERT INTO peers 
					    (hostname, ping, ip) 
					    values  (:hostname, ".DB::unixTimeStamp().", :ip) ",
					[":ip" => $ip, ":hostname" => $hostname]
				);
			}
		} else {
			$res = $db->run(
				"INSERT ignore INTO peers 
				    (hostname, ping, ip) 
				    values  (:hostname, ".DB::unixTimeStamp().", :ip) 
					ON DUPLICATE KEY UPDATE hostname=:hostname2",
				[":ip" => $ip, ":hostname2" => $hostname, ":hostname" => $hostname]
			);
		}
		return $res;
	}

	static function getInfo() {
		global $_config;
		$appsHashFile = Nodeutil::getAppsHashFile();
		$appsHash = @file_get_contents($appsHashFile);
		$generator = isset($_config['generator_public_key']) && $_config['generator'] ? Account::getAddress($_config['generator_public_key']) :  null;
		$miner = isset($_config['miner_public_key']) && $_config['miner'] ? Account::getAddress($_config['miner_public_key']) :  null;
		$masternode = isset($_config['masternode_public_key']) && $_config['masternode'] ? Account::getAddress($_config['masternode_public_key']) : null;
		$current = Cache::get("current", function() {
			return Block::current();
		});
		_log("Cache: current = ".json_encode($current), 5);
		return [
			"height" => $current['height'],
			"appshash" => $appsHash,
			"score"=>$_config['node_score'],
			"version"=> VERSION . "." . BUILD_VERSION,
			"miner"=>$miner,
			"generator"=>$generator,
			"masternode"=>$masternode,
			"block"=>$current['id'],
			"hostname"=>$_config['hostname']
		];
	}

	static function blacklist($id, $reason = '') {
		global $db;
		$hostname = $db->single("select hostname from peers where id = :id", [":id"=>$id]);
		_log("Blacklist peer $hostname reason=$reason");
		$db->run(
			"UPDATE peers SET fails=fails+1, blacklisted=".DB::unixTimeStamp()."+((fails+1)*60*5), 
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

	static function getPeersForPropagate() {
		global $db;
		$r = $db->run("SELECT * FROM peers WHERE blacklisted < ".DB::unixTimeStamp());
		return $r;
	}

	static function findByIp($ip) {
		global $db;
		$x = $db->row(
			"SELECT id,hostname FROM peers WHERE blacklisted<".DB::unixTimeStamp()." AND ip=:ip",
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
		$db->run("DELETE from peers WHERE fails>100 OR stuckfail>100");
	}

	static function clearStuck($id) {
		global $db;
		$db->run("UPDATE peers SET stuckfail=0 WHERE id=:id", [":id" => $id]);
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
		$db->run("UPDATE peers SET ping=".DB::unixTimeStamp().", height=:height, block_id=:block_id, appshash=:appshash, score=:score, version=:version,  
				miner=:miner, generator=:generator, masternode=:masternode, hostname=:hostname
				WHERE ip=:ip",
			[":ip" => $ip, ':height'=>$info['height'], ':appshash'=>$info['appshash'],
				':score'=>$info['score'], ':version' => $info['version'],
				':miner' => $info['miner'], ':generator' => $info['generator'], ':masternode'=>$info['masternode'],
				':block_id' => $info['block'], ":hostname"=>$info['hostname']]);
	}

	static function storePing($url, $curl_info) {
		$info = parse_url($url);
		$hostname = $info['host'];
		$connect_time = $curl_info["connect_time"];
		global $db;
		$res = $db->run("update peers set 
			response_cnt=response_cnt+1, response_time=response_time+:time 
			where hostname like :hostname",
			[ ":hostname"=>"%$hostname%",":time"=>$connect_time]);
	}

	public static function findByHostname($hostName)
	{
		global $db;
		$x = $db->row(
			"SELECT id,hostname FROM peers WHERE hostname=:hostname",
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
		$sql = "update peers p set p.dapps_id = :dapps_id, p.dappshash = :dappshash where p.ip = :ip";
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
		$sql="delete from peers where blacklisted > unix_timestamp() and mid(blacklist_reason, 1, 16) = 'Invalid hostname'";
		$db->run($sql);
	}

}
