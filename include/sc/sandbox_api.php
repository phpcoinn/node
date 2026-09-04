<?php
/**
 * Sandbox-only smart contract APIs.
 *
 * Loaded by sandbox_bootstrap.php and packed into new PHARs. Kept outside
 * include/class so composer classmap does not collide with the node's
 * Transaction and Account classes.
 */

if (!class_exists('Transaction', false)) {
class Transaction
{
    const MAX_SENDS = 50;

    /**
     * Queue a native PHPCoin payout from the contract address.
     * The node applies the balances after the contract executes successfully.
     */
    public static function send($to, $amount)
    {
        if (empty(SmartContractContext::$allowSend)) {
            throw new Exception("Cannot send PHP from a view method");
        }
        if (!valid($to)) {
            throw new Exception("Invalid address");
        }
        $normalized = bcadd((string)$amount, "0", 8);
        if (bccomp($normalized, "0", 8) <= 0) {
            throw new Exception("Invalid amount");
        }
        $from = SmartContractContext::$address;
        $balance = SmartContractContext::$nativeBalance ?? "0";
        if (bccomp($balance, $normalized, 8) < 0) {
            throw new Exception("Insufficient contract balance");
        }
        $transfers = SmartContractContext::$transfers ?? [];
        if (count($transfers) >= self::MAX_SENDS) {
            throw new Exception("Too many native transfers in one execution");
        }
        $transfers[] = [
            "from" => $from,
            "to" => $to,
            "amount" => $normalized,
        ];
        SmartContractContext::$transfers = $transfers;
        SmartContractContext::$nativeBalance = bcsub($balance, $normalized, 8);
    }
}
}

if (!class_exists('Account', false)) {
class Account
{
    public static function valid($address)
    {
        return valid($address);
    }

    public static function getBalance($address)
    {
        if (!valid($address)) {
            return "0.00000000";
        }
        if ($address === SmartContractContext::$address) {
            return bcadd((string)(SmartContractContext::$nativeBalance ?? "0"), "0", 8);
        }
        if (!empty(SmartContractContext::$virtual)) {
            return "0.00000000";
        }
        $db = SmartContractContext::$db;
        $res = $db->single("select balance from accounts where id = :id", [":id" => $address]);
        if ($res === false || $res === null) {
            return "0.00000000";
        }
        return bcadd((string)$res, "0", 8);
    }
}
}
