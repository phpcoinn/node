<?php

class SmartContractBase
{

	protected $sender;

	protected $value;
	protected $height;

	protected $address;

	public function isOwner() {
		return $this->sender == $this->address;
	}

	public function error($msg) {
		throw new Exception($msg);
	}

	public function setFields($args) {
		$tx = $args['transaction'];
		$this->sender = $tx['src'];
		$this->value = floatval($tx['val']);
		$this->height = intval($args['height'])+1;
		$this->address = SC_ADDRESS;
	}

	public function log($s) {
		$log_file = ROOT . "/smart_contract.log";
		$s = date("r")." ".$this->address.": ".$s.PHP_EOL;
		@file_put_contents($log_file, $s, FILE_APPEND);
	}

}
