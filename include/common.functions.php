<?php

function num($val, $dec = COIN_DECIMALS) {
	return @number_format($val, $dec, '.', '');
}

// sign data with private key
function ec_sign($data, $key, $chain_id = CHAIN_ID)
{
	// transform the base58 key format to PEM
	$private_key = coin2pem($key, true);

	$data = $chain_id . $data;

	_log("Sign: sign data $data chain_id=$chain_id", 5);

	$pkey = openssl_pkey_get_private($private_key);

	$k = openssl_pkey_get_details($pkey);


	openssl_sign($data, $signature, $pkey, OPENSSL_ALGO_SHA256);

	// the signature will be base58 encoded
	return base58_encode($signature);
}

// converts the key in base58 to PEM
function coin2pem($data, $is_private_key = false)
{
	$data = base58_decode($data);
	$data = base64_encode($data);

	$dat = str_split($data, 64);
	$data = implode("\n", $dat);

	if ($is_private_key) {
		return "-----BEGIN EC PRIVATE KEY-----\n".$data."\n-----END EC PRIVATE KEY-----\n";
	}
	return "-----BEGIN PUBLIC KEY-----\n".$data."\n-----END PUBLIC KEY-----\n";
}

function base58_decode($base58)
{
	$alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
	$base = strlen($alphabet);

	// Type Validation
	if (is_string($base58) === false) {
		return false;
	}
	// If the string is empty, then the decoded string is obviously empty
	if (strlen($base58) === 0) {
		return '';
	}
	$indexes = array_flip(str_split($alphabet));
	$chars = str_split($base58);
	// Check for invalid characters in the supplied base58 string
	foreach ($chars as $char) {
		if (isset($indexes[$char]) === false) {
			return false;
		}
	}
	// Convert from base58 to base10
	$decimal = gmp_init($indexes[$chars[0]], 10);
	for ($i = 1, $l = count($chars); $i < $l; $i++) {
		$decimal = gmp_mul($decimal, $base);
		$decimal = gmp_add($decimal, $indexes[$chars[$i]]);
	}
	// Convert from base10 to base256 (8-bit byte array)
	$output = '';
	while (gmp_cmp($decimal, 0) > 0) {
		list($decimal, $byte) = gmp_div_qr($decimal, 256);
		$output = pack('C', gmp_intval($byte)).$output;
	}
	// Now we need to add leading zeros
	foreach ($chars as $char) {
		if ($indexes[$char] === 0) {
			$output = "\x00".$output;
			continue;
		}
		break;
	}
	return $output;
}



// Base58 encoding/decoding functions - all credits go to https://github.com/stephen-hill/base58php
function base58_encode($string)
{
	$alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
	$base = strlen($alphabet);
	// Type validation
	if (is_string($string) === false) {
		return false;
	}
	// If the string is empty, then the encoded string is obviously empty
	if (strlen($string) === 0) {
		return '';
	}
	// Now we need to convert the byte array into an arbitrary-precision decimal
	// We basically do this by performing a base256 to base10 conversion
	$hex = unpack('H*', $string);
	$hex = reset($hex);
	$decimal = gmp_init($hex, 16);
	// This loop now performs base 10 to base 58 conversion
	// The remainder or modulo on each loop becomes a base 58 character
	$output = '';
	while (gmp_cmp($decimal, $base) >= 0) {
		list($decimal, $mod) = gmp_div_qr($decimal, $base);
		$output .= $alphabet[gmp_intval($mod)];
	}
	// If there's still a remainder, append it
	if (gmp_cmp($decimal, 0) > 0) {
		$output .= $alphabet[gmp_intval($decimal)];
	}
	// Now we need to reverse the encoded data
	$output = strrev($output);
	// Now we need to add leading zeros
	$bytes = str_split($string);
	foreach ($bytes as $byte) {
		if ($byte === "\x00") {
			$output = $alphabet[0].$output;
			continue;
		}
		break;
	}
	return (string)$output;
}

// convers hex to base58
function hex2coin($hex)
{
	$data = hex2bin($hex);
	return base58_encode($data);
}

function valid($address)
{
    $addressBin=base58_decode($address);
    $addressHex=bin2hex($addressBin);
    $addressChecksum=substr($addressHex, -8);
    $baseAddress = substr($addressHex, 0, -8);
    if(substr($baseAddress, 0, 2) != CHAIN_PREFIX) {
        return false;
    }
    $checksumCalc1=hash('sha256', $baseAddress);
    $checksumCalc2=hash('sha256', $checksumCalc1);
    $checksumCalc3=hash('sha256', $checksumCalc2);
    $checksum=substr($checksumCalc3, 0, 8);
    $valid = $addressChecksum == $checksum;
    return $valid;
}

function get_sc_disable_functions() {
    return 'exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,'.
        'show_source,ini_set,getenv,sleep,set_time_limit,error_reporting,'.
        'rand,shuffle,array_rand,mt_rand,uniqid,date,time,microtime,gettimeofday,sleep,usleep,getrandmax';
}
