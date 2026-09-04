<?php

class SmartContractBase
{

	protected $src;
	protected $dst;
	protected $tx;
	protected $id;

	protected $value;
	protected $height;

	public $address;

	public function isOwner() {
		return $this->src == $this->address;
	}

	public function error($msg) {
		throw new Exception($msg);
	}

    function isExec()
    {
        return $this->tx['type'] == TX_TYPE_SC_EXEC;
    }

    function isSend()
    {
        return $this->tx['type'] == TX_TYPE_SC_SEND;
    }

	public function setFields($args) {
		$tx = $args['transaction'];
		$this->src = $tx['src'];
		$this->dst = $tx['dst'];
		$this->value = floatval($tx['val']);
		$this->height = intval($args['height']);
		$this->address = $args['address'];
        $this->tx = $tx;
        $this->id = $tx['id'];
	}

    public function debug($log) {
        if(SmartContractContext::$virtual) {
            SmartContractContext::$debug_logs[]=$log;
        }
    }

    /**
     * Pay native PHPCoin from this contract to $to.
     * Applied atomically by the node after this call succeeds.
     */
    public function transfer($to, $amount) {
        Transaction::send($to, $amount);
    }

    /**
     * Execute a state-changing method on another smart contract.
     * Nested spender/sender is this contract's address.
     */
    public function execSmartContract($contract, $method, $params) {
        return InterContract::exec($this, $contract, $method, $params);
    }

    /**
     * Call a view method on another smart contract.
     */
    public function callSmartContract($contract, $method, $params) {
        return InterContract::call($this, $contract, $method, $params);
    }

    /**
     * Get fields for external contract calls
     * 
     * NOTE: This method is kept for backward compatibility but is not used
     * since inter-contract calls are disabled.
     * 
     * @return array Transaction and context fields
     */
    public function getExtFields() {
        $args['transaction']=$this->tx;
        $args['height']=$this->height;
        $args['address']=$this->address;
        return $args;
    }

    public function log($msg) {
        SmartContractContext::$logs[] = $msg;
    }

}
