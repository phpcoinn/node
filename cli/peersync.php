<?php
require_once dirname(__DIR__).'/include/init.inc.php';

$hostname = $argv[1];
if(empty($hostname)) {
	_log("PeerSync: Empty hostname");
	exit;
}

_log("PeerSync: start sync with $hostname");

NodeSync::peerSync($hostname,0,0);

Propagate::blockToAll("current");

_log("PeerSync: end");
