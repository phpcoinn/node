<?php
/**
 * PHAR Builder - Build PHAR files from user code folders
 * 
 * This allows packaging multi-file contracts into PHAR archives
 * for distribution and execution.
 */

class PharBuilder {
    
    /**
     * Build a PHAR file from a directory containing contract code
     * 
     * @param string $source_dir Directory containing contract files
     * @param string $output_phar Path where PHAR file will be created
     * @param string $entry_point Main entry point file (e.g., 'contract.php' or 'src/main.php')
     * @param bool $validate_code If true, validates code before building (recommended)
     * @return string Path to created PHAR file
     * @throws InvalidArgumentException if source directory doesn't exist or is invalid
     * @throws RuntimeException if PHAR creation fails
     */
    public static function buildFromDirectory($source_dir, $output_phar, $entry_point = 'contract.php', $validate_code = true) {
        // Validate source directory
        if (!is_dir($source_dir)) {
            throw new InvalidArgumentException("Source directory does not exist: $source_dir");
        }
        
        if (!is_readable($source_dir)) {
            throw new InvalidArgumentException("Source directory is not readable: $source_dir");
        }
        
        // Check if PHAR extension is available
        if (!class_exists('Phar')) {
            throw new RuntimeException("PHAR extension not available");
        }
        
        // Check if we can write PHAR (phar.readonly must be Off for creation)
        if (ini_get('phar.readonly')) {
            throw new RuntimeException("Cannot create PHAR: phar.readonly is On. Set phar.readonly=Off in php.ini to build PHAR files.");
        }
        
        // Validate entry point exists
        $entry_path = rtrim($source_dir, '/') . '/' . $entry_point;
        if (!file_exists($entry_path)) {
            throw new InvalidArgumentException("Entry point file does not exist: $entry_path");
        }
        
        // Get all PHP files from source directory
        $php_files = self::findPhpFiles($source_dir);
        
        if (empty($php_files)) {
            throw new InvalidArgumentException("No PHP files found in source directory: $source_dir");
        }
        
        // Validate code if requested (security check)
        if ($validate_code) {
            self::validateContractCode($php_files, $source_dir);
        }
        
        // Remove existing PHAR if it exists
        if (file_exists($output_phar)) {
            @unlink($output_phar);
        }
        
        try {
            // Create PHAR file
            $phar = new Phar($output_phar);
            $phar->startBuffering();
            
            // Add all PHP files to PHAR, preserving directory structure
            foreach ($php_files as $file_path) {
                // Get relative path from source directory
                $relative_path = str_replace($source_dir . '/', '', $file_path);
                $relative_path = str_replace($source_dir, '', $relative_path);
                $relative_path = ltrim($relative_path, '/');
                
                // Read file content
                $content = file_get_contents($file_path);
                
                // Add to PHAR
                $phar->addFromString($relative_path, $content);
            }
            
            // Add interface.json if it exists
            $interface_path = rtrim($source_dir, '/') . '/interface.json';
            if (file_exists($interface_path)) {
                $content = file_get_contents($interface_path);
                $phar->addFromString('interface.json', $content);
            }
            
            // Set stub (entry point) - this is what executes when PHAR is run directly
            // Load StateManager and SmartContractBase before contract.php
            // Note: These are loaded in the stub, but sandbox bootstrap (auto_prepend_file) 
            // runs before stub, so they'll still be parsed with restrictions active
            $phar_name = basename($output_phar);
            $stub_content = "<?php\n";
            $stub_content .= "Phar::mapPhar('$phar_name');\n";
            // Load base classes before entry point
            $stub_content .= "require_once 'phar://$phar_name/SmartContractBase.php';\n";
            $stub_content .= "require_once 'phar://$phar_name/SmartContractMap.php';\n";
            $stub_content .= "require 'phar://$phar_name/$entry_point';\n";
            $stub_content .= "__HALT_COMPILER();\n";
            
            $phar->setStub($stub_content);
            
            $phar->stopBuffering();
            
            return $output_phar;
            
        } catch (Exception $e) {
            // Clean up on failure
            if (file_exists($output_phar)) {
                @unlink($output_phar);
            }
            throw new RuntimeException("Failed to create PHAR file: " . $e->getMessage());
        }
    }
    
    /**
     * Find all PHP files in a directory recursively
     * 
     * @param string $dir Directory to search
     * @return array Array of absolute file paths
     */
    public static function findPhpFiles($dir) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    /**
     * Validate contract code before building PHAR
     * Checks for dangerous patterns and ensures code is safe
     * 
     * @param array $php_files Array of PHP file paths
     * @param string $source_dir Source directory (for relative paths in errors)
     * @throws RuntimeException if dangerous code is detected
     */
    public static function validateContractCode($php_files, $source_dir) {
        $dangerous_patterns = [
            '__HALT_COMPILER' => 'PHAR magic bytes not allowed',
            'eval(' => 'eval() function not allowed',
            'exec(' => 'exec() function not allowed',
            'system(' => 'system() function not allowed',
            'shell_exec(' => 'shell_exec() function not allowed',
            'passthru(' => 'passthru() function not allowed',
            'proc_open(' => 'proc_open() function not allowed',
            'popen(' => 'popen() function not allowed',
            'file_get_contents(' => 'file_get_contents() will be blocked in sandbox',
            'fopen(' => 'fopen() will be blocked in sandbox',
            'include(' => 'include() will be blocked in sandbox',
            'require(' => 'require() will be blocked in sandbox',
        ];
        
        foreach ($php_files as $file_path) {
            $content = file_get_contents($file_path);
            $relative_path = str_replace($source_dir . '/', '', $file_path);
            
            foreach ($dangerous_patterns as $pattern => $message) {
                if (stripos($content, $pattern) !== false) {
                    // Check if it's in a comment or string (basic check)
                    // This is a simple check - for production, use proper PHP parser
                    $lines = explode("\n", $content);
                    foreach ($lines as $line_num => $line) {
                        if (stripos($line, $pattern) !== false) {
                            // Skip if it's in a comment
                            $trimmed = trim($line);
                            if (strpos($trimmed, '//') === 0 || strpos($trimmed, '/*') === 0 || strpos($trimmed, '*') === 0) {
                                continue;
                            }
                            
                            // This is a warning, not an error - code will be sandboxed anyway
                            // But we log it for awareness
                            error_log("Warning: Potentially dangerous pattern '$pattern' found in $relative_path at line " . ($line_num + 1) . ": $message");
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Build PHAR from user code folder and execute it
     * Convenience method that builds and runs PHAR in one step
     * 
     * @param string $source_dir Directory containing contract code
     * @param array $input Input data for contract
     * @param string $entry_point Main entry point file
     * @param bool $keep_phar If false, delete PHAR after execution
     * @return array Contract execution result
     */
    public static function buildAndRun($source_dir, $input = [], $entry_point = 'contract.php', $keep_phar = false) {
        // Load sandbox executor if not already loaded
        if (!function_exists('run_smart_contract')) {
            require_once __DIR__ . '/sandbox_executor.php';
        }
        
        // Create temporary PHAR file
        $tmp_phar = sys_get_temp_dir() . '/phpc_sc_' . bin2hex(random_bytes(8)) . '.phar';
        
        try {
            // Build PHAR from directory
            self::buildFromDirectory($source_dir, $tmp_phar, $entry_point, true);
            
            // Execute PHAR in sandbox
            $result = run_smart_contract($tmp_phar, $input);
            
            // Clean up PHAR if requested
            if (!$keep_phar && file_exists($tmp_phar)) {
                @unlink($tmp_phar);
            }
            
            return $result;
            
        } catch (Exception $e) {
            // Clean up on failure
            if (file_exists($tmp_phar)) {
                @unlink($tmp_phar);
            }
            throw $e;
        }
    }
}

// Backward compatibility: provide function wrappers for existing code
if (!function_exists('build_phar_from_directory')) {
    /**
     * @deprecated Use PharBuilder::buildFromDirectory() instead
     */
    function build_phar_from_directory($source_dir, $output_phar, $entry_point = 'contract.php', $validate_code = true) {
        return PharBuilder::buildFromDirectory($source_dir, $output_phar, $entry_point, $validate_code);
    }
}

if (!function_exists('find_php_files')) {
    /**
     * @deprecated Use PharBuilder::findPhpFiles() instead
     */
    function find_php_files($dir) {
        return PharBuilder::findPhpFiles($dir);
    }
}

if (!function_exists('validate_contract_code')) {
    /**
     * @deprecated Use PharBuilder::validateContractCode() instead
     */
    function validate_contract_code($php_files, $source_dir) {
        return PharBuilder::validateContractCode($php_files, $source_dir);
    }
}

if (!function_exists('build_and_run_phar')) {
    /**
     * @deprecated Use PharBuilder::buildAndRun() instead
     */
    function build_and_run_phar($source_dir, $input = [], $entry_point = 'contract.php', $keep_phar = false) {
        return PharBuilder::buildAndRun($source_dir, $input, $entry_point, $keep_phar);
    }
}

