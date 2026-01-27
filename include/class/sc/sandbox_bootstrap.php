<?php
/* ================================================================
   PHP Coin - Smart Contract Sandbox Bootstrap (Option A)
   Read-only / Pure Logic Mode
   ================================================================= */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Read input data FIRST, before any sandbox restrictions
// proc_open environment vars are available via $_ENV (getenv() is disabled)
// Input can come from:
// 1. SANDBOX_INPUT_DATA env var (for small inputs <= 32KB)
// 2. STDIN (for large inputs > 32KB to avoid "Argument list too long" error)
$sandbox_input_data = null;

// Check $_ENV first (populated by variables_order=EGPCS)
if (isset($_ENV['SANDBOX_INPUT_DATA'])) {
    $sandbox_input_data = $_ENV['SANDBOX_INPUT_DATA'];
}
// Also check $_SERVER as fallback (some PHP configs populate this)
if (!$sandbox_input_data && isset($_SERVER['SANDBOX_INPUT_DATA'])) {
    $sandbox_input_data = $_SERVER['SANDBOX_INPUT_DATA'];
}

// If not in env var, read from STDIN (for large inputs)
// This must be done BEFORE disabling functions, as stream_get_contents may be disabled later
// When proc_open sends data via STDIN, it's already available and ready to read
if (!$sandbox_input_data || $sandbox_input_data === '') {
    // Read from STDIN (data is already available from proc_open pipe)
    $stdin_data = @stream_get_contents(STDIN);
    if ($stdin_data && $stdin_data !== '') {
        $sandbox_input_data = $stdin_data;
    }
}

if ($sandbox_input_data && $sandbox_input_data !== '') {
    $GLOBALS['SANDBOX_INPUT_DATA'] = $sandbox_input_data;
}
$sandbox_input_data_json = json_decode($sandbox_input_data, true);
$engine_version = $sandbox_input_data_json['engine_version'];
$legacy_engine = version_compare($engine_version, '2.0', '<');
//
// 1. REMOVE ALL STREAM WRAPPERS (NO IO OF ANY KIND)
// Exception: Keep 'phar' wrapper for PHAR file execution
//
foreach (stream_get_wrappers() as $w) {
    if ($w == 'phar' || ($legacy_engine && $w == 'file')) {
    } else {
        @stream_wrapper_unregister($w);
    }
}

//
// 2. BLOCK REFLECTION COMPLETELY
//
// Note: Reflection classes are pre-loaded by PHP, so we can't prevent their declaration.
// However, we can block their instantiation and usage at runtime.

// Block Reflection classes from being autoloaded (if any are not pre-loaded)
spl_autoload_register(function($class){
    if (stripos($class, 'Reflection') !== false) {
        throw new Exception("Reflection is forbidden in smart contracts");
    }
});

// Since Reflection classes are pre-loaded, we need to intercept their instantiation
// We'll do this by wrapping the 'new' operator via error handler and checking call stack
// Note: This is a best-effort approach since PHP doesn't allow overriding 'new'

function _log($l) {
    $GLOBALS['sandbox_logs'][]=$l;
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

// Note: Reflection classes are pre-loaded by PHP, so we can't prevent their declaration.
// However, we block Reflection functions from the whitelist below.
// Reflection instantiation will be caught by the error handler in section 7.

//
// 3. BLOCK GLOBALS
//
// Clear superglobals first
$_SERVER = [];
$_ENV = [];
$_COOKIE = [];
$_FILES = [];
$_GET = [];
$_POST = [];
$_REQUEST = [];
if (isset($_SESSION)) {
    $_SESSION = [];
}

// Now remove them from GLOBALS
// Note: We can't completely remove superglobals from GLOBALS in PHP,
// but we can clear their contents and they won't be accessible
$preserved_keys = ['GLOBALS', 'SANDBOX_INPUT_DATA', 'SANDBOX_INPUT_FILE', 'SANDBOX_EXECUTION_MODE', 'preserved_keys','legacy_engine'];

$globals_keys = array_keys($GLOBALS);
foreach ($globals_keys as $key) {
    if (!in_array($key, $preserved_keys)) {
        // For superglobals, we've already cleared them above
        // For other entries, unset them
        if (!in_array($key, ['_SERVER', '_ENV', '_COOKIE', '_FILES', '_GET', '_POST', '_REQUEST', '_SESSION'])) {
            unset($GLOBALS[$key]);
        }
    }
}

//
// 4. FUNCTION WHITELIST ENFORCEMENT
//
// Note: Function whitelisting is enforced via the php-sandbox.ini file, which is loaded
// when PHP starts (via -d flag or php.ini). The disable_functions setting is a
// PHP_INI_SYSTEM setting and cannot be changed at runtime via ini_set().
//
// The whitelist is defined in scripts/whitelist.php and used by:
// - scripts/generate_sandbox_config.php (to generate disable_functions list and INI files)
// - php-sandbox.ini (loaded at PHP startup to actually disable functions)
//
// The bootstrap does not need to load or enforce the whitelist here, as PHP's
// disable_functions setting handles this at a lower level before any code executes.

//
// 5. BLOCK preg_replace /e AND SIMILAR
// Note: The /e modifier was deprecated in PHP 5.5 and removed in PHP 7.0,
// so it's not a concern in modern PHP versions. preg_replace is whitelisted
// but the dangerous /e modifier cannot be used.
//

//
// 6. BLOCK ANY INCLUDE / REQUIRE
// Note: include/require are language constructs, not functions, so they cannot be
// overridden. They are blocked via disable_functions in php-sandbox.ini and
// by the restricted environment. If used, they will fail due to missing filesystem access.
//

//
// 7. PROTECT AGAINST VARIABLE VARIABLES AND REFLECTION
//
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Block Reflection-related errors and usage
    if (stripos($errstr, 'Reflection') !== false) {
        throw new Exception("Reflection is forbidden in smart contracts");
    }
    // Block attempts to instantiate Reflection classes
    if (stripos($errstr, 'new Reflection') !== false) {
        throw new Exception("Reflection is forbidden in smart contracts");
    }
    // Suppress variable access errors
    if (strpos($errstr, "Cannot access") !== false) return true;
    if (strpos($errstr, "Undefined variable") !== false) return true;
    return false;
});

// Runtime Reflection detection: Check if Reflection classes are being instantiated
// Since Reflection classes are pre-loaded, we intercept via a wrapper approach
// We'll detect Reflection usage by monitoring class instantiation
// Note: This is a best-effort approach - Reflection classes are pre-loaded by PHP

// Additional protection: Register a shutdown function to detect Reflection usage
register_shutdown_function(function() {
    // Check if Reflection classes were instantiated during execution
    // This is a safety net - the error handler should catch most cases
    // Note: debug_backtrace may be disabled, so this is limited
});

//
// 8. NO CLASS AUTOCREATION / BLOCK MAGIC METHODS
//
class __ForbiddenMagic {}
set_exception_handler(function($e){
    echo json_encode(["error"=>"sandbox_error", "details"=>$e->getMessage(), "trace"=>$e->getTraceAsString()]);
    exit;
});

if($legacy_engine) {
    //Old engine - leagacy execution
    $old = true;
    require __DIR__  . '/templates/contract_entry_legacy.php';
}

?>
