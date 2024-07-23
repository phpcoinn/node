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
     */
    public function name() {
        return $this->name;
    }

    /**
     * @SmartContractView
     */
    public function symbol() {
        return $this->symbol;
    }

    /**
     * @SmartContractView
     */
    public function decimals() {
        return $this->decimals;
    }

    /**
     * @SmartContractView
     */
    public function totalSupply() {
        return $this->intToAmount($this->totalSupply);
    }

    /**
     * @SmartContractView
     */
    public function balanceOf($account) {
        if(!valid($account)) {
            $this->error("Invalid account address");
        }
        $balance = isset($this->balances[$account]) ? $this->balances[$account] : 0;
        return $this->intToAmount($balance);
    }

    private function amountToInt($amount) {
        return bcmul($amount, bcpow(10, $this->decimals));
    }

    private function intToAmount($int) {
        return bcdiv($int, bcpow(10, $this->decimals), $this->decimals);
    }

    /**
     * @SmartContractTransact
     */
    public function transfer($to, $value) {
        $owner = $this->msgSender();
        $value = $this->amountToInt($value);
        $this->_transfer($owner,$to,$value);
    }

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
     */
    public function approve($spender, $value) {
        if(!valid($spender)) {
            $this->error("Invalid spender address");
        }
        $owner = $this->msgSender();
        $value = $this->amountToInt($value);
        $this->_approve($owner,$spender,$value, true);
    }

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

    private function setAllowance($owner,$spender,$value) {
        if (!isset($this->allowances[$owner])) {
            $ownerAllowances = [];
        } else {
            $ownerAllowances = json_decode($this->allowances[$owner], true);
        }
        $ownerAllowances[$spender] = $value;
        $this->allowances[$owner]=json_encode($ownerAllowances);
    }

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

    public function msgSender() {
        _log("src=".$this->tx['src']." dst=".$this->tx['dst']);
        return $this->tx['src'];
    }

    private function emitEvent($event, $data) {
        $this->events[$this->tx['id']] = json_encode(['event' => $event, 'data' => $data]);
    }

    /**
     * @SmartContractView
     */
    public function getEvents() {
        return json_encode($this->events->all());
    }


}
