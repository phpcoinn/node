<?php

class Forker {

    private $parent;
    private $listener;
    private $childs = [];

    static function instance() {
        return new Forker();
    }

    function run(Closure $closure, ...$args) {
        $this->parent = [$closure, $args];
        return $this;
    }

    function fork(Closure $child, ...$args) {
        $this->childs[]=[$child, $args];
        return $this;
    }

    function exec() {
        if($this->parent) {
            $this->parent[0]->call($this, ...$this->parent[1]);
        }
        if(count($this->childs)==0) {
            return;
        }
        $sockets = [];
        foreach ($this->childs as $i=>$child) {
            $socket = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            $pid = pcntl_fork();
            if ($pid == -1) {
                return;
            } else if (!$pid) {
                register_shutdown_function(function(){
                    posix_kill(getmypid(), SIGKILL);
                });
                fclose($socket[0]);
                $res = $child[0]->call($this, ...$child[1]);
                fwrite($socket[1], json_encode($res));
                fclose($socket[1]);
                exit;
            } else {
                fclose($socket[1]);
                $sockets[$i] = $socket;
            }
        }
        while (pcntl_waitpid(0, $status) != -1) ;
        foreach($sockets as $socket) {
            $output = stream_get_contents($socket[0]);
            fclose($socket[0]);
            $this->send($output);
        }
    }

    function execNoWait() {
        if($this->parent) {
            $this->parent[0]->call($this, ...$this->parent[1]);
        }
        if(count($this->childs)==0) {
            return;
        }
        foreach ($this->childs as $i=>$child) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                return;
            } else if (!$pid) {
                if (posix_setsid() == -1) {
                    exit();
                }
                register_shutdown_function(function(){
                    posix_kill(getmypid(), SIGKILL);
                });
                ob_start();
                $child[0]->call($this, ...$child[1]);
                ob_end_clean();
                exit;
            }
        }
    }

    function send($data) {
        if($this->listener) {
            $this->listener->call($this, json_decode($data, true));
        }
    }

    function on(Closure $listener) {
        $this->listener = $listener;
        return $this;
    }

}
