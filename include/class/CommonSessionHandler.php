<?php

class CommonSessionHandler implements SessionHandlerInterface {

    private $path;

    private static function safeId($id)
    {
        return preg_replace('/[^a-zA-Z0-9,-]/', '', (string) $id);
    }

    #[\ReturnTypeWillChange]
    public function close()
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function destroy($id)
    {
        $sess_file = $this->path."/sess_".self::safeId($id);
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
        $safeId = self::safeId($id);
        $sessionFilePath = $this->path . '/sess_' . $safeId; // Construct the full path to the session file.
        $sessionData = file_get_contents($sessionFilePath);  // returns the data as a string or FALSE on failure

        return ($sessionData !== false) ? $sessionData : ''; // Return the data if successful, or '' otherwise.
    }

    #[\ReturnTypeWillChange]
    public function write($id, $data)
    {
        $safeId = self::safeId($id);
        if ($safeId === '') return false;
        $ret= file_put_contents($this->path."/sess_$safeId", $data, LOCK_EX) === false ? false : true;
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

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sessions_dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if(!$file->isFile()) {
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

    static function setup($session_id = null, $namespace = null) {
        ini_set('session.use_strict_mode', '1');
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $handler = new CommonSessionHandler();
        session_set_save_handler($handler, true);
        $sessions_dir = ROOT."/tmp/sessions";
        if ($namespace !== null && $namespace !== '') {
            $sessions_dir .= '/'.hash('sha256', (string) $namespace);
        }
        @mkdir($sessions_dir, 0700, true);
        session_save_path($sessions_dir);
        if(!empty($session_id)) {
            $safeId = self::safeId($session_id);
            if ($safeId === '') {
                throw new InvalidArgumentException('Invalid session ID');
            }
            session_id($safeId);
        }
        @session_start(["gc_probability"=>0]);
    }
}
