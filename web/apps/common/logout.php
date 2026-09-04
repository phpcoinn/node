<?php
require_once dirname(__DIR__) . '/apps.inc.php';

CommonSessionHandler::setup();
$_SESSION = [];
@session_destroy();

$redirect = $_GET['redirect'] ?? '/';
if (!Security::isSafeRedirect($redirect)) {
    $redirect = '/';
}

header('Location: ' . $redirect);
exit;
