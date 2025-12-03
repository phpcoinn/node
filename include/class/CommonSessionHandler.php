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

    /**
     * Reads session data from a file.
     *
     * @param string $id The session ID.
     * @return string The session data, or an empty string if the session does not exist or on failure.
     */
    #[\ReturnTypeWillChange]
    public function read(string $id): string
    {
        $safeId = preg_replace('/[^a-zA-Z0-9,-]/', '', $id); // Sanitize the session ID to prevent path traversal attacks.
        $sessionFilePath = $this->path . '/sess_' . $safeId; // Construct the full path to the session file.
        $sessionData = file_get_contents($sessionFilePath);  // returns the data as a string or FALSE on failure

        return ($sessionData !== false) ? $sessionData : ''; // Return the data if successful, or '' otherwise.
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
