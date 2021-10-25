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
header('Content-Type: application/json');

require_once dirname(__DIR__).'/include/init.inc.php';

Api::checkAccess();
$q = $_GET['q'];
$data = Api::getData();

if(empty($q)) {
	api_err("Invalid request");
	return;
}

if(method_exists(Api::class, $q)) {
	call_user_func([Api::class, $q], $data);
	return;
} else {
	$str = str_replace(' ', '', ucwords(str_replace('-', ' ', $q)));
	$str[0] = strtolower($str[0]);
	if(method_exists(Api::class, $str)) {
		call_user_func([Api::class, $str], $data);
		return;
	} else {
		api_err("Invalid request");
		return;
	}
}
