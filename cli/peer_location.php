<?php

require_once dirname(__DIR__).'/include/init.inc.php';

if (php_sapi_name() !== 'cli') {
    die("This should only be run as cli");
}

global $db;

function Dot2LongIP ($IPaddr)
{
    if ($IPaddr == "")
    {
        return 0;
    }
    else {
        $ip = explode(".", $IPaddr);
        return ($ip[3] + $ip[2] * 256 + $ip[1] * 256 * 256 + $ip[0] * 256 * 256 * 256);
    }
}

$sql="create table if not exists peer_location
(
    country_code varchar(20)  null,
    country_name varchar(255) null,
    city_name    varchar(255) null,
    latitude     double       null,
    longitude    double       not null,
    peer_ip      varchar(20)  null,
    constraint peer_location_pk
        unique (peer_ip)
);";

$db->run($sql);
$peers=Peer::getAll();

_log("Updating peers location database peers=".count($peers));
$updated = 0;
$t1=microtime(true);
foreach ($peers as $peer) {
    $ip = $peer['ip'];
    $ipno = Dot2LongIP($ip);

    $rows = $db->run("SELECT * FROM ip2location_db5 WHERE :ipno between ip_from and ip_to order by ip_to limit 1 ", [":ipno"=>$ipno]);
    if(count($rows)==0) {
        _log("Not found location for ip $ip");
        continue;
    } else if (count($rows)>1) {
        _log("Found multiple locations for ip $ip");
        continue;
    } else {
        $row = $rows[0];
    }
    $peer['country_code']=$row['country_code'];
    $peer['country_name']=$row['country_name'];
    $peer['city_name']=$row['city_name'];
    $peer['latitude']=$row['latitude'];
    $peer['longitude']=$row['longitude'];

    $sql="replace into peer_location (peer_ip, country_code, country_name, city_name, latitude, longitude)
                values (:peer_ip, :country_code, :country_name, :city_name, :latitude, :longitude)";

    $params = [
        ":peer_ip" => $ip,
        ":country_code" =>$peer['country_code'],
        ":country_name" => $peer['country_name'],
        ":city_name" => $peer['city_name'],
        ":latitude" => $peer['latitude'],
        ":longitude" => $peer['longitude']
    ];

    $res = $db->run($sql, $params);
    if($res === false) {
        _log("Not update peer location for ip=$ip");
        continue;
    }
    $updated++;
}
$t2=microtime(true);
_log("Updated $updated peers locations in ".($t2-$t1));
