<?php
/**
 * Generate Sandbox Configuration Files
 * 
 * This script generates all sandbox configuration files:
 * 1. disable_functions_list.txt - List of functions to disable
 * 2. php-sandbox.ini - Production PHP INI file
 * 3. php-sandbox-debug.ini - Debug PHP INI file
 * 
 * Usage:
 *   php scripts/generate_sandbox_config.php              # Generate all files
 *   php scripts/generate_sandbox_config.php --list-only  # Generate only disable list
 *   php scripts/generate_sandbox_config.php --ini-only   # Generate only INI files (requires disable list)
 */

$base_dir = dirname(__DIR__);
$whitelist_file = __DIR__ . '/whitelist.php';
$disable_list_file = $base_dir . '/disable_functions_list.txt';
$ini_prod_file = $base_dir . '/php-sandbox.ini';
$ini_debug_file = $base_dir . '/php-sandbox-debug.ini';

// Parse command line arguments
$list_only = in_array('--list-only', $argv);
$ini_only = in_array('--ini-only', $argv);

// Load whitelist
if (!file_exists($whitelist_file)) {
    echo "❌ Error: $whitelist_file not found.\n";
    exit(1);
}

$allowed = require $whitelist_file;

// Functions that must remain enabled (needed by bootstrap or sandbox operation)
$required_functions = [
    'get_defined_functions',  // Needed by bootstrap to generate disable list
    'array_keys',            // Needed by bootstrap for GLOBALS clearing
    'array_diff',            // Needed by bootstrap
    'in_array',              // Needed by bootstrap
    'count',                 // Needed by bootstrap
    'stream_get_contents',   // Needed for STDIN access in contracts
    'stream_get_wrappers',   // Needed by bootstrap to unregister wrappers
    'stream_wrapper_unregister', // Needed by bootstrap
    'spl_autoload_register', // Needed by bootstrap
    'set_error_handler',     // Needed by bootstrap
    'register_shutdown_function', // Needed by bootstrap
    'set_exception_handler',  // Needed by bootstrap
    'get_class',              // Needed by error handlers
    'stripos',                // Needed by bootstrap for Reflection check
    'strpos',                 // Needed by bootstrap
    'method_exists',         // Needed by SmartContractWrapper::invoke() (no longer uses Reflection)
    'call_user_func_array',  // Needed by SmartContractWrapper::invoke() (replaces ReflectionMethod->invoke())
    'json_encode',            // Needed by exception handler
    'exit',                   // Needed by exception handler
    'defined',                // Needed to check constants
    'constant',               // Needed to get constants
    'function_exists',        // Needed for testing and general function checks (safe - just checks existence)
];

// Debug-only functions (only available in debug mode)
$debug_only_functions = [
    'getenv',  // For debugging - can leak system info, non-deterministic
];

// Combine whitelist with required functions (production)
$allowed_functions_prod = array_merge($allowed, $required_functions);
$allowed_functions_prod = array_unique($allowed_functions_prod);

// Combine whitelist with required functions + debug functions (debug mode)
$allowed_functions_debug = array_merge($allowed, $required_functions, $debug_only_functions);
$allowed_functions_debug = array_unique($allowed_functions_debug);

// Get all internal PHP functions
$all_functions = get_defined_functions()['internal'];

// Get functions to disable for production (everything except production allowed)
$disabled_functions_prod = array_diff($all_functions, $allowed_functions_prod);
sort($disabled_functions_prod);
$disable_list_prod = implode(',', $disabled_functions_prod);

// Get functions to disable for debug (everything except debug allowed)
$disabled_functions_debug = array_diff($all_functions, $allowed_functions_debug);
sort($disabled_functions_debug);
$disable_list_debug = implode(',', $disabled_functions_debug);

// Statistics
$total_functions = count($all_functions);
$whitelisted_count_prod = count($allowed_functions_prod);
$whitelisted_count_debug = count($allowed_functions_debug);
$disabled_count_prod = count($disabled_functions_prod);
$disabled_count_debug = count($disabled_functions_debug);

// Generate disable list file (production version)
if (!$ini_only) {
    file_put_contents($disable_list_file, $disable_list_prod);
    
    echo "Whitelist-only Function Disabling\n";
    echo "==================================\n\n";
    echo "Total PHP internal functions: $total_functions\n";
    echo "Production mode:\n";
    echo "  Whitelisted functions: $whitelisted_count_prod\n";
    echo "  Disabled functions: $disabled_count_prod\n";
    echo "Debug mode:\n";
    echo "  Whitelisted functions: $whitelisted_count_debug (includes debug-only functions)\n";
    echo "  Disabled functions: $disabled_count_debug\n\n";
    echo "Disable list written to: $disable_list_file (production)\n";
    echo "Length: " . strlen($disable_list_prod) . " characters\n\n";
    
    // Preview first 100 functions
    echo "Preview (first 100 functions to disable):\n";
    echo implode(', ', array_slice($disabled_functions_prod, 0, 100)) . "...\n\n";
}

// Generate INI files
if (!$list_only) {
    // Check if disable list exists
    if (!file_exists($disable_list_file)) {
        echo "❌ Error: $disable_list_file not found.\n";
        echo "Please run without --ini-only flag first to generate the disable list.\n";
        exit(1);
    }
    
    // Read disable list (in case we're only generating INI files)
    if ($ini_only) {
        // Get all internal PHP functions (needed for recalculation)
        $all_functions = get_defined_functions()['internal'];
        $total_functions = count($all_functions);
        
        $disable_list_prod = trim(file_get_contents($disable_list_file));
        $disabled_functions_prod = explode(',', $disable_list_prod);
        $disabled_count_prod = count($disabled_functions_prod);
        $whitelisted_count_prod = $total_functions - $disabled_count_prod;
        
        // Recalculate debug list
        $allowed_functions_prod = array_diff($all_functions, $disabled_functions_prod);
        $debug_only_functions = ['getenv'];
        $allowed_functions_debug = array_merge($allowed_functions_prod, $debug_only_functions);
        $allowed_functions_debug = array_unique($allowed_functions_debug);
        $disabled_functions_debug = array_diff($all_functions, $allowed_functions_debug);
        sort($disabled_functions_debug);
        $disable_list_debug = implode(',', $disabled_functions_debug);
        $whitelisted_count_debug = count($allowed_functions_debug);
        $disabled_count_debug = count($disabled_functions_debug);
    }
    
    // Generate production INI file
    $ini_prod_content = <<<INI
; --- Sandbox PHP INI (No Filesystem, No Extensions, No Globals) ---
engine = On
short_open_tag = Off
expose_php = Off

; No filesystem
allow_url_fopen = Off
allow_url_include = Off
user_ini.filename =

; No globals
register_globals = Off
variables_order = "EGPCS"
auto_globals_jit = Off

; No functions except whitelisted ones
; WHITELIST-ONLY APPROACH: Disable ALL functions except those explicitly whitelisted
; This is more secure than blacklisting - any new dangerous functions are automatically blocked
; Generated by: php scripts/generate_sandbox_config.php
; Total functions disabled: {$disabled_count_prod} (out of {$total_functions} total PHP functions)
; Whitelisted functions: {$whitelisted_count_prod} (safe functions + bootstrap requirements)
; To regenerate: php scripts/generate_sandbox_config.php
disable_functions = {$disable_list_prod}

; No stream wrappers allowed (will be unregistered in bootstrap)
phar.readonly = On
phar.require_hash = On

; No reflection
zend.exception_ignore_args = On

; Resource limits
max_execution_time = 30
memory_limit = 32M

; No error leakage
display_errors = Off
log_errors = Off
report_memleaks = Off
html_errors = Off
INI;

    // Generate debug INI file
    $ini_debug_content = <<<INI
; --- Sandbox PHP INI (Debug Mode - No Filesystem, No Extensions, No Globals) ---
engine = On
short_open_tag = Off
expose_php = Off

; No filesystem
allow_url_fopen = Off
allow_url_include = Off
user_ini.filename =

; No globals
register_globals = Off
variables_order = "EGPCS"
auto_globals_jit = Off

; No functions except whitelisted ones
; WHITELIST-ONLY APPROACH: Disable ALL functions except those explicitly whitelisted
; This is more secure than blacklisting - any new dangerous functions are automatically blocked
; Generated by: php scripts/generate_sandbox_config.php
; Total functions disabled: {$disabled_count_debug} (out of {$total_functions} total PHP functions)
; Whitelisted functions: {$whitelisted_count_debug} (safe functions + bootstrap requirements + debug-only functions)
; Debug-only functions: getenv (for debugging - can leak system info, non-deterministic)
; To regenerate: php scripts/generate_sandbox_config.php
disable_functions = {$disable_list_debug}

; No stream wrappers allowed (will be unregistered in bootstrap)
phar.readonly = On
phar.require_hash = On

; No reflection
zend.exception_ignore_args = On

; Resource limits
max_execution_time = 1
memory_limit = 32M

; Error display enabled for debugging
display_errors = On
log_errors = On
display_startup_errors = On
report_memleaks = Off
html_errors = Off
INI;

    // Write files
    file_put_contents($ini_prod_file, $ini_prod_content);
    file_put_contents($ini_debug_file, $ini_debug_content);
    
    echo "✅ Generated INI files successfully!\n\n";
    echo "Production INI: $ini_prod_file\n";
    echo "Debug INI: $ini_debug_file\n\n";
}

if (!$list_only && !$ini_only) {
    echo "Statistics:\n";
    echo "  Total PHP functions: $total_functions\n";
    echo "  Production mode:\n";
    echo "    Whitelisted functions: $whitelisted_count_prod\n";
    echo "    Disabled functions: $disabled_count_prod\n";
    echo "  Debug mode:\n";
    echo "    Whitelisted functions: $whitelisted_count_debug\n";
    echo "    Disabled functions: $disabled_count_debug\n";
    echo "    Debug-only functions: " . implode(', ', $debug_only_functions) . "\n";
}

echo "\n✅ All configuration files generated successfully!\n";

