<?php
/**
 * ERC-20 Smart Contract
 * Version 1.0
 *
 *
 */
const SC_CLASS_NAME =  "BurnableMintableERC20Token";
require_once __DIR__ . '/erc_20_token_mintable.php';
class BurnableMintableERC20Token extends MintableERC20Token {

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