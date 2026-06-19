<?php

function num($val, $dec = COIN_DECIMALS) {
	return @number_format($val, $dec, '.', '');
}

function floatvalue($val) {
    $clean = str_replace(',', '', $val);
    $float = floatval($clean);
    return $float;
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

if(!function_exists("ec_verify")) {
    function ec_verify($data, $signature, $key, $chain_id = CHAIN_ID)
    {
        // transform the base58 key to PEM
        $public_key = coin2pem($key);

        $data = $chain_id . $data;

        $signature = base58_decode($signature);

        $pkey = openssl_pkey_get_public($public_key);

        $res = openssl_verify($data, $signature, $pkey, OPENSSL_ALGO_SHA256);

        _log("Sign: verify signature for data: $data chain_id=$chain_id res=$res", 5);
        if ($res === 1) {
            return true;
        }
        return false;
    }
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

if (!function_exists('pem2coin')) {
function pem2coin($pem)
{
    $pem = str_replace("-----BEGIN PUBLIC KEY-----", "", $pem);
    $pem = str_replace("-----END PUBLIC KEY-----", "", $pem);
    $pem = str_replace("-----BEGIN EC PRIVATE KEY-----", "", $pem);
    $pem = str_replace("-----END EC PRIVATE KEY-----", "", $pem);
    $pem = str_replace("\n", "", $pem);
    $pem = base64_decode($pem);
    return base58_encode($pem);
}
}

function generateKeyPair()
{
    $args = [
        "curve_name" => "secp256k1",
        "private_key_type" => OPENSSL_KEYTYPE_EC,
    ];

    $key = openssl_pkey_new($args);
    if ($key === false) {
        return false;
    }

    openssl_pkey_export($key, $privatePem);

    if (PHP_VERSION_ID > 80000) {
        $in = sys_get_temp_dir() . "/phpcoin.asym.in.pem";
        $out = sys_get_temp_dir() . "/phpcoin.asym.out.pem";
        file_put_contents($in, $privatePem);
        shell_exec("openssl ec -in " . escapeshellarg($in) . " -out " . escapeshellarg($out) . " >/dev/null 2>&1");
        if (is_file($out)) {
            $privatePem = file_get_contents($out);
            unlink($out);
        }
        if (is_file($in)) {
            unlink($in);
        }
    }

    $publicDetails = openssl_pkey_get_details($key);
    if ($publicDetails === false || !isset($publicDetails['key'])) {
        return false;
    }

    return [
        'private_key' => pem2coin($privatePem),
        'public_key' => pem2coin($publicDetails['key']),
    ];
}

function generateEncryptionKeyPair()
{
    $account = generateKeyPair();
    if ($account === false) {
        return false;
    }

    return [
        'privateKey' => $account['private_key'],
        'publicKey' => $account['public_key'],
        'privatePem' => coin2pem($account['private_key'], true),
        'publicPem' => coin2pem($account['public_key']),
    ];
}

function phpcoin_asym_hkdf(string $sharedSecret): string
{
    return hash_hkdf('sha256', $sharedSecret, 32, 'PHPCoin asymmetric encryption v1', '');
}

function encryptForPublicKey(string $plaintext, string $recipientPublicKey): string
{
    $ephemeral = generateEncryptionKeyPair();
    if ($ephemeral === false) {
        throw new RuntimeException('Ephemeral account generation failed');
    }

    $senderPrivate = openssl_pkey_get_private($ephemeral['privatePem']);
    $recipientPublic = openssl_pkey_get_public(coin2pem($recipientPublicKey));
    if ($senderPrivate === false || $recipientPublic === false) {
        throw new RuntimeException('Key import failed');
    }

    $sharedSecret = openssl_pkey_derive($recipientPublic, $senderPrivate, 32);
    if ($sharedSecret === false) {
        throw new RuntimeException('Shared secret derivation failed');
    }

    $key = phpcoin_asym_hkdf($sharedSecret);
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'PHPCoin asymmetric encryption v1',
        16
    );
    if ($ciphertext === false) {
        throw new RuntimeException('Encryption failed');
    }

    return base64_encode(json_encode([
        'alg' => 'ECDH-secp256k1+A256GCM',
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'epk' => $ephemeral['publicKey'],
        'ciphertext' => base64_encode($ciphertext),
    ], JSON_UNESCAPED_SLASHES));
}

function decryptWithPrivateKey(string $payloadB64, string $recipientPrivateKey): string
{
    $payload = json_decode(base64_decode($payloadB64, true), true);
    if (!is_array($payload)) {
        throw new RuntimeException('Invalid encrypted payload');
    }

    $recipientPrivate = openssl_pkey_get_private(coin2pem($recipientPrivateKey, true));
    $ephemeralPublic = openssl_pkey_get_public(coin2pem($payload['epk']));
    if ($recipientPrivate === false || $ephemeralPublic === false) {
        throw new RuntimeException('Key import failed');
    }

    $sharedSecret = openssl_pkey_derive($ephemeralPublic, $recipientPrivate, 32);
    if ($sharedSecret === false) {
        throw new RuntimeException('Shared secret derivation failed');
    }

    $key = phpcoin_asym_hkdf($sharedSecret);
    $plaintext = openssl_decrypt(
        base64_decode($payload['ciphertext']),
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        base64_decode($payload['iv']),
        base64_decode($payload['tag']),
        'PHPCoin asymmetric encryption v1'
    );
    if ($plaintext === false) {
        throw new RuntimeException('Decryption failed');
    }

    return $plaintext;
}

if(!function_exists('base58_decode')) {

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

// converts hex to base58
function hex2coin($hex)
{
	$data = hex2bin($hex);
	return base58_encode($data);
}

if(!function_exists('valid')) {
    function valid($address)
    {
        $addressBin = base58_decode($address);
        $addressHex = bin2hex($addressBin);
        $addressChecksum = substr($addressHex, -8);
        $baseAddress = substr($addressHex, 0, -8);
        if (substr($baseAddress, 0, 2) != CHAIN_PREFIX) {
            return false;
        }
        $checksumCalc1 = hash('sha256', $baseAddress);
        $checksumCalc2 = hash('sha256', $checksumCalc1);
        $checksumCalc3 = hash('sha256', $checksumCalc2);
        $checksum = substr($checksumCalc3, 0, 8);
        $valid = $addressChecksum == $checksum;
        return $valid;
    }
}

function getAddress($public_key) {
    if(empty($public_key)) return null;
    $hash1=hash('sha256', $public_key);
    $hash2=hash('ripemd160',$hash1);
    $baseAddress=CHAIN_PREFIX.$hash2;
    $checksumCalc1=hash('sha256', $baseAddress);
    $checksumCalc2=hash('sha256', $checksumCalc1);
    $checksumCalc3=hash('sha256', $checksumCalc2);
    $checksum=substr($checksumCalc3, 0, 8);
    $addressHex = $baseAddress.$checksum;
    $address = base58_encode(hex2bin($addressHex));
    return $address;
}

function get_sc_disable_functions() {
    return 'exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,'.
        'show_source,ini_set,getenv,sleep,set_time_limit,error_reporting,'.
        'rand,shuffle,array_rand,mt_rand,uniqid,date,time,microtime,gettimeofday,sleep,usleep,getrandmax';
}
