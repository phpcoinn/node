<?php

class SmartContractMap implements ArrayAccess, Countable
{

    const ANNOTATION = "SmartContractMap";

    protected $name;
    protected $height;
    protected $address;


    function __construct($address, $name, $height) {
        $this->name = $name;
        $this->height = $height;
        $this->address = $address;
    }


    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return SmartContractState::existsStateVar($this->address, $this->height, $this->name, $offset);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if($offset ===  null || strlen($offset)==0) {
            return $this->count();
        }
        return SmartContractState::getStateVar($this->address, $this->height, $this->name, $offset);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if($offset==null || strlen($offset)==0) {
            $offset = $this->count();
        }
        SmartContractState::setStateVar($this->address, $this->height, $this->name, $value, $offset);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        if($offset=== null || strlen($offset)==0) {
            return;
        }
        if($this->offsetExists($offset)) {
            SmartContractState::setStateVar($this->address, $this->height, $this->name, null, $offset);
        }
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        return SmartContractState::countStateVar($this->address, $this->height, $this->name);
    }

    public function keys() {
        return SmartContractState::stateVarKeys($this->address, $this->height, $this->name);
    }

    public function all() {
        return SmartContractState::stateAll($this->address, $this->height, $this->name);
    }

    public function clear($key=null) {
        if($this->height >= UPDATE_15_EXTENDED_SC_HASH_V2) {
            throw new Exception("Call to deleted function");
        }
        return SmartContractState::stateClear($this->address, $this->name, $key);
    }

    public function inc($key) {
        if($key===null || strlen($key)==0) {
            return;
        }
        if (isset($this[$key])) {
            $pw = $this[$key];
        } else {
            $pw = 0;
        }
        $pw ++;
        $this[$key] = $pw;
    }

    public function add($key, $n) {
        if($key===null || strlen($key)==0) {
            return;
        }
        if (isset($this[$key])) {
            $pw = $this[$key];
        } else {
            $pw = 0;
        }
        $pw += $n;
        $this[$key] = $pw;
    }

    public function query($sql, $params) {
        return SmartContractState::query($this->address, $sql, $params);
    }

}
