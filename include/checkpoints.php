<?php

$chain_id = trim(@file_get_contents(dirname(__DIR__)."/chain_id"));
if($chain_id != DEFAULT_CHAIN_ID && file_exists(__DIR__ . "/checkpoints.".$chain_id.".php")) {
    require_once __DIR__ . "/checkpoints.".$chain_id.".php";
    return;
}

$checkpoints = [
    1 => "2ucwGhYszGUTZwmiT5YMsw3tn9nfhdTciaaKMMTX77Zw",
    1000 => "6seZYqyH83Us7NhX3N9TgB8jqgkFrCc9etpQsiUL3coz",
    2000 => "2rogFRTAfQvn4bQh1kmQ9yxxZsc64MJtZXYwcnNEAhCU",
    3000 => "ASK62JwSdLMmVnPQhgmGphYhTyXQmyktSbbJ45Z9Yh3a",
    4000 => "97RLYJACbpuWrXw82cNHe1hsmxZepEka6dAqq43dyuhk",
    5000 => "BLLhY8L5kr61jmwjF252qNBuBaL1Sxhz8TurgJ1MZVbR",
    6000 => "6Z3uV4ZGaxKsLiG5qH9rt8G5AUHsumGSU78pqTqcz5jg",
    7000 => "FWGgn7MvCSGHGQfPAiqLq7ALhR19BMLMTj3D9H6Mcn29",
    8000 => "GJJY4Bc8mHe7zCPMNaE64vD7S6ZY8HzYCtozFRUqiNCN",
    9000 => "32c1PmcubjHeGtUjsGAiNZ1nRngk18kRvUeT6y1CtY6i",
    10000 => "5FCNEW8bRgb6pv7YXub755ijSU2DQJ15EqSdd4USMj98",
    11000 => "GkH9S8LqQ3X5VWvKwxgScvSYuLjeQoDxdc1Y2pFcoMEt",
];
