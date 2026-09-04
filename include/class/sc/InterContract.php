<?php

class InterContract
{
    const MAX_DEPTH = 3;
    const WRITE_METHODS = ['transferFrom' => true, 'transfer' => true];
    const READ_METHODS = [
        'allowance' => true,
        'balanceOf' => true,
        'decimals' => true,
        'name' => true,
        'symbol' => true,
        'totalSupply' => true,
    ];

    public static function exec($caller, $contract, $method, $params)
    {
        return self::run($caller, $contract, $method, $params, true);
    }

    public static function call($caller, $contract, $method, $params)
    {
        return self::run($caller, $contract, $method, $params, false);
    }

    private static function run($caller, $contract, $method, $params, $write)
    {
        if (empty(SmartContractContext::$icc)) {
            throw new Exception("Inter-contract calls are disabled");
        }
        if (!valid($contract)) {
            throw new Exception("INVALID_CONTRACT");
        }
        $blacklisted = SmartContractContext::$blacklisted ?? [];
        if (is_array($blacklisted) && in_array($contract, $blacklisted, true)) {
            throw new Exception("TOKEN_BLACKLISTED");
        }
        $fields = $caller->getExtFields();
        $callerAddress = $fields['address'];
        if ($contract === $callerAddress) {
            throw new Exception("CANNOT_CALL_SELF");
        }
        $method = (string)$method;
        if ($write && empty(self::WRITE_METHODS[$method])) {
            throw new Exception("METHOD_NOT_ALLOWED");
        }
        if (!$write && empty(self::READ_METHODS[$method])) {
            throw new Exception("METHOD_NOT_ALLOWED");
        }
        if (!is_array($params)) {
            $params = [];
        }
        $depth = intval(SmartContractContext::$iccDepth ?? 0);
        if ($depth >= self::MAX_DEPTH) {
            throw new Exception("CALL_DEPTH_EXCEEDED");
        }
        $stack = SmartContractContext::$iccStack ?? [];
        if (in_array($contract, $stack, true)) {
            throw new Exception("REENTRANCY_BLOCKED");
        }

        if (!class_exists('ERC20Token', false)) {
            throw new Exception("ERC20_HOST_MISSING");
        }

        $interface = self::loadInterface($contract);
        if (!$interface) {
            throw new Exception("TOKEN_NOT_FOUND");
        }
        self::requireErc20($interface);

        $prevAddress = SmartContractContext::$address;
        $prevAllowSend = SmartContractContext::$allowSend;
        SmartContractContext::$iccDepth = $depth + 1;
        $stack[] = $contract;
        SmartContractContext::$iccStack = $stack;
        SmartContractContext::$allowSend = false;

        try {
            $host = new ERC20Token();
            $wrapper = new SmartContractWrapper($host, $contract, $interface);
            $wrapper->args = [
                'height' => $fields['height'],
                'virtual' => !empty(SmartContractContext::$virtual),
                'test' => false,
            ];
            return $wrapper->execExternal($caller, $method, $params, $write);
        } finally {
            SmartContractContext::$address = $prevAddress;
            SmartContractContext::$allowSend = $prevAllowSend;
            SmartContractContext::$iccDepth = $depth;
            array_pop($stack);
            SmartContractContext::$iccStack = $stack;
        }
    }

    private static function loadInterface($address)
    {
        if (empty(SmartContractContext::$virtual) && isset(SmartContractContext::$db)) {
            $row = SmartContractContext::$db->row(
                "select code from smart_contracts where address = ?",
                [$address]
            );
            if (!empty($row['code'])) {
                $data = json_decode(base64_decode($row['code']), true);
                if (is_array($data) && !empty($data['interface'])) {
                    return $data['interface'];
                }
            }
        }
        return null;
    }

    public static function requireErc20($interface)
    {
        $names = [];
        foreach (($interface['methods'] ?? []) as $method) {
            if (!empty($method['name'])) {
                $names[$method['name']] = true;
            }
        }
        foreach (($interface['views'] ?? []) as $method) {
            if (!empty($method['name'])) {
                $names[$method['name']] = true;
            }
        }
        foreach (['transfer', 'transferFrom', 'approve', 'balanceOf', 'decimals'] as $required) {
            if (empty($names[$required])) {
                throw new Exception("NOT_ERC20");
            }
        }
    }
}
