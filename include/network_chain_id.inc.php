<?php

if (!function_exists("resolve_chain_id")) {
    function resolve_chain_id() {
        $networkEnv = strtolower(trim((string)getenv("NETWORK")));
        if ($networkEnv === "testnet") {
            return "01";
        }
        if ($networkEnv === "mainnet") {
            return "00";
        }
        if (Phar::running(false)) {
            $chainIdFile = dirname(Phar::running(false))."/chain_id";
        } else {
            $chainIdFile = dirname(__DIR__)."/chain_id";
        }
        $chainId = trim(@file_get_contents($chainIdFile));
        return $chainId !== "" ? $chainId : "00";
    }
}
