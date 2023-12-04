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
        return SmartContractBase::getStateVar($this->db, SC_ADDRESS, $this->name, $offset);
    }

    public function offsetSet($offset, $value)
    {
        if($offset == null) {
            $offset = $this->count();
        }
        SmartContractBase::setStateVar($this->db, SC_ADDRESS, $this->height, $this->name, $value, $offset);
    }

    public function offsetUnset($offset)
    {
        SmartContractBase::setStateVar($this->db, SC_ADDRESS, $this->height, $this->name, null, $offset);
    }

    public function count()
    {
        return SmartContractBase::countStateVar($this->db, SC_ADDRESS, $this->name);
    }

}
