<?php

/**
 * Class DB
 *
 * A simple wrapper for PDO.
 */
class DB extends PDO
{
    private $error;
    private $sql;
    private $bind;
    private $debugger = 0;
    public $working = "yes";

    public function __construct($dsn, $user = "", $passwd = "", $debug_level = 0)
    {
        $options = [
            PDO::ATTR_PERSISTENT       => false,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
        ];
        $this->debugger = $debug_level;
        try {
            parent::__construct($dsn, $user, $passwd, $options);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            die("Could not connect to the DB - ".$this->error);
        }
    }

    private function debug()
    {
        global $_config;
        if (!$this->debugger) {
//            return;
        }
        $error = ["Error" => $this->error];
        if (!empty($this->bind)) {
            $error["Bind Parameters"] = trim(print_r($this->bind, true));
        }

        $backtrace = debug_backtrace();
	    $backtrace_str = [];
        if (!empty($backtrace)) {
            foreach ($backtrace as $info) {
                if ($info["file"] != __FILE__) {
	                $backtrace_str[] = $info["file"]." at line ".$info["line"];
                }
            }
        }
        _log("SQL ERROR:".str_repeat("-", 50));
        foreach ($backtrace_str as $line) {
	        _log("SQL ERROR: BACKTRACE: " . $line);
        }
        foreach ($error as $key => $val) {
	        _log("SQL ERROR:" . $key.": ".$val);
        }
	    if (!empty($this->sql)) {
		    $lines = explode(PHP_EOL, $this->sql);
		    foreach ($lines as $line) {
			    _log("SQL Statement:" . $line);
		    }
	    }
	    if (!empty($this->bind)) {
			$bind = print_r($this->bind, 1);
		    $lines = explode(PHP_EOL, $bind);
		    foreach ($lines as $line) {
			    _log("SQL params:" . $line);
		    }
	    }
        _log("SQL ERROR:" . json_encode($this->sql));
        _log("SQL ERROR:" . json_encode($this->error));
    }

    private function cleanup($bind, $sql = "")
    {
        if (!is_array($bind)) {
            if (!empty($bind)) {
                $bind = [$bind];
            } else {
                $bind = [];
            }
        }

        foreach ($bind as $key => $val) {
            if (str_replace($key, "", $sql) == $sql) {
                unset($bind[$key]);
            }
        }
        return $bind;
    }

    public function single($sql, $bind = "")
    {
        $this->sql = trim($sql);
        $this->bind = $this->cleanup($bind, $sql);
        $this->error = "";
        if(function_exists('microtime')) {
            $time1 = @microtime(true);
        }
        try {
            $pdostmt = $this->prepare($this->sql);
            if ($pdostmt->execute($this->bind) !== false) {
                $res =  $pdostmt->fetchColumn();
	            $this->logSql($this->sql, $time1);
				return $res;
            }
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->debug();
            return false;
        }
    }

    public function run($sql, $bind = "", $param=true)
    {
        $this->sql = trim($sql);
        $this->bind = $param ?  $this->cleanup($bind, $sql) : $bind;
        $this->error = "";
        if(function_exists('microtime')) {
	        $time1 = @microtime(true);
        }

        try {
            $pdostmt = $this->prepare($this->sql);
            if ($pdostmt->execute($this->bind) !== false) {
                if (preg_match("/^(".implode("|", ["select", "describe", "pragma", "show"]).") /i", $this->sql)) {
                    $res =  $pdostmt->fetchAll(PDO::FETCH_ASSOC);
					$this->logSql($this->sql, $time1);
					return $res;
                } elseif (preg_match("/^(".implode("|", ["delete", "insert", "update"]).") /i", $this->sql)) {
	                $res = $pdostmt->rowCount();
	                $this->logSql($this->sql, $time1);
					return $res;
                }
            }
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->debug();
            return false;
        }
    }

	private function logSql($sql, $start) {
//		$time2 = microtime(true);
//		$diff = round(($time2-$start)*1000);
//		$GLOBALS['sql_counts'][$sql]['count']++;
//		$GLOBALS['sql_counts'][$sql]['total_time']+=$diff;
//		_log("SQL EXEC time=$diff ms sql=$sql", 5);
	}

    public function row($sql, $bind = "", $param = true)
    {
        $query = $this->run($sql, $bind, $param);
        if(!$query) {
            return false;
        }
        if (count($query) == 0) {
            return false;
        }
        if (count($query) >= 1) {
            return $query[0];
        }
    }

    public function setConfig($name, $value) {
	    $sql="select val from config where cfg = :cfg";
	    $row = $this->row($sql,[':cfg'=>$name]);
	    if($row) {
		    $sql="update config set val = :val where cfg = :cfg";
	    } else {
		    $sql="insert into config (val, cfg) values (:val, :cfg)";
	    }
	    $this->run($sql, [':val'=>$value, ':cfg'=>$name]);
    }

	public function getConfig($name) {
		$sql="select val from config where cfg = :cfg";
		return $this->single($sql,[':cfg'=>$name]);
	}

    public function isSqlite() {
    	global $_config;
    	return substr($_config['db_connect'], 0, 6)=== "sqlite";
    }

    public function lockTables() {
    	if(!$this->isSqlite()) {
	        $this->exec("LOCK TABLES blocks WRITE, accounts WRITE, transactions WRITE, mempool WRITE, masternode WRITE, peers write, config WRITE");
	    }
    }

    function unlockTables() {
	    if(!$this->isSqlite()) {
		    $this->exec("UNLOCK TABLES");
	    }
    }

	function if() {
		if(!$this->isSqlite()) {
			return "iif";
		} else {
			return "if";
		}
	}

	static function concat(...$args) {
		global $db;
		if(!$db->isSqlite()) {
			return "concat(".implode(",", $args).")";
		} else {
			return implode(" || ", $args);
		}
	}

    function fkCheck($enable = true) {
	    if($this->isSqlite()) {
		    $this->run("PRAGMA foreign_keys = ".($enable ? "on" : "off").";");
	    } else {
		    $this->run("SET foreign_key_checks=".($enable ? "1": "0").";");
	    }
    }

    function truncate($table) {
        _log("truncate table $table", 3);
	    if($this->isSqlite()) {
			$this->run("delete from $table");
	    } else {
	        $this->run("TRUNCATE TABLE $table");
	    }
    }

    static function unixTimeStamp() {
    	global $db;
	    if($db->isSqlite()) {
			return "strftime('%s', 'now')";
	    } else {
			return 'UNIX_TIMESTAMP()';
	    }
    }

    static function random() {
	    global $db;
	    if($db->isSqlite()) {
		    return "random()";
	    } else {
		    return 'rand()';
	    }
    }

    static function autoInc() {
	    global $db;
	    if($db->isSqlite()) {
		    return "integer primary key autoincrement";
	    } else {
		    return 'int auto_increment primary key';
	    }
    }

    static function reconnect() {
        global $_config;
        return new DB($_config['db_connect'], $_config['db_user'], $_config['db_pass'], $_config['enable_logging']);
    }

}
