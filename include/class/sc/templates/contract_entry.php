<?php
/**
 * Smart Contract Entry Point
 * 
 * This file is used as the entry point for compiled PHAR files.
 * It can be executed directly for testing/debugging by setting these variables:
 *   $__PHP_FILE__ - The contract PHP file to load
 *   $__CLASS_NAME__ - The class name to instantiate
 * 
 * For PHAR compilation, these variables are replaced with actual values.
 */

// Variables (will be replaced during PHAR compilation, or set for direct execution)
$__PHP_FILE__ = $__PHP_FILE__ ?? 'test1.php';
$__CLASS_NAME__ = $__CLASS_NAME__ ?? 'SmartContract';

const TX_TYPE_SC_CREATE = 5;
const TX_TYPE_SC_EXEC = 6;
const TX_TYPE_SC_SEND = 7;

//TODO: set values from chain
const UPDATE_15_EXTENDED_SC_HASH_V2=1117000;
const CHAIN_PREFIX = "38";

// SmartContractBase and StateManager are loaded in the PHAR stub before this file
// They should already be available, but load them here as fallback for direct execution
// Note: In PHAR execution, these are already loaded by the stub
$phar_path = Phar::running(false);
if (!$phar_path) {
    // Only load if not running from PHAR (direct execution mode)
    @require_once 'SmartContractBase.php';
    @require_once 'StateManager.php';
}

// Load contract file
// When running from PHAR, we must use phar:// prefix because file:// wrapper is disabled in sandbox
if ($phar_path) {
    // Running from PHAR - use explicit phar:// path
    require 'phar://' . $phar_path . '/' . $__PHP_FILE__;
    require 'phar://' . $phar_path . '/' . "SmartContractWrapper.php";
} else {
    // Direct execution (not from PHAR)
    require $__PHP_FILE__;
}

// Read input from STDIN or pre-read file data (file for debugging, STDIN for production)
// Bootstrap reads the file BEFORE blocking file access and stores it in $GLOBALS
$input_data = $GLOBALS['SANDBOX_INPUT_DATA'] ?? null;
if ($input_data !== null) {
    $data = json_decode($input_data, true);
} else {
    $data = json_decode(stream_get_contents(STDIN), true);
}

// Extract methods taht will be called, input, execution mode, address, state mode, and initial state
$methods = [];
if(!isset($data['input'])) {
    throw new Exception("Smart contract failed: input data missing.");
}

if(isset($data['input'])) {
    $input = $data['input'];
    $type = $input['type'] ?? null;
    if(empty($type)) {
        throw new Exception("Smart contract failed: input type missing.");
    }
    if($type == 'process') {
        //Must go through all transactions to check methods
        if (!isset($input['transactions'])) {
            throw new Exception("Smart contract failed: transactions missing.");
        }
        $transactions = $input['transactions'];
        foreach ($transactions as $transaction) {
            $message = $transaction['msg'] ?? null;
            if (empty($message)) {
                throw new Exception("Smart contract failed: transaction message missing.");
            }
            $type = $transaction['type'] ?? null;
            if ($type == TX_TYPE_SC_CREATE) {
                $method = "deploy";
                $methods[$method] = $method;
            } else {
                $message_decoded = json_decode(base64_decode($message), true);
                $method = $message_decoded['method'];
                $methods[$method] = $method;
            }
        }
    } else if ($type == "view") {
        if(!isset($input['method'])) {
            throw new Exception("Smart contract failed: method missing.");
        }
        $method = $input['method'];
        $methods[$method] = $method;
    } else {
        throw new Exception("Smart contract failed: Unhandled input type $type.");
    }
}




$input = $data['input'] ?? [];
$execution_mode = $data['mode'] ?? 'exec'; // 'exec' or 'send'
$address = $data['address'] ?? null;
$state_mode = $data['state_mode'] ?? 'virtual'; // 'virtual' or 'db'
$initial_state = $data['initial_state'] ?? []; // State loaded outside sandbox

// Store execution mode in GLOBALS so contract can access it
$GLOBALS['SANDBOX_EXECUTION_MODE'] = $execution_mode;

// Load interface.json to get address and allowed methods
$interface = null;
$phar_path_for_interface = Phar::running(false);
if ($phar_path_for_interface) {
    try {
        $phar = new Phar($phar_path_for_interface);
        if (isset($phar['interface.json'])) {
            $interface = json_decode($phar['interface.json']->getContent(), true);
        if ($interface && isset($interface['address']) && $address === null) {
                $address = $interface['address'];
            }
        }
    } catch (Exception $e) {
        throw new Exception("Smart contract failed: Error reading interface: " . $e->getMessage());
    }
}

$methods = array_values($methods);
foreach($methods as $method) {
    if($method == 'deploy') {
        continue;
    } else {
        $found = false;
        foreach($interface['methods'] as $interface_method) {
            if($interface_method['name'] == $method) {
                $found = true;
                break;
            }
        }
        foreach($interface['views'] as $interface_method) {
            if($interface_method['name'] == $method) {
                $found = true;
                break;
            }
        }
        if(!$found) {
            throw new Exception("Smart contract failed: Method $method not found.");
        }
    }
}

// Instantiate class
ob_start();
$contract = new $__CLASS_NAME__();
$contractWrapper = new SmartContractWrapper($contract, $address);
$result = $contractWrapper->run($input, $initial_state);

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
@ob_clean();
echo json_encode($result);

