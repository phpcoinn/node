<?php

/**
 * ERC-20 Smart Contract
 * Version 1.0
 *
 *
 */
const SC_CLASS_NAME =  "MintableERC20Token";
require_once __DIR__ . '/erc_20_token.php';
class MintableERC20Token extends ERC20Token
{


    /**
     * @SmartContractVar
     */
    public $creator;

    /**
     * @SmartContractDeploy
     *
     * @param $name
     * @param $symbol
     * @param $decimals
     * @param $initialSupply
     * @return void
     *
     * Deploys smart contract with specified parameters
     */
    public function deploy($name, $symbol, $decimals, $initialSupply) {
        $this->name = $name;
        $this->symbol = $symbol;
        $this->decimals = $decimals;
        $this->totalSupply = $this->amountToInt($initialSupply);
        $this->balances[$this->msgSender()] = $this->totalSupply;
        $sender = $this->msgSender();
        $this->creator = $sender;
        $this->log("DEPLOY sender=$sender balances=".json_encode($this->balances->all())." total=".$this->totalSupply);
    }


    /**
     *
     * @SmartContractTransact
     *
     * @param $to
     * @param $amount
     * @return void
     * @throws Exception
     *
     * Creates a `amount` amount of tokens and assigns them to contract owner, by transferring it null address (minting).
     */
    public function mint($amount) {
        $sender = $this->msgSender();
        if ($sender !== $this->creator) {
            $this->error("Unauthorized mint attempt");
        }
        $value = $this->amountToInt($amount);
        $this->_update(null, $sender, $value);
    }

}