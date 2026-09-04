<?php

/**
 * Simple PHP / token offer-board DEX.
 *
 * Trade this contract's token against native PHPCoin. Token balances stay in
 * this contract; PHP payouts use Transaction::send(). Compile and deploy as a
 * single contract — inter-contract calls are not available.
 */
const SC_CLASS_NAME = "SimpleOfferDex";

class SimpleOfferDex extends SmartContractBase
{
    /**
     * @SmartContractVar
     */
    public $owner;
    /**
     * @SmartContractVar
     */
    public $name;
    /**
     * @SmartContractVar
     */
    public $symbol;
    /**
     * @SmartContractVar
     */
    public $decimals;
    /**
     * @SmartContractVar
     */
    public $totalSupply;
    /**
     * @SmartContractVar
     */
    public $nextOfferId;

    /**
     * @SmartContractMap
     */
    public SmartContractMap $balances;
    /**
     * @SmartContractMap
     */
    public SmartContractMap $offers;

    /**
     * @SmartContractDeploy
     */
    public function deploy($name, $symbol, $decimals, $initialSupply)
    {
        $this->owner = $this->src;
        $this->name = $name;
        $this->symbol = $symbol;
        $this->decimals = $decimals;
        $this->totalSupply = $this->amountToInt($initialSupply);
        $this->balances[$this->src] = $this->totalSupply;
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
    public function tokenBalance($address)
    {
        $bal = $this->balances[$address];
        return empty($bal) ? "0" : $bal;
    }

    /**
     * @SmartContractView
     */
    public function phpBalance()
    {
        return Account::getBalance($this->address);
    }

    /**
     * @SmartContractTransact
     */
    public function transferToken($to, $amount)
    {
        if (!Account::valid($to)) {
            $this->error("INVALID_ADDRESS");
        }
        $value = $this->amountToInt($amount);
        $fromBal = $this->intOrZero($this->balances[$this->src]);
        if (bccomp($fromBal, $value) < 0) {
            $this->error("INSUFFICIENT_TOKEN_BALANCE");
        }
        $this->balances[$this->src] = bcsub($fromBal, $value, 0);
        $toBal = $this->intOrZero($this->balances[$to]);
        $this->balances[$to] = bcadd($toBal, $value, 0);
    }

    /**
     * Lock tokens and post a sell offer. Buyer later pays $phpAmount native PHP.
     *
     * @SmartContractTransact
     */
    public function postSell($tokenAmount, $phpAmount)
    {
        $tokenValue = $this->amountToInt($tokenAmount);
        $phpValue = $this->phpAmount($phpAmount);
        if (bccomp($tokenValue, "0") <= 0 || bccomp($phpValue, "0") <= 0) {
            $this->error("INVALID_AMOUNT");
        }
        $fromBal = $this->intOrZero($this->balances[$this->src]);
        if (bccomp($fromBal, $tokenValue) < 0) {
            $this->error("INSUFFICIENT_TOKEN_BALANCE");
        }
        $this->balances[$this->src] = bcsub($fromBal, $tokenValue, 0);
        $id = (string)$this->nextOfferId;
        $this->nextOfferId = (int)$this->nextOfferId + 1;
        $this->offers[$id] = json_encode([
            "id" => $id,
            "side" => "sell",
            "maker" => $this->src,
            "token" => $tokenValue,
            "php" => $phpValue,
        ]);
        return $id;
    }

    /**
     * Fill a sell offer by sending exactly the listed PHP with this transaction.
     *
     * @SmartContractTransact
     */
    public function fillSell($id)
    {
        $offer = $this->requireOffer($id, "sell");
        $phpValue = $this->phpAmount($this->value);
        if (bccomp($phpValue, $offer["php"], 8) !== 0) {
            $this->error("PHP_AMOUNT_MUST_MATCH_OFFER");
        }
        $toBal = $this->intOrZero($this->balances[$this->src]);
        $this->balances[$this->src] = bcadd($toBal, $offer["token"], 0);
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
        $toBal = $this->intOrZero($this->balances[$this->src]);
        $this->balances[$this->src] = bcadd($toBal, $offer["token"], 0);
        unset($this->offers[$id]);
    }

    /**
     * Lock native PHP (send it with this transaction) and post a buy offer.
     *
     * @SmartContractTransact
     */
    public function postBuy($tokenAmount)
    {
        $tokenValue = $this->amountToInt($tokenAmount);
        $phpValue = $this->phpAmount($this->value);
        if (bccomp($tokenValue, "0") <= 0 || bccomp($phpValue, "0") <= 0) {
            $this->error("INVALID_AMOUNT");
        }
        $id = (string)$this->nextOfferId;
        $this->nextOfferId = (int)$this->nextOfferId + 1;
        $this->offers[$id] = json_encode([
            "id" => $id,
            "side" => "buy",
            "maker" => $this->src,
            "token" => $tokenValue,
            "php" => $phpValue,
        ]);
        return $id;
    }

    /**
     * Fill a buy offer by delivering the listed token amount from the caller.
     *
     * @SmartContractTransact
     */
    public function fillBuy($id)
    {
        $offer = $this->requireOffer($id, "buy");
        $fromBal = $this->intOrZero($this->balances[$this->src]);
        if (bccomp($fromBal, $offer["token"]) < 0) {
            $this->error("INSUFFICIENT_TOKEN_BALANCE");
        }
        $this->balances[$this->src] = bcsub($fromBal, $offer["token"], 0);
        $makerBal = $this->intOrZero($this->balances[$offer["maker"]]);
        $this->balances[$offer["maker"]] = bcadd($makerBal, $offer["token"], 0);
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

    private function amountToInt($amount)
    {
        $mul = bcpow("10", (string)intval($this->decimals), 0);
        return bcmul((string)$amount, $mul, 0);
    }

    private function phpAmount($amount)
    {
        return bcadd((string)$amount, "0", 8);
    }

    private function intOrZero($value)
    {
        if ($value === null || $value === false || $value === "") {
            return "0";
        }
        return (string)$value;
    }
}
