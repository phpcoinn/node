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
     * Execute a method on another smart contract
     * 
     * NOTE: Inter-contract calls are disabled in this version for security reasons.
     * This feature may be re-enabled in a future version with proper security measures.
     * 
     * @param string $contract Contract address (not used - feature disabled)
     * @param string $method Method name (not used - feature disabled)
     * @param array $params Method parameters (not used - feature disabled)
     * @throws Exception Always throws exception indicating feature is disabled
     */
    public function execSmartContract($contract, $method, $params) {
        $this->error("Inter-contract calls are disabled in this version. Smart contracts cannot call other smart contracts.");
    }

    /**
     * Call a view method on another smart contract
     * 
     * NOTE: Inter-contract calls are disabled in this version for security reasons.
     * This feature may be re-enabled in a future version with proper security measures.
     * 
     * @param string $contract Contract address (not used - feature disabled)
     * @param string $method Method name (not used - feature disabled)
     * @param array $params Method parameters (not used - feature disabled)
     * @return mixed Never returns - always throws exception
     * @throws Exception Always throws exception indicating feature is disabled
     */
    public function callSmartContract($contract, $method, $params) {
        $this->error("Inter-contract calls are disabled in this version. Smart contracts cannot call other smart contracts.");
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
