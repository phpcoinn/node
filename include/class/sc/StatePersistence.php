<?php
/**
 * StatePersistence - Handles state file I/O outside the sandbox
 * 
 * This class handles loading and saving state to files/database.
 * It runs outside the sandbox, so it has full file system access.
 */

class StatePersistence {
    
    /**
     * Load state for a contract address
     * 
     * @param string $address PHPcoin address
     * @return array State data (empty array if no state exists)
     */
    public static function loadState($address) {
        return self::loadStateFromFile($address);
    }

    /**
     * Save state for a contract address
     * 
     * @param string $address PHPcoin address
     * @param array $state State data
     * @return bool True on success
     */
    public static function saveState($address, $state) {
        return self::saveStateToFile($address, $state);
    }
    
    /**
     * Load state from JSON file (virtual mode)
     * 
     * @param string $address PHPcoin address
     * @return array State data (empty array if file doesn't exist)
     */
    private static function loadStateFromFile($address) {
        $state_file = self::getStateFilePath($address);
        
        if (!file_exists($state_file)) {
            return []; // No state file, return empty state
        }
        
        $content = file_get_contents($state_file);
        if ($content === false) {
            return []; // Failed to read, return empty state
        }
        
        $state = json_decode($content, true);
        if ($state === null && json_last_error() !== JSON_ERROR_NONE) {
            // Invalid JSON, return empty state
            return [];
        }
        
        return $state ?: [];
    }
    
    /**
     * Save state to JSON file (virtual mode)
     * 
     * @param string $address PHPcoin address
     * @param array $state State data
     * @return bool True on success
     */
    private static function saveStateToFile($address, $state) {
        $state_file = self::getStateFilePath($address);
        $state_dir = dirname($state_file);
        
        if (!is_dir($state_dir)) {
            if (!mkdir($state_dir, 0755, true)) {
                return false;
            }
        }
        
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return file_put_contents($state_file, $json) !== false;
    }
    
    /**
     * Get state file path for an address (virtual mode)
     * 
     * @param string $address Contract address
     * @return string File path
     */
    private static function getStateFilePath($address) {
        $state_dir = SmartContractEngine::getRunFolder();
        return $state_dir . '/' . $address . '.json';
    }
}
