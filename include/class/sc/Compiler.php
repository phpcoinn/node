<?php
/**
 * Compiler - Build PHAR files from contract source code
 * 
 * Usage:
 *   // Compile from single file
 *   Compiler::compile('test1.php', 'test');  // Creates test1.phar
 *   
 *   // Compile from folder
 *   Compiler::compile('contract_folder/', 'test');  // Creates contract_folder.phar
 */

require_once __DIR__ . '/PharBuilder.php';

// Note: Additional SmartContract annotation types (SmartContractVar, SmartContractMap, 
// SmartContractDeploy, SmartContractTransact, SmartContractView) are supported via
// docblock annotations. PHP 8 attribute classes for these can be added later if needed.

class Compiler {
    
    /**
     * List of system files to include in compiled PHARs
     * These files are copied from the sandbox directory into the PHAR
     * 
     * @var array Array of filenames relative to __DIR__
     */
    private static $system_files = [
        'SmartContractBase.php',
        'SmartContractMap.php',
        'SmartContractWrapper.php',
    ];
    
    /**
     * Read interface JSON from a compiled PHAR file
     * 
     * @param string $phar_path Path to PHAR file
     * @return array Interface structure with version, address, properties, deploy, and methods
     * @throws InvalidArgumentException if PHAR file doesn't exist or is invalid
     * @throws RuntimeException if interface.json is missing or invalid
     */
    public static function readInterface($phar_path) {
        // Validate PHAR exists
        if (!file_exists($phar_path)) {
            throw new InvalidArgumentException("PHAR file not found: $phar_path");
        }
        
        // Validate it's a PHAR file
        if (pathinfo($phar_path, PATHINFO_EXTENSION) !== 'phar') {
            throw new InvalidArgumentException("File is not a PHAR archive: $phar_path");
        }
        
        try {
            $phar = new Phar($phar_path);
            
            if (!isset($phar['interface.json'])) {
                throw new RuntimeException("interface.json not found in PHAR. This PHAR may have been compiled with an older version.");
            }
            
            $interface = json_decode($phar['interface.json']->getContent(), true);
            
            if ($interface === null) {
                throw new RuntimeException("Invalid JSON in interface.json: " . json_last_error_msg());
            }
            
            return $interface;
            
        } catch (PharException $e) {
            throw new InvalidArgumentException("Invalid PHAR file: " . $e->getMessage());
        } catch (Exception $e) {
            throw new RuntimeException("Could not read interface: " . $e->getMessage());
        }
    }
    
    /**
     * Compile PHAR from source (single file or folder)
     * Compiles entire source to PHAR. Only methods with supported SmartContract annotations can be executed.
     * 
     * Supported annotations: SmartContractVar, SmartContractMap, SmartContractDeploy, 
     *                        SmartContractTransact, SmartContractView
     * 
     * @param string $source Path to PHP file or folder
     * @param string $address Phpcoin address (required)
     * @param string|null $output Optional output PHAR file path
     * @return string Path to created PHAR file
     * @throws InvalidArgumentException if source is invalid or address is missing
     * @throws RuntimeException if compilation fails
     */
    public static function compile($source, $address = null, $output = null) {
        if (empty($address)) {
            throw new InvalidArgumentException("Address parameter is required");
        }
        
        // Normalize path
        $source = rtrim($source, '/');
        
        if (is_file($source)) {
            return self::compileFromFile($source, $address, $output);
        } elseif (is_dir($source)) {
            return self::compileFromFolder($source, $address, $output);
        } else {
            throw new InvalidArgumentException("Source must be a file or folder: $source");
        }
    }
    
    /**
     * Compile PHAR from a single PHP file
     * File must contain class SmartContract
     * Compiles entire file to PHAR. Only methods with supported SmartContract annotations can be executed.
     * 
     * Supported annotations: SmartContractVar, SmartContractMap, SmartContractDeploy, 
     *                        SmartContractTransact, SmartContractView
     * 
     * @param string $php_file Path to PHP file
     * @param string $address Phpcoin address
     * @param string|null $output Optional output PHAR file path
     * @return string Path to created PHAR file
     * @throws InvalidArgumentException if file doesn't exist or doesn't contain SmartContract
     * @throws RuntimeException if compilation fails
     */
    public static function compileFromFile($php_file, $address, $output = null) {
        // Validate file exists
        if (!file_exists($php_file)) {
            throw new InvalidArgumentException("File does not exist: $php_file");
        }
        
        if (!is_readable($php_file)) {
            throw new InvalidArgumentException("File is not readable: $php_file");
        }
        
        // Validate file contains SmartContract class
        $class_name = self::extractClassName($php_file);
        
        // Determine output PHAR path
        if ($output !== null) {
            $phar_path = $output;
        } else {
            $phar_name = pathinfo($php_file, PATHINFO_FILENAME) . '.phar';
            $phar_path = dirname($php_file) . '/' . $phar_name;
        }
        
        // Check if PHAR already exists and is newer
        if (file_exists($phar_path) && filemtime($phar_path) >= filemtime($php_file)) {
//            return $phar_path;
        }
        
        // Create temporary directory for building
        $tmp_dir = sys_get_temp_dir() . '/phpc_build_' . bin2hex(random_bytes(4));
        mkdir($tmp_dir, 0755, true);
        
        try {
            // Copy PHP file
            copy($php_file, $tmp_dir . '/' . basename($php_file));
            
            // Copy system files into PHAR
            self::copySystemFiles($tmp_dir);

            // Generate interface JSON with address
            $interface = self::generateInterface($php_file, $class_name, $address);
            file_put_contents($tmp_dir . '/interface.json', json_encode($interface, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Create entry point that validates and calls methods with annotations
            $entry_point = self::createEntryPoint($tmp_dir, basename($php_file), $class_name);
            
            // Build PHAR
            PharBuilder::buildFromDirectory($tmp_dir, $phar_path, $entry_point, true);
            
            // Cleanup
            self::deleteDirectory($tmp_dir);
            
            return $phar_path;
            
        } catch (Exception $e) {
            // Cleanup on error
            if (file_exists($tmp_dir)) {
                self::deleteDirectory($tmp_dir);
            }
            throw $e;
        }
    }
    
    /**
     * Compile PHAR from a folder
     * Folder must contain index.php with class SmartContract
     * 
     * @param string $folder_path Path to folder
     * @param string $address Phpcoin address
     * @param string|null $output Optional output PHAR file path
     * @return string Path to created PHAR file
     * @throws InvalidArgumentException if folder doesn't exist or doesn't contain index.php with SmartContract
     * @throws RuntimeException if compilation fails
     */
    public static function compileFromFolder($folder_path, $address, $output = null) {
        // Validate folder exists
        if (!is_dir($folder_path)) {
            throw new InvalidArgumentException("Folder does not exist: $folder_path");
        }
        
        if (!is_readable($folder_path)) {
            throw new InvalidArgumentException("Folder is not readable: $folder_path");
        }
        
        // Check for index.php
        $index_file = rtrim($folder_path, '/') . '/index.php';
        if (!file_exists($index_file)) {
            throw new InvalidArgumentException("Folder must contain index.php: $folder_path");
        }
        
        // Validate index.php contains SmartContract class
        $class_name = self::extractClassName($index_file);

        // Determine output PHAR path
        if ($output !== null) {
            $phar_path = $output;
        } else {
            $folder_name = basename(rtrim($folder_path, '/'));
            $phar_name = $folder_name . '.phar';
            $phar_path = dirname($folder_path) . '/' . $phar_name;
        }
        
        // Check if PHAR already exists and is newer than index.php
        if (file_exists($phar_path) && filemtime($phar_path) >= filemtime($index_file)) {
//            return $phar_path;
        }
        
        // Create temporary directory for building
        $tmp_dir = sys_get_temp_dir() . '/phpc_build_' . bin2hex(random_bytes(4));
        mkdir($tmp_dir, 0755, true);
        
        try {
            // Copy all files from folder
            self::copyDirectory($folder_path, $tmp_dir);
            
            // Copy system files into PHAR (skip if already in folder)
            self::copySystemFiles($tmp_dir, true);
            
            // Detect methods with supported SmartContract annotations at compile time
            // Use temp directory so all included files are available

            // Generate interface JSON with address
            // Use temp directory so all included files are available
            $interface = self::generateInterfaceFromFolder($tmp_dir, 'index.php', $class_name, $address);
            file_put_contents($tmp_dir . '/interface.json', json_encode($interface, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Create entry point that validates and calls methods with annotations
            $entry_point = self::createEntryPoint($tmp_dir, 'index.php', $class_name);
            
            // Build PHAR
            PharBuilder::buildFromDirectory($tmp_dir, $phar_path, $entry_point, true);
            
            // Cleanup
            self::deleteDirectory($tmp_dir);
            
            return $phar_path;
            
        } catch (Exception $e) {
            // Cleanup on error
            if (file_exists($tmp_dir)) {
                self::deleteDirectory($tmp_dir);
            }
            throw $e;
        }
    }
    
    /**
     * Extract class name from PHP file
     * 
     * @param string $php_file Path to PHP file
     * @return string Class name
     * @throws RuntimeException if class not found
     */
    private static function extractClassName($php_file) {
        $content = file_get_contents($php_file);

        if (preg_match('/const SC_CLASS_NAME.*/', $content, $matches)) {
            eval($matches[0]);
            return SC_CLASS_NAME;
        }
        
        // Simple regex to find class name
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }
        
        throw new RuntimeException("Could not find class name in: $php_file");
    }

    /**
     * List of all supported SmartContract annotation types
     */
    private static function getSupportedAnnotationTypes() {
        return [
            'SmartContractVar',
            'SmartContractMap',
            'SmartContractDeploy',
            'SmartContractTransact',
            'SmartContractView'
        ];
    }

    /**
     * Create entry point file that validates and calls methods with supported SmartContract annotations
     * 
     * @param string $tmp_dir Temporary directory
     * @param string $php_file PHP file name (relative to tmp_dir)
     * @param string $class_name Class name
     * @return string Entry point file name
     */
    private static function createEntryPoint($tmp_dir, $php_file, $class_name) {
        $entry_point = 'contract.php';
        
        // Get entry point PHP file
        $entry_file = __DIR__ . '/templates/contract_entry.php';
        if (!file_exists($entry_file)) {
            throw new RuntimeException("Entry point file not found: $entry_file");
        }
        
        // Read entry point file
        $entry_content = file_get_contents($entry_file);
        
        // Replace variable assignments with actual values
        $entry_content = preg_replace(
            '/\$__PHP_FILE__\s*=\s*[^;]+;/',
            "\$__PHP_FILE__ = " . var_export($php_file, true) . ";",
            $entry_content
        );
        $entry_content = preg_replace(
            '/\$__CLASS_NAME__\s*=\s*[^;]+;/',
            "\$__CLASS_NAME__ = " . var_export($class_name, true) . ";",
            $entry_content
        );

        // Write entry point file
        file_put_contents($tmp_dir . '/' . $entry_point, $entry_content);
        return $entry_point;
    }
    
    /**
     * Copy directory recursively
     * 
     * @param string $source Source directory
     * @param string $dest Destination directory
     */
    private static function copyDirectory($source, $dest) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            // Get relative path from source directory
            $relative_path = str_replace($source . DIRECTORY_SEPARATOR, '', $item->getPathname());
            $relative_path = str_replace($source, '', $relative_path);
            $relative_path = ltrim($relative_path, DIRECTORY_SEPARATOR);
            
            $dest_path = $dest . DIRECTORY_SEPARATOR . $relative_path;
            
            if ($item->isDir()) {
                if (!is_dir($dest_path)) {
                    mkdir($dest_path, 0755, true);
                }
            } else {
                // Ensure parent directory exists
                $dest_dir = dirname($dest_path);
                if (!is_dir($dest_dir)) {
                    mkdir($dest_dir, 0755, true);
                }
                copy($item, $dest_path);
            }
        }
    }
    
    /**
     * Get the annotation type from a method or property
     * 
     * @param ReflectionMethod|ReflectionProperty $reflection Reflection object
     * @return string|null Annotation type name or null if not found
     */
    private static function getAnnotationType($reflection) {
        $supported_types = self::getSupportedAnnotationTypes();
        
        // Check PHP 8 attributes
        if (PHP_VERSION_ID >= 80000) {
            foreach ($supported_types as $type) {
                $attributes = $reflection->getAttributes($type);
                if (!empty($attributes)) {
                    return $type;
                }
            }
        }
        
        // Check docblock annotation
        $docblock = $reflection->getDocComment();
        if ($docblock !== false) {
            foreach ($supported_types as $type) {
                if (preg_match('/@' . preg_quote($type, '/') . '\b/', $docblock)) {
                    return $type;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract method parameters information
     * 
     * @param ReflectionMethod $method Reflection method object
     * @return array Array of parameter information
     */
    private static function extractMethodParameters($method) {
        $params = [];
        $parameters = $method->getParameters();
        
        foreach ($parameters as $param) {
            $param_info = [
                'name' => $param->getName(),
                'type' => null,
                'value' => null,
                'required' => !$param->isOptional()
            ];
            
            // Try to get type from type hint
            if ($param->hasType()) {
                $type = $param->getType();
                if ($type instanceof ReflectionNamedType) {
                    $param_info['type'] = $type->getName();
                }
            }
            
            // Try to get default value
            if ($param->isOptional() && $param->isDefaultValueAvailable()) {
                try {
                    $default = $param->getDefaultValue();
                    $param_info['value'] = $default;
                } catch (ReflectionException $e) {
                    // Ignore if default value cannot be retrieved
                }
            }
            
            $params[] = $param_info;
        }
        
        return $params;
    }
    
    /**
     * Generate interface JSON for the contract from a folder
     * Simply extracts folder and runs entry file - PHP will handle includes naturally
     * 
     * @param string $folder_path Path to folder containing all PHP files
     * @param string $entry_file Entry file name (relative to folder_path)
     * @param string $class_name Class name
     * @param string $address Phpcoin address
     * @return array Interface structure
     */
    private static function generateInterfaceFromFolder($folder_path, $entry_file, $class_name, $address) {
        // Save current working directory
        $original_cwd = getcwd();
        
        try {
            // Change to folder directory so includes/requires work correctly
            chdir($folder_path);
            
            // Use a unique class name to avoid conflicts with previously loaded classes
            $unique_class_name = $class_name . '_' . bin2hex(random_bytes(4));
            
            // Read entry file and replace class name with unique name
            $entry_content = file_get_contents($entry_file);
            // Remove opening PHP tag if present
            $entry_content = preg_replace('/^<\?php\s*/', '', $entry_content);
            // Replace class name with unique name
            $entry_content = preg_replace('/class\s+' . preg_quote($class_name, '/') . '\b/', "class $unique_class_name", $entry_content);
            
            // Create temporary file with bootstrap and entry content
            $tmp_file = $folder_path . '/phpc_interface_' . bin2hex(random_bytes(4)) . '.php';
            $tmp_content = "<?php\n";
            
            // Include SmartContractBase if it exists
            $base_class_file = __DIR__ . '/SmartContractBase.php';
            if (file_exists($base_class_file)) {
                $tmp_content .= "require_once '" . str_replace("'", "\\'", $base_class_file) . "';\n";
            }
            
            // Include SmartContractMap if it exists
            $map_class_file = __DIR__ . '/SmartContractMap.php';
            if (file_exists($map_class_file)) {
                $tmp_content .= "require_once '" . str_replace("'", "\\'", $map_class_file) . "';\n";
            }
            
            $tmp_content .= $entry_content . "\n";
            file_put_contents($tmp_file, $tmp_content);
            
            // Include the temporary file (will load entry file which includes its dependencies)
            require_once $tmp_file;
            
            // Use the unique class name for reflection
            $class_name = $unique_class_name;
            
            if (!class_exists($class_name)) {
                throw new RuntimeException("Class $class_name not found after loading folder");
            }
            
            $reflection = new ReflectionClass($class_name);
            
            // Initialize interface structure
            $interface = [
                'version' => '3.0.0',
                'address' => $address,
                'properties' => [],
                'deploy' => null,
                'methods' => []
            ];
            
            // Extract properties with SmartContractVar or SmartContractMap
            $properties = $reflection->getProperties();
            foreach ($properties as $property) {
                $annotation_type = self::getAnnotationType($property);
                if ($annotation_type === 'SmartContractVar' || $annotation_type === 'SmartContractMap' ||
                    ($property->getType()!= null && $property->getType()->getName() == 'SmartContractMap') ) {
                    $type = ($annotation_type === 'SmartContractMap' ||
                    ($property->getType()!= null && $property->getType()->getName() == 'SmartContractMap')) ? 'map' : 'var';
                    $interface['properties'][] = [
                        'name' => $property->getName(),
                        'type' => $type
                    ];
                }
            }
            
            // Extract methods
            $methods = $reflection->getMethods();
            foreach ($methods as $method) {
                // Skip magic methods and constructor
                if ($method->isConstructor() || strpos($method->getName(), '__') === 0) {
                    continue;
                }
                
                $annotation_type = self::getAnnotationType($method);
                if ($annotation_type === null) {
                    continue;
                }
                
                $method_info = [
                    'name' => $method->getName(),
                    'params' => self::extractMethodParameters($method)
                ];
                
                // Check if it's a deploy method
                if ($annotation_type === 'SmartContractDeploy') {
                    $interface['deploy'] = [
                        'name' => $method->getName(),
                        'params' => self::extractMethodParameters($method)
                    ];
                } else if ($annotation_type == "SmartContractTransact") {
                    $method_info['annotation'] = $annotation_type;
                    $interface['methods'][] = $method_info;
                } else if ($annotation_type == "SmartContractView") {
                    $method_info['annotation'] = $annotation_type;
                    $interface['views'][] = $method_info;
                }
            }
            
            return $interface;
            
        } catch (Exception $e) {
            throw new RuntimeException("Failed to generate interface from folder: " . $e->getMessage());
        } finally {
            // Restore original working directory
            if ($original_cwd !== false) {
                chdir($original_cwd);
            }
            // Cleanup temporary file
            if (isset($tmp_file) && file_exists($tmp_file)) {
                @unlink($tmp_file);
            }
        }
    }
    
    /**
     * Generate interface JSON for the contract
     * 
     * @param string $php_file Path to PHP file
     * @param string $class_name Class name
     * @param string $address Phpcoin address
     * @return array Interface structure
     */
    private static function generateInterface($php_file, $class_name, $address) {
        // Create a temporary isolated environment to load the class
        $tmp_file = sys_get_temp_dir() . '/phpc_interface_' . bin2hex(random_bytes(4)) . '.php';
        
        try {
            // Read the source file
            $content = file_get_contents($php_file);
            
            // Remove opening PHP tag if present
            $content = preg_replace('/^<\?php\s*/', '', $content);
            
            // Create a temporary file with the source
            // Use a unique class name to avoid conflicts with previously loaded classes
            $unique_class_name = $class_name . '_' . bin2hex(random_bytes(4));
            
            // Replace class name in content with unique name
            $unique_content = preg_replace('/class\s+' . preg_quote($class_name, '/') . '\b/', "class $unique_class_name", $content);
            
            $tmp_content = "<?php\n";
            // Include SmartContractBase if it exists
            $base_class_file = __DIR__ . '/SmartContractBase.php';
            if (file_exists($base_class_file)) {
                $tmp_content .= "require_once '" . str_replace("'", "\\'", $base_class_file) . "';\n";
            }
            $tmp_content .= $unique_content . "\n";
            
            file_put_contents($tmp_file, $tmp_content);
            
            // Include the temporary file to load the class
            require_once $tmp_file;
            
            // Use the unique class name for reflection
            $class_name = $unique_class_name;
            
            if (!class_exists($class_name)) {
                throw new RuntimeException("Class $class_name not found after loading file");
            }
            
            $reflection = new ReflectionClass($class_name);
            
            // Initialize interface structure
            $interface = [
                'version' => '3.0.0',
                'address' => $address,
                'properties' => [],
                'deploy' => null,
                'methods' => [],
                'views' => [],
            ];
            
            // Extract properties with SmartContractVar or SmartContractMap
            $properties = $reflection->getProperties();
            foreach ($properties as $property) {
                $annotation_type = self::getAnnotationType($property);
                if ($annotation_type === 'SmartContractVar' || $annotation_type === 'SmartContractMap') {
                    $type = ($annotation_type === 'SmartContractMap') ? 'map' : 'var';
                    $interface['properties'][] = [
                        'name' => $property->getName(),
                        'type' => $type
                    ];
                }
            }
            
            // Extract methods
            $methods = $reflection->getMethods();
            foreach ($methods as $method) {
                // Skip magic methods and constructor
                if ($method->isConstructor() || strpos($method->getName(), '__') === 0) {
                    continue;
                }
                
                $annotation_type = self::getAnnotationType($method);
                if ($annotation_type === null) {
                    continue;
                }
                
                $method_info = [
                    'name' => $method->getName(),
                    'params' => self::extractMethodParameters($method)
                ];
                
                // Check if it's a deploy method
                if ($annotation_type === 'SmartContractDeploy') {
                    $interface['deploy'] = [
                        'name' => $method->getName(),
                        'params' => self::extractMethodParameters($method)
                    ];
                } else if ($annotation_type == "SmartContractTransact") {
                    $method_info['annotation'] = $annotation_type;
                    $interface['methods'][] = $method_info;
                } else if ($annotation_type == "SmartContractView") {
                    $method_info['annotation'] = $annotation_type;
                    $interface['views'][] = $method_info;
                }
            }
            
            return $interface;
            
        } catch (Exception $e) {
            throw new RuntimeException("Failed to generate interface: " . $e->getMessage());
        } finally {
            // Cleanup temporary file
            if (file_exists($tmp_file)) {
                @unlink($tmp_file);
            }
        }
    }
    
    /**
     * Recursively delete directory
     * 
     * @param string $dir Directory to delete
     */
    private static function deleteDirectory($dir) {
        if (!file_exists($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Copy system files into temporary build directory
     * 
     * @param string $tmp_dir Temporary build directory
     * @param bool $skip_if_exists If true, skip copying if file already exists in destination
     */
    private static function copySystemFiles($tmp_dir, $skip_if_exists = false) {
        foreach (self::$system_files as $filename) {
            $source_file = __DIR__ . '/' . $filename;
            $dest_file = $tmp_dir . '/' . $filename;
            
            if (file_exists($source_file)) {
                if ($skip_if_exists && file_exists($dest_file)) {
                    continue;
                }
                copy($source_file, $dest_file);
            }
        }
    }
    
}

