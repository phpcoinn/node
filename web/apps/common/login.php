<?php
require_once dirname(__DIR__) . '/apps.inc.php';

CommonSessionHandler::setup();

$redirect = $_GET['redirect'] ?? '/';
if (!Security::isSafeRedirect($redirect)) {
    $redirect = '/';
}

$app = $_GET['app'] ?? 'Node';
$app = substr(preg_replace('/[^A-Za-z0-9 _-]/', '', (string)$app), 0, 40);
if ($app === '') {
    $app = 'Node';
}

$_SESSION['request_code'] = bin2hex(random_bytes(8));

$url = '/dapps.php?url=' . MAIN_DAPPS_ID . '/gateway/auth.php'
    . '?app=' . rawurlencode($app)
    . '&request_code=' . rawurlencode($_SESSION['request_code'])
    . '&redirect=' . rawurlencode($redirect);

header('Location: ' . $url);
exit;
