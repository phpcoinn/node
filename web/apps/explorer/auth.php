<?php

require_once dirname(__DIR__) . '/apps.inc.php';
CommonSessionHandler::setup();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'error' => 'POST required']);
    exit;
}

$pending = isset($_SESSION['explorer_connect']) && is_array($_SESSION['explorer_connect'])
    ? $_SESSION['explorer_connect']
    : null;
unset($_SESSION['explorer_connect']);

$token = trim((string)($_POST['token'] ?? ''));
$validRequest = $pending !== null
    && (int)($pending['expires'] ?? 0) >= time()
    && isset($pending['token'])
    && hash_equals((string)$pending['token'], $token);
if (!$validRequest) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'error' => 'Login request expired']);
    exit;
}

$return = (string)($pending['return'] ?? '/apps/explorer/');
if (!str_starts_with($return, '/') || str_starts_with($return, '//') || !Security::isSafeRedirect($return)) {
    $return = '/apps/explorer/';
}

if (($_POST['action'] ?? '') === 'logout') {
    unset($_SESSION['account']);
    header('Location: ' . $return);
    exit;
}

header('Content-Type: application/json');
if (($_POST['action'] ?? '') !== 'login') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid action']);
    exit;
}

$address = trim((string)($_POST['address'] ?? ''));
$publicKey = trim((string)($_POST['public_key'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$signature = trim((string)($_POST['signature'] ?? ''));
$signed = json_decode($message, true);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$expectedOrigin = $scheme . '://' . $_SERVER['HTTP_HOST'];
$issuedAt = is_array($signed) ? (int)($signed['issued_at'] ?? 0) : 0;

$validSignature = false;
if (is_array($signed) && strlen($address) <= 128 && strlen($publicKey) <= 1024
    && strlen($signature) <= 1024 && strlen($message) <= 2048
    && hash_equals($expectedOrigin, (string)($signed['domain'] ?? ''))
    && hash_equals($address, (string)($signed['address'] ?? ''))
    && !empty($signed['nonce'])
    && abs((int)(microtime(true) * 1000) - $issuedAt) <= 120000
    && $address !== '' && $publicKey !== '' && $signature !== '') {
    try {
        $validSignature = hash_equals($address, Account::getAddress($publicKey))
            && ec_verify($message, $signature, $publicKey, '');
    } catch (Throwable $error) {
        $validSignature = false;
    }
}

if (!$validSignature) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Invalid wallet signature']);
    exit;
}

$_SESSION['account'] = ['address' => $address, 'public_key' => $publicKey];
echo json_encode(['status' => 'ok', 'redirect' => $return]);
