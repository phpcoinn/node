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
        // Session cleanup is performed by Cron::process(). Scanning the entire
        // session directory from a web request can overload busy nodes.
        return 0;
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

    static function cleanupExpired($max_lifetime = null) {
        $sessions_dir = ROOT."/tmp/sessions";
        if(!is_dir($sessions_dir)) {
            return ["checked"=>0, "deleted"=>0];
        }

        if($max_lifetime === null) {
            $max_lifetime = intval(ini_get("session.gc_maxlifetime"));
        }
        if($max_lifetime <= 0) {
            $max_lifetime = 1440;
        }

        $checked = 0;
        $deleted = 0;
        $cutoff = time() - $max_lifetime;

        foreach (new DirectoryIterator($sessions_dir) as $file) {
            if($file->isDot() || !$file->isFile()) {
                continue;
            }
            if(strpos($file->getFilename(), "sess_") !== 0) {
                continue;
            }

            $checked++;
            $filename = $file->getPathname();
            $modified = @filemtime($filename);
            if($modified !== false && $modified < $cutoff && @unlink($filename)) {
                $deleted++;
            }
        }

        return ["checked"=>$checked, "deleted"=>$deleted];
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
        @session_start(["gc_probability"=>0]);
    }
}
