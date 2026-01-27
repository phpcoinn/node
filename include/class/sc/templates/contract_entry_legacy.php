<?php

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/include/sc.inc.php';
require_once '/home/marko/web/phpcoin/node/include/class/SmartContractEngine.php';

$input_data = $GLOBALS['SANDBOX_INPUT_DATA'] ?? null;
if ($input_data !== null) {
    $data = json_decode($input_data, true);
} else {
    $data = json_decode(stream_get_contents(STDIN), true);
}
$initial_state = $data['initial_state'] ?? [];
$interface = $data['interface'] ?? null;

$sc_dir = SmartContractEngine::getRunFolder();
$sc_address = $data['address'] ?? null;
$phar_file = $sc_dir . "/$sc_address.phar";
$require_file = "phar://$phar_file";
require_once ROOT . '/include/sc.inc.php';
$phar = new Phar($phar_file);
$php_files = [];
$phar_path_prefix = 'phar://' . $phar->getPath() . '/';
foreach (new RecursiveIteratorIterator($phar) as $file) {
    if ($file->isFile() && pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'php') {
        // Extract relative path from phar:// path
        $full_path = $file->getPathname();
        if (strpos($full_path, $phar_path_prefix) === 0) {
            $relative_path = substr($full_path, strlen($phar_path_prefix));
        } else {
            // Fallback: use filename if path extraction fails
            $relative_path = $file->getFilename();
        }
        $php_files[] = $relative_path;
    }
}

// Single file PHAR: only one PHP file (the contract file itself)
if (count($php_files) === 1) {
    $entry_point = $php_files[0];
}

// Folder PHAR: check for index.php
foreach ($php_files as $file) {
    if (basename($file) === 'index.php') {
        $entry_point = 'index.php';
    }
}

// Fallback: return first PHP file found
if (empty($entry_point) && !empty($php_files)) {
    $entry_point = $php_files[0];
}

if(empty($entry_point)) {
    echo json_encode([
        'error' => 'empty_entry_point',
        'message' => "Can not determine entry point.",
    ]);
    exit(1);
}

require_once $require_file."/$entry_point";

if(class_exists($sc_address)) {
    $class = new $sc_address();
}

if(defined("SC_CLASS_NAME")) {
    $className = SC_CLASS_NAME;
} else if (class_exists('SmartContract')) {
    $className = 'SmartContract';
}

if(!empty($className)) {
    $class = new $className();
}

$smartContractWrapper = new SmartContractWrapper($class, $sc_address, $interface);
$result = $smartContractWrapper->run($data['input'], $initial_state);

if(empty($result)) {
    echo json_encode([
        'error' => 'empty_result',
        'message' => "Empty result from smart contract call",
    ]);
    exit(1);
}
if(!is_array($result)) {
    echo json_encode([
        'error' => 'invalid_result',
        'message' => "Invalid result from smart contract call",
    ]);
    exit(1);
}
if($result['status'] !== 'ok') {
    echo json_encode([
        'error' => 'error_executing_smart_contract',
        'message' => $result['error'],
        'trace' => $result['trace'],
    ]);
    exit(1);
}
$result['sandbox_logs']=$GLOBALS['sandbox_logs'];
echo json_encode($result);
exit;