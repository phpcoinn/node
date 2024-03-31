<?php

class SmartContractMap implements ArrayAccess, Countable
{

    protected $name;
    protected $height;
    protected $db;

    function __construct($db, $name, $height) {
        $this->name = $name;
        $this->db = $db;
        $this->height = $height;
    }


    public function offsetExists($offset)
    {
        return SmartContractBase::existsStateVar($this->db, SC_ADDRESS, $this->name, $offset);
    }

    public function offsetGet($offset)
    {
        if(strlen($offset)==0) {
            return $this->count();
        }
        return SmartContractBase::getStateVar($this->db, SC_ADDRESS, $this->name, $offset);
    }

    public function offsetSet($offset, $value)
    {
        if(strlen($offset)==0) {
            $offset = $this->count();
        }
        SmartContractBase::setStateVar($this->db, SC_ADDRESS, $this->height, $this->name, $value, $offset);
    }

    public function offsetUnset($offset)
    {
        if(strlen($offset)==0) {
            return;
        }
        if($this->offsetExists($offset)) {
            SmartContractBase::setStateVar($this->db, SC_ADDRESS, $this->height, $this->name, null, $offset);
        }
    }

    public function count()
    {
        return SmartContractBase::countStateVar($this->db, SC_ADDRESS, $this->name);
    }

    public function keys() {
        return SmartContractBase::stateVarKeys($this->db, SC_ADDRESS, $this->name);
    }

    public function all() {
        return SmartContractBase::stateAll($this->db, SC_ADDRESS, $this->name);
    }

    public function clear($key=null) {
        if($this->height >= UPDATE_15_EXTENDED_SC_HASH_V2) {
            throw new Exception("Call to deleted function");
        }
        return SmartContractBase::stateClear($this->db, SC_ADDRESS, $this->name, $key);
    }

    public function inc($key) {
        if(strlen($key)==0) {
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
        if(strlen($key)==0) {
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

}
