<?php
require_once dirname(__DIR__).'/include/init.inc.php';
header('Content-Type: text/plain');
global $_config;
$peers = Peer::getPeers();

echo $_config['hostname'].PHP_EOL;

//find best peers
$peers_by_height = [];
foreach($peers as $peer) {
	$height = $peer['height'];
	$hostname = $peer['hostname'];
	$peers_by_height[$height][$hostname]=$hostname;
}

uksort($peers_by_height, function($h1, $h2) use ($peers_by_height) {
	$c1 = count($peers_by_height[$h1]);
	$c2 = count($peers_by_height[$h2]);
	return $c2-$c1;
});

$best_peers = array_shift($peers_by_height);

foreach($best_peers as $peer) {
	echo $peer.PHP_EOL;
}
