<?php

class Dex
{
    const REQUIRED_METHODS = [
        'listToken',
        'postSell',
        'fillSell',
        'cancelSell',
        'postBuy',
        'fillBuy',
        'cancelBuy',
    ];

    const LEGACY_METHODS = [
        'transferToken',
        'postSell',
        'fillSell',
        'cancelSell',
        'postBuy',
        'fillBuy',
        'cancelBuy',
    ];

    const ERC20_METHODS = [
        'transfer',
        'transferFrom',
        'approve',
        'balanceOf',
        'decimals',
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

    public static function methodNames($address)
    {
        $interface = SmartContractEngine::getInterface($address);
        $names = [];
        if (!$interface) {
            return $names;
        }
        foreach (['methods', 'views'] as $group) {
            foreach ($interface[$group] ?? [] as $method) {
                if (!empty($method['name'])) {
                    $names[$method['name']] = true;
                }
            }
        }
        return $names;
    }

    public static function hasMethods($address, $required)
    {
        $names = self::methodNames($address);
        if (empty($names)) {
            return false;
        }
        foreach ($required as $method) {
            if (empty($names[$method])) {
                return false;
            }
        }
        return true;
    }

    public static function verifyInterface($address)
    {
        return self::hasMethods($address, self::REQUIRED_METHODS)
            || self::hasMethods($address, self::LEGACY_METHODS);
    }

    public static function isMulti($address = null)
    {
        $address = $address ?: self::contractAddress();
        if (empty($address)) {
            return false;
        }
        return self::hasMethods($address, self::REQUIRED_METHODS);
    }

    public static function isErc20($address)
    {
        try {
            if (empty($address) || !Account::valid($address) || !SmartContract::getById($address)) {
                return false;
            }
            return self::hasMethods($address, self::ERC20_METHODS);
        } catch (Throwable $e) {
            return false;
        }
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

    public static function publicLabel($value, $max = 64)
    {
        $s = trim(strip_tags((string)$value));
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, $max);
        }
        return substr($s, 0, $max);
    }

    public static function getListedTokens($address = null)
    {
        $address = $address ?: self::contractAddress();
        if (empty($address)) {
            return [];
        }
        $state = SmartContract::getState($address) ?: [];
        $raw = $state['listedTokens'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $listed = [];
        foreach ($raw as $token => $info) {
            $meta = is_array($info) ? $info : json_decode((string)$info, true);
            if (!is_array($meta) || empty($token)) {
                continue;
            }
            $meta['token'] = $meta['token'] ?? $token;
            $listed[] = $meta;
        }
        usort($listed, function ($a, $b) {
            return strcmp($a['symbol'] ?? '', $b['symbol'] ?? '');
        });
        return $listed;
    }

    public static function getMempoolListedTokens($address = null)
    {
        $address = $address ?: self::contractAddress();
        if (empty($address)) {
            return [];
        }
        $rows = Mempool::getAll();
        if (!is_array($rows)) {
            return [];
        }
        $known = [];
        foreach (self::getNetworkTokens() as $tok) {
            $known[$tok['address']] = $tok;
        }
        $pending = [];
        foreach ($rows as $row) {
            if (intval($row['type'] ?? 0) !== TX_TYPE_SC_EXEC) {
                continue;
            }
            if (($row['dst'] ?? '') !== $address) {
                continue;
            }
            $payload = json_decode(base64_decode((string)($row['message'] ?? '')), true);
            if (!is_array($payload) || ($payload['method'] ?? '') !== 'listToken') {
                continue;
            }
            $token = (string)($payload['params'][0] ?? '');
            if ($token === '' || isset($pending[$token])) {
                continue;
            }
            $meta = $known[$token] ?? [];
            $pending[$token] = [
                'token' => $token,
                'name' => $meta['name'] ?? '',
                'symbol' => $meta['symbol'] ?? 'TOKEN',
                'decimals' => (string)($meta['decimals'] ?? '8'),
                'pending' => true,
            ];
        }
        return array_values($pending);
    }

    public static function getNetworkTokens()
    {
        global $db;
        $sql = "select address,
                       json_unquote(json_extract(metadata,'$.name')) as name,
                       json_unquote(json_extract(metadata,'$.symbol')) as symbol,
                       json_unquote(json_extract(metadata,'$.decimals')) as decimals
                from smart_contracts
                where json_unquote(json_extract(metadata,'$.class')) = 'ERC-20'
                order by height desc";
        $rows = $db->run($sql);
        return is_array($rows) ? $rows : [];
    }

    public static function getMarketTokens($address = null)
    {
        $dex = $address ?: self::contractAddress();
        $blacklisted = defined('BLACKLISTED_SMART_CONTRACTS') ? BLACKLISTED_SMART_CONTRACTS : [];
        $markets = [];
        foreach (self::getNetworkTokens() as $tok) {
            $token = $tok['address'] ?? '';
            if ($token === '' || $token === $dex || in_array($token, $blacklisted, true)) {
                continue;
            }
            if (!self::isErc20($token)) {
                continue;
            }
            $markets[$token] = [
                'token' => $token,
                'name' => self::publicLabel($tok['name'] ?? ''),
                'symbol' => self::publicLabel($tok['symbol'] ?: 'TOKEN', 16),
                'decimals' => (string)($tok['decimals'] ?? '8'),
            ];
        }
        foreach (self::getListedTokens($dex) as $meta) {
            $token = $meta['token'] ?? '';
            if ($token === '' || isset($markets[$token]) || in_array($token, $blacklisted, true)) {
                continue;
            }
            $meta['name'] = self::publicLabel($meta['name'] ?? '');
            $meta['symbol'] = self::publicLabel($meta['symbol'] ?? 'TOKEN', 16);
            $markets[$token] = $meta;
        }
        $markets = array_values($markets);
        usort($markets, function ($a, $b) {
            return strcmp($a['symbol'] ?? '', $b['symbol'] ?? '');
        });
        return $markets;
    }

    public static function getInfo($address = null)
    {
        $address = $address ?: self::contractAddress();
        $contract = $address ? self::getContract($address) : null;
        $state = $contract ? (SmartContract::getState($address) ?: []) : [];
        $multi = $contract ? self::isMulti($address) : false;
        $decimals = intval($state['decimals'] ?? 8);
        $listed = $multi ? self::getMarketTokens($address) : [];
        return [
            'address' => $address,
            'deployed' => !empty($contract),
            'verified' => $contract ? self::verifyInterface($address) : false,
            'multi' => $multi,
            'dex_node' => self::isDexNode(),
            'name' => $multi ? 'PHPCoin DEX' : ($state['name'] ?? null),
            'symbol' => $multi ? 'PHP' : ($state['symbol'] ?? null),
            'decimals' => $decimals,
            'totalSupply' => isset($state['totalSupply']) ? self::tokenToDisplay($state['totalSupply'], $decimals) : null,
            'owner' => $state['owner'] ?? null,
            'phpBalance' => $address ? Account::getBalance($address) : "0",
            'nextOfferId' => intval($state['nextOfferId'] ?? 0),
            'listed' => $listed,
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
        $listed = [];
        foreach (self::getListedTokens($address) as $meta) {
            $listed[$meta['token']] = $meta;
        }
        $markets = [];
        foreach (self::getMarketTokens($address) as $meta) {
            $markets[$meta['token']] = $meta;
        }
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
            if (isset($offer['tokenAmount'])) {
                $offer['token_address'] = $offer['token'] ?? '';
                $offer['token_display'] = (string)$offer['tokenAmount'];
                $offer['token_symbol'] = $listed[$offer['token_address']]['symbol']
                    ?? ($markets[$offer['token_address']]['symbol'] ?? 'TOKEN');
            } else {
                $offer['token_address'] = $address;
                $offer['token_display'] = self::tokenToDisplay($offer['token'] ?? "0", $decimals);
                $offer['token_symbol'] = $state['symbol'] ?? 'TOKEN';
            }
            $offer['php_display'] = num($offer['php'] ?? 0);
            $offers[] = $offer;
        }
        usort($offers, function ($a, $b) {
            return intval($a['id']) - intval($b['id']);
        });
        return $offers;
    }

    public static function getMempoolOffers($address = null)
    {
        $address = $address ?: self::contractAddress();
        if (empty($address)) {
            return [];
        }
        $rows = Mempool::getAll();
        if (!is_array($rows)) {
            return [];
        }
        $listed = [];
        foreach (self::getListedTokens($address) as $meta) {
            $listed[$meta['token']] = $meta;
        }
        $offers = [];
        foreach ($rows as $row) {
            if (intval($row['type'] ?? 0) !== TX_TYPE_SC_EXEC) {
                continue;
            }
            if (($row['dst'] ?? '') !== $address) {
                continue;
            }
            $payload = json_decode(base64_decode((string)($row['message'] ?? '')), true);
            if (!is_array($payload) || empty($payload['method'])) {
                continue;
            }
            $params = $payload['params'] ?? [];
            if ($payload['method'] === 'postSell') {
                $isMulti = isset($params[2]);
                $tokenAddr = $isMulti ? (string)$params[0] : $address;
                $offers[] = [
                    'id' => $row['id'],
                    'side' => 'sell',
                    'maker' => $row['src'],
                    'token_address' => $tokenAddr,
                    'token_display' => (string)($isMulti ? $params[1] : $params[0]),
                    'token_symbol' => $listed[$tokenAddr]['symbol'] ?? 'TOKEN',
                    'php_display' => num($isMulti ? $params[2] : ($params[1] ?? 0)),
                    'pending' => true,
                    'date' => intval($row['date'] ?? 0),
                ];
            } elseif ($payload['method'] === 'postBuy') {
                $isMulti = isset($params[1]);
                $tokenAddr = $isMulti ? (string)$params[0] : $address;
                $offers[] = [
                    'id' => $row['id'],
                    'side' => 'buy',
                    'maker' => $row['src'],
                    'token_address' => $tokenAddr,
                    'token_display' => (string)($isMulti ? $params[1] : $params[0]),
                    'token_symbol' => $listed[$tokenAddr]['symbol'] ?? 'TOKEN',
                    'php_display' => num($row['val'] ?? 0),
                    'pending' => true,
                    'date' => intval($row['date'] ?? 0),
                ];
            }
        }
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

    public static function formatPrice($price)
    {
        $price = (string)$price;
        if ($price === '' || bccomp($price, "0", 12) <= 0) {
            return "0";
        }
        if (bccomp($price, "1000", 8) >= 0) {
            return rtrim(rtrim(number_format((float)$price, 2, '.', ''), '0'), '.') ?: "0";
        }
        if (bccomp($price, "1", 8) >= 0) {
            return rtrim(rtrim(number_format((float)$price, 4, '.', ''), '0'), '.') ?: "0";
        }
        return rtrim(rtrim(number_format((float)$price, 8, '.', ''), '0'), '.') ?: "0";
    }

    public static function offerPrice($offer)
    {
        $amount = (string)($offer['token_display'] ?? '0');
        $php = (string)($offer['php_display'] ?? '0');
        if (bccomp($amount, "0", 8) <= 0) {
            return "0";
        }
        return bcdiv($php, $amount, 8);
    }

    public static function collectOffers($address = null)
    {
        $address = $address ?: self::contractAddress();
        $offers = self::getOffers($address);
        if ($address) {
            $offers = array_merge(self::getMempoolOffers($address), $offers);
        }
        return $offers;
    }

    public static function bookFromOffers($offers)
    {
        $bids = [];
        $asks = [];
        foreach ($offers as $offer) {
            $price = self::offerPrice($offer);
            if (bccomp($price, "0", 8) <= 0) {
                continue;
            }
            $row = [
                'id' => (string)($offer['id'] ?? ''),
                'side' => $offer['side'] ?? '',
                'price' => $price,
                'price_display' => self::formatPrice($price),
                'amount' => (string)($offer['token_display'] ?? '0'),
                'php' => num($offer['php_display'] ?? 0),
                'maker' => $offer['maker'] ?? '',
                'pending' => !empty($offer['pending']),
            ];
            if (($offer['side'] ?? '') === 'buy') {
                $bids[] = $row;
            } else {
                $asks[] = $row;
            }
        }
        usort($asks, function ($a, $b) {
            $cmp = bccomp($a['price'], $b['price'], 8);
            return $cmp === 0 ? 0 : ($cmp < 0 ? -1 : 1);
        });
        usort($bids, function ($a, $b) {
            $cmp = bccomp($a['price'], $b['price'], 8);
            return $cmp === 0 ? 0 : ($cmp > 0 ? -1 : 1);
        });
        $cum = "0";
        foreach ($asks as $i => $row) {
            $cum = bcadd($cum, $row['amount'], 8);
            $asks[$i]['total'] = $cum;
        }
        $cum = "0";
        foreach ($bids as $i => $row) {
            $cum = bcadd($cum, $row['amount'], 8);
            $bids[$i]['total'] = $cum;
        }
        $maxTotal = "0";
        foreach (array_merge($asks, $bids) as $row) {
            if (bccomp($row['total'], $maxTotal, 8) > 0) {
                $maxTotal = $row['total'];
            }
        }
        foreach ($asks as $i => $row) {
            $asks[$i]['depth'] = bccomp($maxTotal, "0", 8) > 0 ? (float)bcdiv($row['total'], $maxTotal, 4) : 0;
        }
        foreach ($bids as $i => $row) {
            $bids[$i]['depth'] = bccomp($maxTotal, "0", 8) > 0 ? (float)bcdiv($row['total'], $maxTotal, 4) : 0;
        }
        return ['bids' => $bids, 'asks' => $asks];
    }

    public static function tickerFromBook($book, $symbol = 'TOKEN')
    {
        $bids = $book['bids'] ?? [];
        $asks = $book['asks'] ?? [];
        $bestBid = $bids[0]['price'] ?? null;
        $bestAsk = $asks[0]['price'] ?? null;
        $last = null;
        if ($bestBid && $bestAsk) {
            $last = bcdiv(bcadd($bestBid, $bestAsk, 8), "2", 8);
        } elseif ($bestAsk) {
            $last = $bestAsk;
        } elseif ($bestBid) {
            $last = $bestBid;
        }
        $spread = null;
        $spreadPct = null;
        if ($bestBid && $bestAsk) {
            $spread = bcsub($bestAsk, $bestBid, 8);
            if (bccomp($last, "0", 8) > 0) {
                $spreadPct = bcmul(bcdiv($spread, $last, 8), "100", 2);
            }
        }
        $bidPhp = "0";
        $askPhp = "0";
        $bidAmt = "0";
        $askAmt = "0";
        foreach ($bids as $row) {
            $bidPhp = bcadd($bidPhp, $row['php'], 8);
            $bidAmt = bcadd($bidAmt, $row['amount'], 8);
        }
        foreach ($asks as $row) {
            $askPhp = bcadd($askPhp, $row['php'], 8);
            $askAmt = bcadd($askAmt, $row['amount'], 8);
        }
        return [
            'symbol' => $symbol,
            'last' => $last,
            'last_display' => $last ? self::formatPrice($last) : '—',
            'best_bid' => $bestBid,
            'best_bid_display' => $bestBid ? self::formatPrice($bestBid) : '—',
            'best_ask' => $bestAsk,
            'best_ask_display' => $bestAsk ? self::formatPrice($bestAsk) : '—',
            'spread' => $spread,
            'spread_display' => $spread ? self::formatPrice($spread) : '—',
            'spread_pct' => $spreadPct,
            'bid_php' => num($bidPhp),
            'ask_php' => num($askPhp),
            'bid_amount' => $bidAmt,
            'ask_amount' => $askAmt,
            'book_php' => num(bcadd($bidPhp, $askPhp, 8)),
            'bid_count' => count($bids),
            'ask_count' => count($asks),
        ];
    }

    public static function getMarketView($token)
    {
        $info = self::getInfo();
        $meta = null;
        foreach ($info['listed'] as $row) {
            if (($row['token'] ?? '') === $token) {
                $meta = $row;
                break;
            }
        }
        if (!$meta && !$info['multi'] && $token === ($info['address'] ?? '')) {
            $meta = [
                'token' => $token,
                'name' => $info['name'] ?: 'DEX token',
                'symbol' => $info['symbol'] ?: 'TOKEN',
                'decimals' => (string)($info['decimals'] ?: 8),
            ];
        }
        $offers = self::collectOffers($info['address']);
        if ($info['multi']) {
            $offers = array_values(array_filter($offers, function ($o) use ($token) {
                return ($o['token_address'] ?? '') === $token;
            }));
        }
        $book = self::bookFromOffers($offers);
        $symbol = $meta['symbol'] ?? 'TOKEN';
        $ticker = self::tickerFromBook($book, $symbol);
        $activity = [];
        foreach ($offers as $offer) {
            $price = self::offerPrice($offer);
            $activity[] = [
                'id' => $offer['id'] ?? '',
                'side' => $offer['side'] ?? '',
                'price_display' => self::formatPrice($price),
                'amount' => $offer['token_display'] ?? '0',
                'php' => $offer['php_display'] ?? '0',
                'maker' => $offer['maker'] ?? '',
                'pending' => !empty($offer['pending']),
                'date' => intval($offer['date'] ?? 0),
                'label' => !empty($offer['pending']) ? 'Pending' : 'Open',
            ];
        }
        usort($activity, function ($a, $b) {
            $ap = !empty($a['pending']);
            $bp = !empty($b['pending']);
            if ($ap !== $bp) {
                return $ap ? -1 : 1;
            }
            if ($ap) {
                return intval($b['date'] ?? 0) <=> intval($a['date'] ?? 0);
            }
            return intval($b['id'] ?? 0) <=> intval($a['id'] ?? 0);
        });
        return [
            'token' => $token,
            'meta' => $meta,
            'pair' => $symbol . '/PHP',
            'ticker' => $ticker,
            'book' => $book,
            'activity' => $activity,
            'height' => Block::getHeight(),
            'updated' => time(),
        ];
    }

    public static function getTickers()
    {
        $info = self::getInfo();
        $offers = self::collectOffers($info['address']);
        $byToken = [];
        foreach ($offers as $offer) {
            $token = $offer['token_address'] ?? '';
            if ($token === '') {
                continue;
            }
            $byToken[$token][] = $offer;
        }
        $tickers = [];
        $markets = $info['multi'] ? ($info['listed'] ?? []) : [[
            'token' => $info['address'],
            'name' => $info['name'] ?: 'DEX token',
            'symbol' => $info['symbol'] ?: 'TOKEN',
            'decimals' => (string)($info['decimals'] ?: 8),
        ]];
        foreach ($markets as $meta) {
            $token = $meta['token'] ?? '';
            if ($token === '') {
                continue;
            }
            $book = self::bookFromOffers($byToken[$token] ?? []);
            $ticker = self::tickerFromBook($book, $meta['symbol'] ?? 'TOKEN');
            $tickers[] = array_merge($meta, $ticker, [
                'pair' => ($meta['symbol'] ?? 'TOKEN') . '/PHP',
            ]);
        }
        return $tickers;
    }
}
