<?php

/**
 * ERC-20 Smart Contract
 * Version 1.0
 *
 *
 */
const SC_CLASS_NAME =  "ERC20Token";
class ERC20Token extends SmartContractBase
{

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
    public SmartContractMap $balances;
    public SmartContractMap $allowances;
    public SmartContractMap $events;

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
        $this->log("DEPLOY sender=$sender balances=".json_encode($this->balances->all())." total=".$this->totalSupply);
    }

    /**
     * @SmartContractView
     * Returns the name of the token.
     */
    public function name() {
        return $this->name;
    }

    /**
     * @SmartContractView
     * Returns the symbol of the token, usually a shorter version of the
     * name.
     */
    public function symbol() {
        return $this->symbol;
    }

    /**
     * @SmartContractView
     * Returns the number of decimals used to get its user representation.
     *  For example, if `decimals` equals `2`, a balance of `505` tokens should
     *  be displayed to a user as `5.05` (`505 / 10 ** 2`).
     *
     *  Tokens usually opt for a value of 18, imitating the relationship between
     *  Ether and Wei. This is the default value returned by this function, unless
     *  it's overridden.
     *
     *  NOTE: This information is only used for _display_ purposes: it in
     *  no way affects any of the arithmetic of the contract, including
     *  {IERC20-balanceOf} and {IERC20-transfer}.
     */
    public function decimals() {
        return $this->decimals;
    }

    /**
     * @SmartContractView
     * Returns the value of tokens in existence.
     */
    public function totalSupply() {
        return $this->intToAmount($this->totalSupply);
    }

    /**
     * @SmartContractView
     *
     * @param $account
     * @return string|null
     * @throws Exception
     *
     * Returns the value of tokens owned by `account`.
     */
    public function balanceOf($account) {
        if(!valid($account)) {
            $this->error("Invalid account address");
        }
        $balance = isset($this->balances[$account]) ? $this->balances[$account] : 0;
        return $this->intToAmount($balance);
    }

    /**
     * @param $amount
     * Internal function that converts decimal amount to amount that will be stored in db
     * @return string
     */
    private function amountToInt($amount) {
        return bcmul($amount, bcpow(10, $this->decimals));
    }

    /**
     * @param $int
     * Internal function that converts stored ammount to decimal
     * @return string|null
     */
    private function intToAmount($int) {
        return bcdiv($int, bcpow(10, $this->decimals), $this->decimals);
    }

    /**
     * @SmartContractTransact
     *
     * @param $to
     * @param $value
     * @return void
     * @throws Exception
     *
     *  Moves a `value` amount of tokens from the caller's account to `to`.
     *  Returns a boolean value indicating whether the operation succeeded.
     *  Emits a {Transfer} event.
     *
     */
    public function transfer($to, $value) {
        $owner = $this->msgSender();
        $value = $this->amountToInt($value);
        $this->_transfer($owner,$to,$value);
    }

    /**
     * @param $from
     * @param $to
     * @param $value
     * @return void
     * @throws Exception
     *
     * Moves a `value` amount of tokens from `from` to `to`.
     * *
     * This internal function is equivalent to {transfer}, and can be used to
     * e.g. implement automatic token fees, slashing mechanisms, etc.
     * *
     * Emits a {Transfer} event.
     * *
     * NOTE: This function is not virtual, {_update} should be overridden instead.
    */
    function _transfer($from,$to,$value) {
        if($from == $to) {
            $this->error("Invalid transfer to self");
        }
        if(!$from) {
            $this->error("Invalid sender");
        }
        if(!$to) {
            $this->error("Invalid receiver");
        }
        if(!valid($from)) {
            $this->error("Invalid from address");
        }
        if(!valid($to)) {
            $this->error("Invalid to address");
        }
        if($value <= 0) {
            $this->error("Invalid value");
        }
        $this->_update($from, $to, $value);
    }

    /**
     * @param $from
     * @param $to
     * @param $value
     * @return void
     * @throws Exception
     *
     * Transfers a `value` amount of tokens from `from` to `to`, or alternatively mints (or burns) if `from`
     * (or `to`) is the zero address. All customizations to transfers, mints, and burns should be done by overriding
     * this function.
     *
     * Emits a {Transfer} event.
    */
    private function _update($from,$to,$value) {
        if(!$from) {
            $this->totalSupply = bcadd($this->totalSupply, $value);
        } else {
            $fromBalance = $this->balances[$from];
            if($fromBalance < $value) {
                $this->error("Insufficient balance from=$from fromBalance=$fromBalance value=$value");
            }
            $this->balances[$from] = bcsub($fromBalance, $value);
        }
        if(!$to) {
            $this->totalSupply = bcsub($this->totalSupply,$value);
        } else {
            $this->balances[$to] = bcadd($this->balances[$to], $value);
        }
        $this->emitEvent("Transfer", ["from"=>$from, "to"=>$to, "value"=>$value]);
    }

    /**
     * @SmartContractTransact
     * @param $spender
     * @param $value
     * @return void
     * @throws Exception
     *
     * Sets a `value` amount of tokens as the allowance of `spender` over the
     * caller's tokens.
     *
     * Returns a boolean value indicating whether the operation succeeded.
     *
     * IMPORTANT: Beware that changing an allowance with this method brings the risk
     * that someone may use both the old and the new allowance by unfortunate
     * transaction ordering. One possible solution to mitigate this race
     * condition is to first reduce the spender's allowance to 0 and set the
     * desired value afterwards:
     * https://github.com/ethereum/EIPs/issues/20#issuecomment-263524729
     *
     * Emits an {Approval} event.
     */
    public function approve($spender, $value) {
        if(!valid($spender)) {
            $this->error("Invalid spender address");
        }
        $owner = $this->msgSender();
        $value = $this->amountToInt($value);
        $this->_approve($owner,$spender,$value, true);
    }

    /**
     * @param $owner
     * @param $spender
     * @param $value
     * @param $emitEvent
     * @return void
     * @throws Exception
     *
     * Sets `value` as the allowance of `spender` over the `owner` s tokens.
     *
     * This internal function is equivalent to `approve`, and can be used to
     * e.g. set automatic allowances for certain subsystems, etc.
     *
     * Emits an {Approval} event.
     *
     * Requirements:
     *
     * - `owner` cannot be the zero address.
     * - `spender` cannot be the zero address.
     *
     * Overrides to this logic should be done to the variant with an additional `bool emitEvent` argument.
     */
    private function _approve($owner,$spender,$value, $emitEvent) {
        if(!$owner) {
            $this->error("Invalid approver");
        }
        if(!$spender) {
            $this->error("Invalid spender");
        }
        $this->setAllowance($owner, $spender, $value);
        if($emitEvent) {
            $this->emitEvent('Approval', ['owner' => $owner, 'spender' => $spender, 'value' => $value]);
        }
    }

    /**
     * @param $owner
     * @param $spender
     * @param $value
     * @return void
     *
     * Internal function that stores owner allowances as json data in db
     */
    private function setAllowance($owner,$spender,$value) {
        if (!isset($this->allowances[$owner])) {
            $ownerAllowances = [];
        } else {
            $ownerAllowances = json_decode($this->allowances[$owner], true);
        }
        $ownerAllowances[$spender] = $value;
        $this->allowances[$owner]=json_encode($ownerAllowances);
    }

    /**
     * @param $owner
     * @param $spender
     * @return int|mixed
     *
     * Internal function that retrieves owner alowances from json
     */
    private function getAllowance($owner, $spender) {
        if(isset($this->allowances[$owner])) {
            $ownerAllowances = json_decode($this->allowances[$owner], true);
        } else {
            return 0;
        }
        return $ownerAllowances[$spender] ?? 0;
    }

    /**
     * @SmartContractView
     * @param $owner
     * @param $spender
     * @return string|null
     * @throws Exception
     *
     * Returns the remaining number of tokens that `spender` will be
     * allowed to spend on behalf of `owner` through {transferFrom}. This is
     * zero by default.
     *
     * This value changes when {approve} or {transferFrom} are called.
     */
    public function allowance($owner, $spender) {
        if(!valid($owner)) {
            $this->error("Invalid owner address");
        }
        if(!valid($spender)) {
            $this->error("Invalid spender address");
        }
        $allowance = $this->getAllowance($owner, $spender);
        return $this->intToAmount($allowance);
    }

    /**
     * @SmartContractTransact
     * @param $from
     * @param $to
     * @param $value
     * @return void
     * @throws Exception
     *
     * Moves a `value` amount of tokens from `from` to `to` using the
     * allowance mechanism. `value` is then deducted from the caller's
     * allowance.
     *
     * Emits a {Transfer} event.
    */
    public function transferFrom($from, $to, $value) {
        if(!valid($from)) {
            $this->error("Invalid from address");
        }
        if(!valid($to)) {
            $this->error("Invalid to address");
        }
        $value = $this->amountToInt($value);
        $spender = $this->msgSender();
        $this->_spendAllowance($from, $spender, $value);
        $this->_transfer($from, $to, $value);
    }

    /**
     * @param $owner
     * @param $spender
     * @param $value
     * @return void
     * @throws Exception
     *
     * Updates `owner` s allowance for `spender` based on spent `value`.
     *
     *  Does not update the allowance value in case of infinite allowance.
     *  Revert if not enough allowance is available.
     *
     *  Does not emit an {Approval} event.
     *
     */
    private function _spendAllowance($owner, $spender, $value) {
        $allowance = $this->allowance($owner, $spender);
        $currentAllowance = $this->amountToInt($allowance);
        if($currentAllowance != PHP_INT_MAX) {
            if($currentAllowance < $value) {
                $this->error("Insufficient allowance spender=$spender currentAllowance=$currentAllowance value=$value");
            }
            $this->_approve($owner, $spender, bcsub($currentAllowance,$value), false);
        }
    }

    /**
     * @return mixed
     *
     * Returns the address of sender
     */
    public function msgSender() {
        _log("src=".$this->tx['src']." dst=".$this->tx['dst']);
        return $this->tx['src'];
    }

    /**
     * @param $event
     * @param $data
     * @return void
     *
     * Internal function that emits events
     */
    private function emitEvent($event, $data) {
        $this->events[$this->tx['id']] = json_encode(['event' => $event, 'data' => $data]);
    }

    /**
     * @SmartContractView
     * View function to retrieve all events
     */
    public function getEvents() {
        return json_encode($this->events->all());
    }


}
