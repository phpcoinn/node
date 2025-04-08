<?php

class SmartContractVirtualMap extends SmartContractMap
{

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        $state = $this->loadState();
        if(strlen($offset)==0) {
            return isset($state[$this->name]);
        } else {
            return is_array($state[$this->name]) && key_exists($offset, $state[$this->name]);
        }
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if($offset ===  null || strlen($offset)==0) {
            return $this->count();
        }
        $state = $this->loadState();
        $val = $state[ $this->name];
        if(is_array($val)) {
            if(strlen($offset)==0) {
                return count($val);
            } else {
                return $state[$this->name][$offset];
            }
        } else {
            return $val;
        }
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if($offset==null || strlen($offset)==0) {
            $offset = $this->count();
        }

        $state = $this->loadState();
        $state[$this->name][$offset]=$value;
        $this->storeState($state);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        if(strlen($offset)==0) {
            return;
        }
        if($this->offsetExists($offset)) {

            $state = $this->loadState();
            if($offset==null || strlen($offset)==0) {
                $state[$this->name]=null;
            } else {
                $state[$this->name][$offset]=null;
            }
            $this->storeState($state);
        }
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        $state = $this->loadState();
        return @count($state[$this->name] ?? []);
    }

    public function keys() {
        $state = $this->loadState();
        ksort($state[$this->name]);
        return array_keys($state[$this->name]);
    }

    public function all() {
        $state = $this->loadState();
        ksort($state[$this->name]);
        return $state[$this->name];
    }

    public function clear($key=null) {
        if($this->height >= UPDATE_15_EXTENDED_SC_HASH_V2) {
            throw new Exception("Call to deleted function");
        }

        $state = $this->loadState();
        if($key == null) {
            unset($state[$this->name]);
        } else {
            unset($state[$this->name][$key]);
        }
        $this->storeState($state);
    }

    private function loadState() {
        $state_file = ROOT . '/tmp/sc/'.$this->address.'.state.json';
        $state = json_decode(@file_get_contents($state_file), true);
        return $state;
    }

    private function storeState($state) {
        $state_file = ROOT . '/tmp/sc/'.$this->address.'.state.json';
        file_put_contents($state_file, json_encode($state));
    }

}