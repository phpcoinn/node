<?php

/**
 * ERC-20 Smart Contract
 * Version 1.0
 *
 *
 */
const SC_CLASS_NAME =  "BurnableRC20Token";
require_once __DIR__.'/erc_20_token.php';
class BurnableRC20Token extends ERC20Token
{

    /**
     * @SmartContractTransact
     *
     *
     * @param $amount
     * @return void
     * @throws Exception
     *
     * Destroys a `amount` amount of tokens from the caller.
     *
     */
    public function burn($amount) {
        $sender = $this->msgSender();
        $value = $this->amountToInt($amount);
        $this->_update($sender, null, $value);
    }

}