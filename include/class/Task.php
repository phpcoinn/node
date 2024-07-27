<?php

class Task
{

    static function availableTasks() {
        return [Sync::class, NodeMiner::class, Dapps::class, Masternode::class, Cron::class];
    }

    static $run_interval = 60;

    static $options = [];

    static function isEnabled() {
        global $_config;
        $name = static::$name;
        return isset($_config[$name]) && $_config[$name];
    }

    static function enable() {
        global $db;
        $name = static::$name;
        $db->setConfig($name, 1);
    }

    static function disable() {
        global $db;
        $name = static::$name;
        $db->setConfig($name, 0);
    }

    static function canDisable() {
        return true;
    }

    static function checkAndRun()
    {
        $name = static::$name;
//        _log("Task: check $name enabled=".static::isEnabled());
        if (static::isEnabled()) {
            static::runTask();
        } else {
            _log("Task:$name not enabled",2);
        }
    }

    static function checkLock() {
        $name = static::$name;
        $pid=getmypid();
        $lock_dir = ROOT."/tmp/cli-$name.lock";
        if(!@mkdir($lock_dir)){
            _log("task $name locked", 2);
            exit();
        }

        function signalHandler($signal) {
            switch($signal) {
                case SIGTERM:
                case SIGINT:
                    exit;
            }
        }
        pcntl_signal(SIGTERM, 'signalHandler', false);
        pcntl_signal(SIGINT, 'signalHandler', false);

        register_shutdown_function(function() use ($pid, $lock_dir,$name){
            if($pid==getmypid()){
                @rmdir($lock_dir);
            }

        });
    }

    static function runTask() {
        $name = static::$name;
            $dir = ROOT . "/cli";
            $cmd = "php $dir/$name.php";
            _log("Task: run $name task",3);
            $userInfo = posix_getpwuid(posix_geteuid());
            $user=null;
        if($userInfo['name']!="www-data" && $userInfo['name']=="root") {
                $user="www-data";
            }
            Nodeutil::runSingleProcess($cmd, null, $user);
    }

    static function processTask() {
        $name = static::$name;
        self::checkLock();
        self::processArgs();
        $userInfo = posix_getpwuid(posix_geteuid());
        $user=$userInfo['name'];
        try {
            $t1=microtime(true);
            static::process();
            $t2=microtime(true);
            $time = number_format($t2 - $t1, 3);
            _log("Task: $name - completed in $time");
        } catch (Throwable $e) {
            _log("Task: $name - error running task: ".$e->getMessage());
        }

    }

    static function getTaskStatus() {
        $name=static::$name;
        $status['title']=static::$title;
        $status['name']=$name;
        $status['enabled']=static::isEnabled();
        $res = Nodeutil::psAux(ROOT."/cli/$name.php", null, "TZ=UTC0 timeout 1 ps -e -o pid,pcpu,pmem,lstart,user,cmd");
        $status['running']=!empty($res);
        if($status['running']){
            $status['process_cnt']=count($res);
            $process = trim($res[0]);
            $arr = preg_split("/\s+/", $process);
            $pid = $arr[0];
            $started =array_slice($arr, 3, 5);
            $started = implode(" ", $started);
            $started = strtotime($started);
            $cpu = $arr[1];
            $memory = $arr[2];
            $data['running'] = true;
            $data['started'] = $started;
            $data['pid'] = $pid;
            $data['cpu'] = $cpu;
            $data['memory'] = $memory;
            $data['owner'] = $arr[8];
            $status['process']=$data;
        } else {
            global $db;
            $last_run_time = $db->getConfig("ts-task-$name");
            $status['last_run_time']=$last_run_time;
        }
        return $status;
    }

    static function hasArg($a) {
        return isset(self::$options[$a]);
    }

    static function processArgs() {
        global $argv;
        self::$options = [];
        $args = implode(" ",array_slice($argv, 1));
        $arr = explode("--", $args);
        foreach($arr as $line) {
            if(strlen($line)==0) {
                continue;
            }
            $line=trim($line);
            if(strpos($line, "=")!== false) {
                $arr2=explode("=", $line);
                self::$options[$arr2[0]]=$arr2[1];
            } else if(strpos($line, " ")!== false) {
                $arr2=explode(" ", $line);
                self::$options[$arr2[0]]=$arr2[1];
            } else {
                self::$options[$line]="";
            }
        }
        _log("Options: ".json_encode(self::$options),5);
        if(self::hasArg("stop")) {
            $res = Nodeutil::psAux(ROOT."/cli/".static::$name.".php");
            if(!empty($res)) {
                foreach ($res as $process) {
                    $arr = preg_split("/\s+/", $process);
                    $pid = $arr[1];
                    if ($pid != getmypid()) {
                        $scmd = "kill $pid";
                        shell_exec($scmd);
                        _log("Killed process $pid");
                        exit;
                    }
                }
            }
            exit;
        }
        if(self::hasArg("enable")) {
            static::enable();
	        exit;
        }
        if(self::hasArg("disable")) {
            static::disable();
	        exit;
        }
        if(self::hasArg("status")) {
            $status = static::getTaskStatus();
            api_echo($status);
            exit;
        }
    }

    static function getArg($a, $def) {
        if(isset(self::$options[$a])) {
            return self::$options[$a];
        } else {
            return $def;
        }
    }

    static function checkLongRunning() {
        $status = self::getTaskStatus();
        if(isset($status['process'])) {
            $started = $status['process']['started'];
            $elapsed = time() - $started;
            _log("Check long running ".static::$name. " elapsed=".$elapsed, 3);
            if($elapsed > 60*10) {
                $pid=$status['process']['pid'];
                $scmd = "kill $pid";
                shell_exec($scmd);
            }
        } else {
            $lock_dir = ROOT . "/tmp/cli-".static::$name.".lock";
            if(file_exists($lock_dir)) {
                _log("Lock ".static::$name. " exists without process", 3);
                @rmdir($lock_dir);
            }
        }

    }


}
