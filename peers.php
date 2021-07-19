<?php
require_once __DIR__.'/include/init.inc.php';
header('Content-Type: text/plain');
global $_config;
$peers = Peer::getPeers();

echo $_config['hostname'].PHP_EOL;

foreach($peers as $peer) {
	echo $peer['hostname'].PHP_EOL;
}
