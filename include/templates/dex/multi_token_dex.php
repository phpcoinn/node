<?php

/**
 * Multi-token DEX: ERC-20s created on PHPCoin vs native PHP.
 *
 * Tokens created as ERC-20s on this network are tradeable versus PHP.
 * The first trade against a token verifies decimals/symbol on-chain.
 * Sells pull tokens with transferFrom after the maker approves this DEX.
 */
const SC_CLASS_NAME = "MultiTokenDex";

class MultiTokenDex extends SmartContractBase
{
    /**
     * @SmartContractVar
     */
    public $owner;
    /**
     * @SmartContractVar
     */
    public $nextOfferId;

    /**
     * @SmartContractMap
     */
    public SmartContractMap $listedTokens;
    /**
     * @SmartContractMap
     */
    public SmartContractMap $offers;

    /**
     * @SmartContractDeploy
     */
    public function deploy()
    {
        $this->owner = $this->src;
        $this->nextOfferId = 1;
    }

    /**
     * @SmartContractView
     */
    public function getOffer($id)
    {
        $raw = $this->offers[$id];
        if (empty($raw)) {
            return null;
        }
        return json_decode($raw, true);
    }

    /**
     * @SmartContractView
     */
    public function listedToken($token)
    {
        $raw = $this->listedTokens[$token];
        if (empty($raw)) {
            return null;
        }
        return json_decode($raw, true);
    }

    /**
     * @SmartContractView
     */
    public function openOffers()
    {
        $out = [];
        $all = $this->offers->all();
        if (!is_array($all)) {
            return $out;
        }
        foreach ($all as $id => $raw) {
            if (empty($raw)) {
                continue;
            }
            $offer = json_decode($raw, true);
            if (is_array($offer) && !empty($offer["side"])) {
                $offer["id"] = (string)$id;
                $out[] = $offer;
            }
        }
        return $out;
    }

    /**
     * Optional explicit verify. New ERC-20s are already markets from create;
     * the first postSell/postBuy also verifies. Kept so older DEX UIs still work.
     *
     * @SmartContractTransact
     */
    public function listToken($token)
    {
        $this->ensureToken($token);
        return $token;
    }

    /**
     * @SmartContractTransact
     */
    public function postSell($token, $tokenAmount, $phpAmount)
    {
        $meta = $this->ensureToken($token);
        $phpValue = $this->phpAmount($phpAmount);
        if (bccomp((string)$tokenAmount, "0", intval($meta["decimals"])) <= 0 || bccomp($phpValue, "0") <= 0) {
            $this->error("INVALID_AMOUNT");
        }
        $this->execSmartContract($token, "transferFrom", [$this->src, $this->address, $tokenAmount]);
        return $this->putOffer("sell", $token, $tokenAmount, $phpValue);
    }

    /**
     * @SmartContractTransact
     */
    public function fillSell($id)
    {
        $offer = $this->requireOffer($id, "sell");
        $phpValue = $this->phpAmount($this->value);
        if (bccomp($phpValue, $offer["php"], 8) !== 0) {
            $this->error("PHP_AMOUNT_MUST_MATCH_OFFER");
        }
        $this->execSmartContract($offer["token"], "transfer", [$this->src, $offer["tokenAmount"]]);
        unset($this->offers[$id]);
        Transaction::send($offer["maker"], $offer["php"]);
    }

    /**
     * @SmartContractTransact
     */
    public function cancelSell($id)
    {
        $offer = $this->requireOffer($id, "sell");
        if ($this->src !== $offer["maker"]) {
            $this->error("UNAUTHORIZED");
        }
        $this->execSmartContract($offer["token"], "transfer", [$this->src, $offer["tokenAmount"]]);
        unset($this->offers[$id]);
    }

    /**
     * @SmartContractTransact
     */
    public function postBuy($token, $tokenAmount)
    {
        $meta = $this->ensureToken($token);
        $phpValue = $this->phpAmount($this->value);
        if (bccomp((string)$tokenAmount, "0", intval($meta["decimals"])) <= 0 || bccomp($phpValue, "0") <= 0) {
            $this->error("INVALID_AMOUNT");
        }
        return $this->putOffer("buy", $token, $tokenAmount, $phpValue);
    }

    /**
     * @SmartContractTransact
     */
    public function fillBuy($id)
    {
        $offer = $this->requireOffer($id, "buy");
        $this->execSmartContract($offer["token"], "transferFrom", [$this->src, $offer["maker"], $offer["tokenAmount"]]);
        unset($this->offers[$id]);
        Transaction::send($this->src, $offer["php"]);
    }

    /**
     * @SmartContractTransact
     */
    public function cancelBuy($id)
    {
        $offer = $this->requireOffer($id, "buy");
        if ($this->src !== $offer["maker"]) {
            $this->error("UNAUTHORIZED");
        }
        unset($this->offers[$id]);
        Transaction::send($this->src, $offer["php"]);
    }

    private function putOffer($side, $token, $tokenAmount, $phpValue)
    {
        $id = (string)$this->nextOfferId;
        $this->nextOfferId = (int)$this->nextOfferId + 1;
        $this->offers[$id] = json_encode([
            "id" => $id,
            "side" => $side,
            "maker" => $this->src,
            "token" => $token,
            "tokenAmount" => (string)$tokenAmount,
            "php" => $phpValue,
        ]);
        return $id;
    }

    private function ensureToken($token)
    {
        if (!Account::valid($token) || $token === $this->address) {
            $this->error("INVALID_TOKEN");
        }
        $blacklisted = SmartContractContext::$blacklisted ?? [];
        if (is_array($blacklisted) && in_array($token, $blacklisted, true)) {
            $this->error("TOKEN_BLACKLISTED");
        }
        $raw = $this->listedTokens[$token];
        if (!empty($raw)) {
            $meta = json_decode($raw, true);
            if (is_array($meta) && isset($meta["decimals"], $meta["symbol"])) {
                return $meta;
            }
        }
        $decimals = $this->callSmartContract($token, "decimals", []);
        $symbol = $this->callSmartContract($token, "symbol", []);
        $name = $this->callSmartContract($token, "name", []);
        if ($decimals === null || $decimals === false || $decimals === "") {
            $this->error("NOT_ERC20");
        }
        if ($symbol === null || $symbol === false || $symbol === "") {
            $this->error("NOT_ERC20");
        }
        $meta = [
            "token" => $token,
            "name" => (string)$name,
            "symbol" => (string)$symbol,
            "decimals" => (string)$decimals,
        ];
        $this->listedTokens[$token] = json_encode($meta);
        return $meta;
    }

    private function requireOffer($id, $side)
    {
        $raw = $this->offers[$id];
        if (empty($raw)) {
            $this->error("OFFER_NOT_FOUND");
        }
        $offer = json_decode($raw, true);
        if (!is_array($offer) || ($offer["side"] ?? null) !== $side) {
            $this->error("INVALID_OFFER");
        }
        return $offer;
    }

    private function phpAmount($amount)
    {
        return bcadd((string)$amount, "0", 8);
    }
}
