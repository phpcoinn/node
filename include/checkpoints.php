<?php

$chain_id = trim(@file_get_contents(dirname(__DIR__)."/chain_id"));
if($chain_id != DEFAULT_CHAIN_ID && file_exists(__DIR__ . "/checkpoints.".$chain_id.".php")) {
    require_once __DIR__ . "/checkpoints.".$chain_id.".php";
    return;
}

$checkpoints = [
    1 => "2ucwGhYszGUTZwmiT5YMsw3tn9nfhdTciaaKMMTX77Zw"
];

