<?php

class Dex
{
    const REQUIRED_METHODS = [
        'transferToken',
        'postSell',
        'fillSell',
        'cancelSell',
        'postBuy',
        'fillBuy',
        'cancelBuy',
    ];

    public static function isEnabled()
    {
        return defined('FEATURE_SMART_CONTRACTS') && FEATURE_SMART_CONTRACTS;
    }

    public static function isDexNode()
    {
        global $_config;
        if (!self::isEnabled()) {
            return false;
        }
        if (isset($_config['dex_node'])) {
            return (bool)$_config['dex_node'];
        }
        return true;
    }

    public static function contractAddress()
    {
        global $_config;
        if (!empty($_config['dex_contract']) && Account::valid($_config['dex_contract'])) {
            return $_config['dex_contract'];
        }
        if (defined('DEX_CONTRACT_ADDRESS') && DEX_CONTRACT_ADDRESS !== '' && Account::valid(DEX_CONTRACT_ADDRESS)) {
            return DEX_CONTRACT_ADDRESS;
        }
        return '';
    }

    public static function getContract($address = null)
    {
        $address = $address ?: self::contractAddress();
        if (empty($address)) {
            return null;
        }
        return SmartContract::getById($address);
    }

    public static function verifyInterface($address)
    {
        $interface = SmartContractEngine::getInterface($address);
        if (!$interface || empty($interface['methods'])) {
            return false;
        }
        $names = [];
        foreach ($interface['methods'] as $method) {
            $names[$method['name']] = true;
        }
        foreach (self::REQUIRED_METHODS as $required) {
            if (empty($names[$required])) {
                return false;
            }
        }
        return true;
    }

    public static function isVerified($address = null)
    {
        $address = $address ?: self::contractAddress();
        if (empty($address) || !self::getContract($address)) {
            return false;
        }
        return self::verifyInterface($address);
    }

    public static function tokenToDisplay($amount, $decimals)
    {
        $decimals = intval($decimals);
        if ($decimals < 0) {
            $decimals = 0;
        }
        $div = bcpow("10", (string)$decimals, 0);
        if ($div === "0" || $div === "") {
            $div = "1";
        }
        return bcdiv((string)($amount ?: "0"), $div, $decimals);
    }

    public static function getInfo($address = null)
    {
        $address = $address ?: self::contractAddress();
        $contract = $address ? self::getContract($address) : null;
        $state = $contract ? (SmartContract::getState($address) ?: []) : [];
        $decimals = intval($state['decimals'] ?? 8);
        return [
            'address' => $address,
            'deployed' => !empty($contract),
            'verified' => $contract ? self::verifyInterface($address) : false,
            'dex_node' => self::isDexNode(),
            'name' => $state['name'] ?? null,
            'symbol' => $state['symbol'] ?? null,
            'decimals' => $decimals,
            'totalSupply' => isset($state['totalSupply']) ? self::tokenToDisplay($state['totalSupply'], $decimals) : null,
            'owner' => $state['owner'] ?? null,
            'phpBalance' => $address ? Account::getBalance($address) : "0",
            'nextOfferId' => intval($state['nextOfferId'] ?? 0),
        ];
    }

    public static function getOffers($address = null)
    {
        $address = $address ?: self::contractAddress();
        if (empty($address)) {
            return [];
        }
        $state = SmartContract::getState($address) ?: [];
        $rawOffers = $state['offers'] ?? [];
        if (!is_array($rawOffers)) {
            return [];
        }
        $decimals = intval($state['decimals'] ?? 8);
        $offers = [];
        foreach ($rawOffers as $id => $raw) {
            if (empty($raw)) {
                continue;
            }
            $offer = is_array($raw) ? $raw : json_decode($raw, true);
            if (!is_array($offer) || empty($offer['side'])) {
                continue;
            }
            $offer['id'] = (string)($offer['id'] ?? $id);
            $offer['token_display'] = self::tokenToDisplay($offer['token'] ?? "0", $decimals);
            $offer['php_display'] = num($offer['php'] ?? 0);
            $offers[] = $offer;
        }
        usort($offers, function ($a, $b) {
            return intval($a['id']) - intval($b['id']);
        });
        return $offers;
    }

    public static function getPeerNodes()
    {
        global $db, $_config;
        $nodes = [];
        $selfHost = null;
        if (!empty($_config['hostname'])) {
            $selfHost = rtrim($_config['hostname'], '/');
        }
        if (self::isDexNode()) {
            $nodes[] = [
                'hostname' => $selfHost ?: '/',
                'ip' => null,
                'height' => Block::getHeight(),
                'version' => VERSION . '.' . BUILD_VERSION,
                'local' => true,
                'dex_contract' => self::contractAddress(),
                'url' => '/apps/dex',
            ];
        }
        $peers = $db->run("SELECT hostname, ip, height, version, ping, info FROM peers WHERE blacklisted < " . DB::unixTimeStamp() . " ORDER BY ping DESC");
        if (!is_array($peers)) {
            return $nodes;
        }
        foreach ($peers as $peer) {
            $info = json_decode($peer['info'] ?? '', true);
            if (!is_array($info) || empty($info['dex'])) {
                continue;
            }
            $hostname = rtrim($peer['hostname'], '/');
            if ($selfHost && strcasecmp($hostname, $selfHost) === 0) {
                continue;
            }
            $nodes[] = [
                'hostname' => $hostname,
                'ip' => $peer['ip'],
                'height' => $peer['height'],
                'version' => $peer['version'],
                'local' => false,
                'dex_contract' => $info['dex_contract'] ?? '',
                'url' => $hostname . '/apps/dex',
            ];
        }
        return $nodes;
    }
}
