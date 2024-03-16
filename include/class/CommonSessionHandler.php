<?php

class CommonSessionHandler implements SessionHandlerInterface {

    private $path;

    #[\ReturnTypeWillChange]
    public function close()
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function destroy($id)
    {
//        _log("Dapps: destroy session");
        $sess_file = $this->path."/sess_$id";
        if (!file_exists($sess_file)) return false;
        $ret = @unlink($sess_file);
        return $ret;
    }

    #[\ReturnTypeWillChange]
    public function gc($max_lifetime)
    {
        $deleted=0;
        _log("Dapps: call session gc");
        foreach (glob($this->path."/sess_*") as $filename) {
            if (filemtime($filename) + $max_lifetime < time()) {
                $res= @unlink($filename);
                if($res) {
                    $deleted++;
                }
            }
        }
        return $deleted;
    }

    #[\ReturnTypeWillChange]
    public function open($path, $name)
    {
        $this->path = $path;
        return(true);
    }

    #[\ReturnTypeWillChange]
    public function read($id)
    {
        $sess_file = $this->path."/sess_$id";
        if(file_exists($sess_file)) $out=@file_get_contents($sess_file);
        return (string) $out;
    }

    #[\ReturnTypeWillChange]
    public function write($id, $data)
    {
        if(empty($data)) return true;
        $ret= file_put_contents($this->path."/sess_$id", $data) === false ? false : true;
        return $ret;
    }

    static function setup($session_id = null) {
        $handler = new CommonSessionHandler();
        session_set_save_handler($handler, true);
        $sessions_dir = ROOT."/tmp/sessions";
        @mkdir($sessions_dir);
        session_save_path($sessions_dir);
        if(!empty($session_id)) {
            session_id($session_id);
        }
        @session_start();
    }
}
