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


/**
 * @api {get} /api.php 01. Basic Information
 * @apiName Info
 * @apiGroup API
 * @apiDescription Each API call will return the result in JSON format.
 * There are 2 objects, "status" and "data".
 *
 * The "status" object returns "ok" when the transaction is successful and "error" on failure.
 *
 * The "data" object returns the requested data, as sub-objects.
 *
 * The parameters must be sent either as POST['data'], json encoded array or independently as GET.
 *
 * @apiSuccess {String} status "ok"
 * @apiSuccess {String} data The data provided by the api will be under this object.
 *
 *
 *
 * @apiSuccessExample {json} Success-Response:
 *{
 *   "status":"ok",
 *   "data":{
 *      "obj1":"val1",
 *      "obj2":"val2",
 *      "obj3":{
 *         "obj4":"val4",
 *         "obj5":"val5"
 *      }
 *   }
 *}
 *
 * @apiError {String} status "error"
 * @apiError {String} result Information regarding the error
 *
 * @apiErrorExample {json} Error-Response:
 *     {
 *       "status": "error",
 *       "data": "The requested action could not be completed."
 *     }
 */

use PHPCoin\Blacklist;

require_once __DIR__.'/include/init.inc.php';

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
