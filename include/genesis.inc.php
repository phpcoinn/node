<?php
if(file_exists(dirname(__DIR__)."/testnet")) {
	require_once __DIR__ . "/genesis.testnet.inc.php";
	return;
}


const GENESIS_DATA = [
"signature" => 'AN1rKvtK3T1BTx9ptY3i7XfKhRXNi4mBXdrqX6gzr4984xnnxNoPnFnaXzsMEesA25H2ULPnXMru5R98okTpB7Ux83tsuTTUj',
"public_key" => 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCzn7YbwQ4KfEfp3QLZJvQuxgfxEbopcGyVhiZr666X352TaGXEGAX1PcmtXCb7rWvmBxRMVC3yicp1GcgBFzF7tF2',
"argon" => '$argon2i$v=19$m=2048,t=1,p=1$UGNCREo1SkpKenlLZUJndg$ROYdz1qBmyYuImfUlg9/CyOxalrVUZ+6bpxa1swL8zw',
"difficulty" => '60000',
"nonce" => '906634de25024432d1af84a348f434f3b9fcf31a72c8b6d3df96f12b01aedf57',
"date" => '1636659426',
"reward_tx" => '{"FwTpRshuzepsZsNcQSwfPouDoRrWYZWEcTdPDxuqe6tc":{"date":1636659426,"dst":"PcBDJ5JJJzyKeBgvKLpYynEctG5LnkuNsj","fee":"0.00000000","id":"FwTpRshuzepsZsNcQSwfPouDoRrWYZWEcTdPDxuqe6tc","message":"This is genesis","public_key":"PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCzn7YbwQ4KfEfp3QLZJvQuxgfxEbopcGyVhiZr666X352TaGXEGAX1PcmtXCb7rWvmBxRMVC3yicp1GcgBFzF7tF2","signature":"AN1rKvtiAdnvUax7pJggqWyUoudhgiCJteepJimMPCwQEzVpC1VHC36bJdNcSWAF3yYaerqSJi3BvwYiqJtqz8FkGv4BKDssg","src":"PcBDJ5JJJzyKeBgvKLpYynEctG5LnkuNsj","type":0,"val":"4900010.00000000"}}',
];
const GENESIS_TIME = 1636659426;
