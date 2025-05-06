<?php

$chain_id = trim(@file_get_contents(dirname(__DIR__)."/chain_id"));
if($chain_id != DEFAULT_CHAIN_ID && file_exists(__DIR__ . "/genesis.".$chain_id.".inc.php")) {
	require_once __DIR__ . "/genesis.".$chain_id.".inc.php";
	return;
}

const GENESIS_DATA = [
    "signature" => 'iKx1CJQ38Fu3W4uvvyWDqscDHK79SSU5sZcVG2Hdg1VVt8R8mJmn8ppQUQjVj74Z89q22Cqmdw5s44JTJiN5v5XPiUmtoDsHhD',
    "public_key" => 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCwV8eomW54A8ffNJhS8h3iq1DpzNaDadZvMBSBp6yqKLuebioGkhPjZCGCe59WCVTMmGAHF1qCXaVzKWmCR7KBNEA',
    "argon" => '$argon2i$v=19$m=32768,t=2,p=1$dnN6NVAvUVU5RUt0NzJPQQ$fucz2ipellsRhqbR6g3oAG+wTuzcJi44KsL7D6g1P/A',
    "difficulty" => '60000',
    "nonce" => '0215d1ec54cab34df311b623e270c7171e4b7023aee65fde46af07ef62eea2fe',
    "date" => '1680350400',
    "reward_tx" => '{"9YF9wJKWP8cKuGWsaSZEs9QrHF39vzqsbjSG8VuSQwZ8":{"date":1680350400,"dst":"PgngvvRi27gwQrWQQD43Pj6R3cJaABeLTv","fee":"0.00000000","id":"9YF9wJKWP8cKuGWsaSZEs9QrHF39vzqsbjSG8VuSQwZ8","message":"Doc: Roads? Where were going, we dont need roads.","public_key":"PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCwV8eomW54A8ffNJhS8h3iq1DpzNaDadZvMBSBp6yqKLuebioGkhPjZCGCe59WCVTMmGAHF1qCXaVzKWmCR7KBNEA","signature":"AN1rKvtKPNusPgcV21viJdDH7VJ9oX84TgVp2b7dB8qPaxL5pxo5WKaK7ymuEbdS9uJ7cgJdzdxYAkJE4JUkXFt2cbPnC7mFU","src":"PgngvvRi27gwQrWQQD43Pj6R3cJaABeLTv","type":0,"val":"103200000.00000000"}}',
    "block" => '2ucwGhYszGUTZwmiT5YMsw3tn9nfhdTciaaKMMTX77Zw',
    "address" => 'PgngvvRi27gwQrWQQD43Pj6R3cJaABeLTv',
];
const GENESIS_TIME = 1680350400;
