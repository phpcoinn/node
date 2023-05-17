<?php

$chain_id = trim(@file_get_contents(dirname(__DIR__)."/chain_id"));
if($chain_id != DEFAULT_CHAIN_ID && file_exists(__DIR__ . "/checkpoints.".$chain_id.".php")) {
    require_once __DIR__ . "/checkpoints.".$chain_id.".php";
    return;
}

$checkpoints = [
    1 => "2ucwGhYszGUTZwmiT5YMsw3tn9nfhdTciaaKMMTX77Zw",
    1000 => "6seZYqyH83Us7NhX3N9TgB8jqgkFrCc9etpQsiUL3coz",
    5000 => "BLLhY8L5kr61jmwjF252qNBuBaL1Sxhz8TurgJ1MZVbR",
    10000 => "5FCNEW8bRgb6pv7YXub755ijSU2DQJ15EqSdd4USMj98",
    15000 => "CmFpsmEew6kCUgwFRPyvbYKGmiXZ4Hd1Gedoa6q98TJV",
    20000 => "3LYBUKS1UFyPnY6ne3vz8zaYAWA36p75jvJzX1LZAJCi",
    25000 => "29oP8cKUKawXvQgfV5prURUPytZt58iFqUug3cQd9ndf",
    30000 => "AyuYpXTxSimipWXw9kaXg5A3Cu9gX7VcoFh5k2HhXMTp",
    35000 => "wnV5doKG5L5do9X7ZK9ceTsEY38n4ktYgdZatpuyM1L",
    40000 => "BBC84NpVjBtcaxdSPjCzHZm1J9XZ8hdoC38kPwLouatj",
    45000 => "8fFgPVrVjWZQNWUkvWB4VUngdS9XFhVUEyZXnQFLaxuR",
    50000 => "2RmopeRpxuFp6VM64v2Czyph7sTL3MXxJcPSGLBod2up",
];
