<?php
/*
The MIT License (MIT)
Copyright (c) 2018 AroDev
Copyright (c) 2021 PHPCoin

phpcoin.net

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE
OR OTHER DEALINGS IN THE SOFTWARE.
*/
require_once dirname(__DIR__).'/include/init.inc.php';
header('Content-Type: application/json');

$q = $_GET['q'];

$t1=microtime(true);

$info = "";
$data = json_decode(trim($_POST['data']), true);

$lock_name = false;
$lock_filename = false;
if($q=="submitBlock") {
    $lock_name = "submitBlock-".$data['id'];
}


if($lock_name) {
    $lock_filename = ROOT.'/tmp/peer-'.$lock_name.'.lock';
//    _log("PEER: check lock process peer $lock_name filename=$lock_filename");
    if (!@mkdir($lock_filename, 0700)) {
        //_log("PEER: Request q=$q $lock_name BUSY");
        api_err("Peer busy");
    }
}




register_shutdown_function(function () use ($t1, $q, $lock_filename) {
    $t2=microtime(true);
    $diff = $t2-$t1;
    if($lock_filename) {
        @rmdir($lock_filename);
    }
//    if($diff > 1) {
//        _log("PEER: Request q=$q time=" . $diff);
//    }
});

PeerRequest::processRequest();



if(method_exists(PeerRequest::class, $q)) {
	call_user_func([PeerRequest::class, $q]);
} else {
	api_err("Invalid request: $q");
}

