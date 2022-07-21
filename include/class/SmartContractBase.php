<?php

class SmartContractBase
{

	protected $sender;

	protected $value;

	protected $address;

	public function isOwner() {
		return $this->sender == $this->address;
	}

	public function error($msg) {
		throw new Exception($msg);
	}

	public function setFields($tx_sender, $tx_value) {
		$this->sender = $tx_sender;
		$this->value = floatval($tx_value);
		$this->address = SC_ADDRESS;
	}

	public function log($s) {
		$log_file = ROOT . "/smart_contract.log";
		$s = date("r")." ".$this->address.": ".$s.PHP_EOL;
		@file_put_contents($log_file, $s, FILE_APPEND);
	}

}
