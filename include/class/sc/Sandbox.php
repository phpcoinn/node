<?php
/**
 * Simple Sandbox API - Execute PHAR files in sandbox
 *
 * Usage:
 *   // Compile PHAR using Compiler class
 *   $phar = Compiler::compile('test1.php', 'test');  // From single file
 *   $phar = Compiler::compile('contract_folder/', 'test');  // From folder
 *
 *   // Execute PHAR
 *   Sandbox::exec('test1.phar', 'test', ['input' => 'data']);
 */

require_once __DIR__ . '/StatePersistence.php';
//require_once __DIR__ . '/StateManager.php';

class Sandbox {

    /**
     * Execute a method from a PHAR file in the sandbox
     *
     * @param string $phar_path Path to PHAR file (must be .phar extension)
     * @param array $input Input data for the contract
     * @param string|null $address Contract address (optional, extracted from PHAR if not provided)
     * @param string $state_mode State storage mode: 'virtual' or 'db' (default: 'virtual')
     * @param bool $debug Enable debug mode (shows errors, includes stderr/stdout in response)
     * @return array Execution result
     * @throws InvalidArgumentException if file is not a PHAR or doesn't exist
     */
    public static function exec($phar_path, $input = [], $address = null, $virtual = false, $debug = false) {
        // Validate PHAR file
        if (pathinfo($phar_path, PATHINFO_EXTENSION) !== 'phar') {
            throw new InvalidArgumentException("File must be a PHAR file (.phar extension): $phar_path");
        }

        // Ensure PHAR exists
        if (!file_exists($phar_path)) {
            throw new InvalidArgumentException("PHAR file does not exist: $phar_path");
        }

        $engine_version = "1.0";
        $phar = new Phar($phar_path);
        if (isset($phar['interface.json'])) {
            $interface = json_decode($phar['interface.json']->getContent(), true);
            $engine_version = "2.0";
        } else {
            if(!empty($address)) {
                $interface = SmartContractEngine::getInterface($address);
            }
        }

        // Extract address from PHAR's interface.json if not provided
        if ($address === null) {
            try {
                $phar = new Phar($phar_path);
                if (isset($phar['interface.json'])) {
                    $interface = json_decode($phar['interface.json']->getContent(), true);
                    if ($interface && isset($interface['address'])) {
                        $address = $interface['address'];
                    }
                }
            } catch (Exception $e) {
                // Failed to read interface.json, continue without address
                // State won't be persisted if address is missing
            }
        }

        // Load state before execution (outside sandbox)
        $initial_state = [];
        if ($address && $virtual) {
            try {
                $initial_state = StatePersistence::loadState($address);
            } catch (Exception $e) {
                // Failed to load state, continue with empty state
                $initial_state = [];
            }
        }

        // Execute PHAR in sandbox (pass initial state in input)
        $result = self::runSmartContract($phar_path, [
            'input' => $input,
            'address' => $address,
            'initial_state' => $initial_state,
            'engine_version' => $engine_version,
            'interface' => $interface
        ], $debug);

        // Save state after execution (outside sandbox)
        // Skip state saving if dry_run mode (for mempool validation)
        if ($address && $virtual && isset($result['data']['state'])) {
            StatePersistence::saveState($address, $result['data']['state']);
        }

        return $result;
    }

    /**
     * Execute smart contract code or PHAR file in sandbox
     *
     * @param string $code_or_phar PHP code string or path to PHAR file
     * @param array $input Input data for contract
     * @param bool $debug Enable debug mode (shows errors, includes stderr/stdout)
     * @return array Contract execution result
     * @throws InvalidArgumentException if code is not a string or PHAR file is invalid
     * @throws RuntimeException if PHAR magic bytes detected in code string
     */
    public static function runSmartContract($code_or_phar, $input = [], $debug = false) {
        // Check if it's a PHAR file path (direct execution)
        $is_phar_path = false;
        $phar_file = null;

        // Check for phar:// protocol
        if (is_string($code_or_phar) && strpos($code_or_phar, 'phar://') === 0) {
            // Parse PHAR path
            if (preg_match('#^phar://(.+?)(?:/(.+))?$#', $code_or_phar, $matches)) {
                $phar_file = $matches[1];
                $is_phar_path = true;
            }
        }
        // Check for .phar file extension
        elseif (is_string($code_or_phar) && file_exists($code_or_phar) && pathinfo($code_or_phar, PATHINFO_EXTENSION) === 'phar') {
            $phar_file = $code_or_phar;
            $is_phar_path = true;
        }

        // If it's a PHAR file, execute it directly (sandbox bootstrap will still run)
        if ($is_phar_path && $phar_file) {
            return self::runPharDirectly($phar_file, $input, $debug);
        }

        // Otherwise, treat as code string
        $code = $code_or_phar;

        // Security: Ensure code is a string
        if (!is_string($code)) {
            throw new InvalidArgumentException("Code must be a string or PHAR file path");
        }

        // Security: Block PHAR magic bytes in code (prevent direct PHAR execution)
        // Note: We allow reading FROM PHAR files (via safe_read_phar_file), but we don't allow
        // PHAR magic bytes in the code itself (which would indicate a compiled PHAR being executed)
        if (strpos($code, '__HALT_COMPILER') !== false) {
            throw new RuntimeException("PHAR/compiled code detected in code string - not allowed. Use safe_read_phar_file() to extract code from PHAR files, or pass PHAR path directly.");
        }

        // Create temp directory
        $tmp = sys_get_temp_dir() . "/phpc_sc_" . bin2hex(random_bytes(4));
        mkdir($tmp);

        $contract = "$tmp/contract.php";

        // Write the user code into contract file
        file_put_contents($contract, $code);

        // Get absolute paths for config files
        $sandboxDir = __DIR__;
        $iniFile = $debug ? "php-sandbox-debug.ini" : "php-sandbox.ini";
        $iniFile = escapeshellarg("$sandboxDir/$iniFile");
        $bootstrapFile = escapeshellarg("$sandboxDir/sandbox_bootstrap.php");
        $contractFile = escapeshellarg($contract);

        // Build command with debug options if enabled
        $cmd = "php -c $iniFile -d auto_prepend_file=$bootstrapFile";
        if ($debug) {
            $cmd .= " -d error_reporting=" . E_ALL;
            // Enable Xdebug for CLI debugging
            if (extension_loaded('xdebug')) {
                $cmd .= " -d xdebug.mode=debug";
                $cmd .= " -d xdebug.start_with_request=yes";
                $cmd .= " -d xdebug.idekey=PHPSTORM";
                // Xdebug will connect to IDE on default port 9003 (Xdebug 3.x) or 9000 (Xdebug 2.x)
                // Can be overridden with XDEBUG_CONFIG environment variable
            }
        }
        $cmd .= " $contractFile";

        // Prepare environment variables (for PhpStorm path mapping)
        $env = $_ENV;
        $input_file = null;

        // Check input size - if too large, use STDIN instead of env vars to avoid "Argument list too long" error
        // System limit is typically around 128KB-256KB for environment variables
        $input_json = json_encode($input);
        $input_size = strlen($input_json);
        $max_env_size = 32 * 1024; // 32KB limit for env vars (conservative)

        if ($debug && extension_loaded('xdebug')) {
            // Set PHP_IDE_CONFIG for PhpStorm path mapping
            // Default server name, can be overridden with PHP_IDE_CONFIG env var
            $php_ide_config = getenv('PHP_IDE_CONFIG') ?: 'serverName=PHAR_Sandbox';
            $env['PHP_IDE_CONFIG'] = $php_ide_config;
            // Set XDEBUG_SESSION to force Xdebug to start
            $env['XDEBUG_SESSION'] = 'PHPSTORM';

            // Only use environment variable for small inputs (to avoid "Argument list too long" error)
            // For large inputs, use STDIN (Xdebug works fine with STDIN - it operates at PHP execution level, not I/O level)
            if ($input_size <= $max_env_size) {
                $env['SANDBOX_INPUT_DATA'] = $input_json;
            }
            // For large inputs, we'll use STDIN (handled below) - Xdebug will work normally
        } elseif ($input_size <= $max_env_size) {
            // Production mode: use env var for small inputs (more efficient)
            $env['SANDBOX_INPUT_DATA'] = $input_json;
        }

        // Provide input as JSON (STDIN if not in env var)
        // Note: STDIN works perfectly with Xdebug - Xdebug operates at PHP execution level, not I/O level
        $use_stdin = !isset($env['SANDBOX_INPUT_DATA']);

        $proc = proc_open(
            $cmd,
            [
                0 => ($debug && $input_file) ? ['file', '/dev/null', 'r'] : ($use_stdin ? ['pipe','r'] : ['file', '/dev/null', 'r']),
                1 => ['pipe','w'],
                2 => ['pipe','w']
            ],
            $pipes,
            $tmp,
            $env  // Pass environment variables
        );

        if ($use_stdin) {
            // Send input via STDIN (for large inputs or when env var not set)
            fwrite($pipes[0], $input_json);
            fclose($pipes[0]);
        }

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        proc_close($proc);

        unlink($contract);
        rmdir($tmp);

        if ($errors && trim($errors) !== '') {
            // Filter out harmless warnings about functions that can't be disabled
            $filtered_errors = preg_replace('/PHP Warning:.*Cannot disable function.*\n?/i', '', $errors);
            if (trim($filtered_errors) !== '') {
                // In debug mode, include both stdout and stderr for debugging
                $result = ["error" => "sandbox_error", "details" => $filtered_errors];
                if ($debug && $output) {
                    $result["stdout"] = $output;
                }
                return $result;
            }
        }
        $decoded = json_decode($output, true);

        // In debug mode, include raw output if JSON decode fails
        if ($debug && $decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return [
                "error" => "json_decode_error",
                "message" => "Failed to decode JSON output",
                "json_error" => json_last_error_msg(),
                "raw_output" => $output,
                "stderr" => $errors ?: null
            ];
        }

        return $decoded;
    }

    /**
     * Execute PHAR file directly in sandbox
     * Security: Sandbox bootstrap runs first (via auto_prepend_file)
     * All security restrictions still apply
     *
     * @param string $phar_file Path to PHAR file
     * @param array $input Input data for contract
     * @param bool $debug Enable debug mode (shows errors, includes stderr/stdout in response)
     * @return array Contract execution result
     * @throws InvalidArgumentException if PHAR file doesn't exist or is invalid
     */
    public static function runPharDirectly($phar_file, $input = [], $debug = false) {
        // Validate PHAR file exists
        if (!file_exists($phar_file)) {
            throw new InvalidArgumentException("PHAR file does not exist: $phar_file");
        }

        // Ensure it's a PHAR file
        if (pathinfo($phar_file, PATHINFO_EXTENSION) !== 'phar') {
            throw new InvalidArgumentException("File is not a PHAR archive: $phar_file");
        }

        // Get absolute paths for config files
        $sandboxDir = __DIR__;
        $iniFile = $debug ? "php-sandbox-debug.ini" : "php-sandbox.ini";
        $iniFile = escapeshellarg("$sandboxDir/$iniFile");
        $bootstrapFile = escapeshellarg("$sandboxDir/sandbox_bootstrap.php");
        $pharFileEscaped = escapeshellarg($phar_file);

        // Execute PHAR directly - sandbox bootstrap runs first via auto_prepend_file
        // This ensures all security restrictions apply
        // Build command with debug options if enabled
        $cmd = "php -c $iniFile -d auto_prepend_file=$bootstrapFile";
        $cmd .= " -d max_execution_time=" . SC_MAX_EXEC_TIME;
        $cmd .= " -d memory_limit=" . SC_MEMORY_LIMIT;
        if ($debug) {
            $cmd .= " -d error_reporting=" . E_ALL;
            // Enable Xdebug for CLI debugging
            if (extension_loaded('xdebug')) {
                $cmd .= " -d xdebug.mode=debug";
                $cmd .= " -d xdebug.start_with_request=yes";
                $cmd .= " -d xdebug.idekey=PHPSTORM";
                // Xdebug will connect to IDE on default port 9003 (Xdebug 3.x) or 9000 (Xdebug 2.x)
                // Can be overridden with XDEBUG_CONFIG environment variable
            }
        }
        $cmd .= " $pharFileEscaped";

        // Prepare environment variables (for PhpStorm path mapping)
        $env = $_ENV;
        $input_file = null;

        // Check input size - if too large, use STDIN instead of env vars to avoid "Argument list too long" error
        // System limit is typically around 128KB-256KB for environment variables
        $input_json = json_encode($input);
        $input_size = strlen($input_json);
        $max_env_size = 32 * 1024; // 32KB limit for env vars (conservative)

        if ($debug && extension_loaded('xdebug')) {
            // Set PHP_IDE_CONFIG for PhpStorm path mapping
            // Default server name, can be overridden with PHP_IDE_CONFIG env var
            $php_ide_config = getenv('PHP_IDE_CONFIG') ?: 'serverName=PHAR_Sandbox';
            $env['PHP_IDE_CONFIG'] = $php_ide_config;
            // Set XDEBUG_SESSION to force Xdebug to start
            $env['XDEBUG_SESSION'] = 'PHPSTORM';

            // Only use environment variable for small inputs (to avoid "Argument list too long" error)
            // For large inputs, use STDIN (Xdebug works fine with STDIN - it operates at PHP execution level, not I/O level)
            if ($input_size <= $max_env_size) {
                $env['SANDBOX_INPUT_DATA'] = $input_json;
            }
            // For large inputs, we'll use STDIN (handled below) - Xdebug will work normally
        } elseif ($input_size <= $max_env_size) {
            // Production mode: use env var for small inputs (more efficient)
            $env['SANDBOX_INPUT_DATA'] = $input_json;
        }

        // Provide input as JSON (STDIN if not in env var)
        // Note: STDIN works perfectly with Xdebug - Xdebug operates at PHP execution level, not I/O level
        $use_stdin = !isset($env['SANDBOX_INPUT_DATA']);

        $proc = proc_open(
            $cmd,
            [
                0 => $use_stdin ? ['pipe','r'] : ['file', '/dev/null', 'r'],
                1 => ['pipe','w'],
                2 => ['pipe','w']
            ],
            $pipes,
            null,  // Use current working directory
            $env   // Pass environment variables
        );

        if ($use_stdin) {
            // Send input via STDIN (for large inputs or when env var not set)
            fwrite($pipes[0], $input_json);
            fclose($pipes[0]);
        }

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        proc_close($proc);

        // Cleanup input file if used
        if ($input_file && file_exists($input_file)) {
            unlink($input_file);
        }

        if ($errors && trim($errors) !== '') {
            // Filter out harmless warnings about functions that can't be disabled
            $filtered_errors = preg_replace('/PHP Warning:.*Cannot disable function.*\n?/i', '', $errors);
            if (trim($filtered_errors) !== '') {
                // In debug mode, include both stdout and stderr for debugging
                $result = ["error" => "sandbox_error", "details" => $filtered_errors];
                if ($debug && $output) {
                    $result["stdout"] = $output;
                }
                return $result;
            }
        }

        $decoded = json_decode($output, true);

        // In debug mode, include raw output if JSON decode fails
        if ($debug && $decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return [
                "error" => "json_decode_error",
                "message" => "Failed to decode JSON output",
                "json_error" => json_last_error_msg(),
                "raw_output" => $output,
                "stderr" => $errors ?: null
            ];
        }

        return $decoded;
    }

    static function runDapp($php_file,$input,$allowed_files,$debug=false)
    {

        // Validate PHP file exists
        if (!file_exists($php_file)) {
            throw new InvalidArgumentException("PHP file does not exist: $php_file");
        }

        // Ensure it's a PHP file
        if (pathinfo($php_file, PATHINFO_EXTENSION) !== 'php') {
            throw new InvalidArgumentException("File is not a PHAR archive: $php_file");
        }
        $iniFile = $debug ? "php-sandbox-debug.ini" : "php-sandbox.ini";
        $sandboxDir = __DIR__;
        $bootstrapFile = escapeshellarg("$sandboxDir/sandbox_dapp_bootstrap.php");
        $cmd = "php -c $iniFile -d auto_prepend_file=$bootstrapFile";
        $dapps_dir = Dapps::getDappsDir();

        $allowed_files[]=$bootstrapFile;

        $allowed_files_list = implode(":", $allowed_files);
        $cmd .= " -d open_basedir=" . $dapps_dir.":".$allowed_files_list;
//        $debug=false;
        if ($debug) {
            $cmd .= " -d error_reporting=" . E_ALL;
            // Enable Xdebug for CLI debugging
            if (extension_loaded('xdebug')) {
                $cmd .= " -d xdebug.mode=debug";
                $cmd .= " -d xdebug.start_with_request=yes";
                $cmd .= " -d xdebug.idekey=PHPSTORM";
                // Xdebug will connect to IDE on default port 9003 (Xdebug 3.x) or 9000 (Xdebug 2.x)
                // Can be overridden with XDEBUG_CONFIG environment variable
            }
        }
        $phpFileEscaped = escapeshellarg($php_file);
        $cmd .= " $phpFileEscaped";

        $use_stdin = !isset($env['SANDBOX_INPUT_DATA']);

        // Prepare environment variables (for PhpStorm path mapping)
        $env = $_ENV;
        $input_json = json_encode($input);
        $input_size = strlen($input_json);
        $max_env_size = 32 * 1024; // 32KB limit for env vars (conservative)

        if ($debug && extension_loaded('xdebug')) {
            // Set PHP_IDE_CONFIG for PhpStorm path mapping
            // Default server name, can be overridden with PHP_IDE_CONFIG env var
            $php_ide_config = getenv('PHP_IDE_CONFIG') ?: 'serverName=PHAR_Sandbox';
            $env['PHP_IDE_CONFIG'] = $php_ide_config;
            // Set XDEBUG_SESSION to force Xdebug to start
            $env['XDEBUG_SESSION'] = 'PHPSTORM';

            // Only use environment variable for small inputs (to avoid "Argument list too long" error)
            // For large inputs, use STDIN (Xdebug works fine with STDIN - it operates at PHP execution level, not I/O level)
            if ($input_size <= $max_env_size) {
                $env['SANDBOX_INPUT_DATA'] = $input_json;
            }
            // For large inputs, we'll use STDIN (handled below) - Xdebug will work normally
        } elseif ($input_size <= $max_env_size) {
            // Production mode: use env var for small inputs (more efficient)
            $env['SANDBOX_INPUT_DATA'] = $input_json;
        }


        $proc = proc_open(
            $cmd,
            [
                0 => $use_stdin ? ['pipe','r'] : ['file', '/dev/null', 'r'],
                1 => ['pipe','w'],
                2 => ['pipe','w']
            ],
            $pipes,
            null,  // Use current working directory
            $env   // Pass environment variables
        );

        if ($use_stdin) {
            // Send input via STDIN (for large inputs or when env var not set)
            fwrite($pipes[0], $input_json);
            fclose($pipes[0]);
        }

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        proc_close($proc);

        return $output;




    }

}

// Backward compatibility: provide function wrappers for existing code
if (!function_exists('run_smart_contract')) {
    /**
     * @deprecated Use Sandbox::runSmartContract() instead
     */
    function run_smart_contract($code_or_phar, $input = [], $debug = false) {
        return Sandbox::runSmartContract($code_or_phar, $input, $debug);
    }
}

if (!function_exists('run_phar_directly')) {
    /**
     * @deprecated Use Sandbox::runPharDirectly() instead
     */
    function run_phar_directly($phar_file, $input = [], $debug = false) {
        return Sandbox::runPharDirectly($phar_file, $input, $debug);
    }
}

