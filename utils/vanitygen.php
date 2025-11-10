<?php

const VANITYGEN_NAME = 'PHPCoin Vanity Address Generator';
const VANITYGEN_VERSION = '0.0.1';
const VANITYGEN_USAGE = 'Usage: php vanitygen.php prefix [-c] [-d]' . PHP_EOL .
    '  prefix     Prefix for the PHPCoin address (e.g., "Php")' . PHP_EOL .
    '  -c         Case sensitive matching' . PHP_EOL .
    '  -d         Enable debug output' . PHP_EOL;
const VANITYGEN_URL = 'https://github.com/phpcoinn/node/blob/main/utils/vanitygen.php';    
const DEFAULT_CHAIN_ID = '00';

$debug = false;

print VANITYGEN_NAME . ' v' . VANITYGEN_VERSION . PHP_EOL;

setupOrExit();

generateVanityAddress(getOptionsOrExit($argv));

print PHP_EOL . 'Exiting ' . VANITYGEN_NAME . PHP_EOL;

/**
 * Generates a vanity PHPCoin address based on the provided options.
 *
 * @param array $options An associative array with keys:
 *                       - 'prefix': The desired prefix for the address.
 *                       - 'case_sensitive': Boolean indicating if the match should be case sensitive.
 * @return array The generated account details containing 'address', 'public_key', and 'private_key'.
 */
function generateVanityAddress(array $options): array
{
    $prefix = $options['prefix'];
    // All PHPCoin addresses start with uppercase 'P'
    if (! str_starts_with($prefix, 'p') && ! str_starts_with($prefix, 'P')) {
        $prefix = 'P' . $prefix;
    }
    // Force starting with uppercase 'P'
    if (str_starts_with($prefix, 'p')) {
        $prefix = 'P' . substr($prefix, 1);
    }

    $caseSensitive = $options['case_sensitive'];

    print 'Prefix: ' . $prefix . PHP_EOL;
    print 'Case Sensitive: ' . ($caseSensitive ? 'Yes' : 'No') . PHP_EOL;

    $count = 0;

    while (true) {
        $account = Account::generateAcccount();
        $address = $account['address'];
        $count++;
        _debug('Generation '. $count . ': ' . $address);

        if (! $caseSensitive) {
            $address = strtolower($address);
            $prefix = strtolower($prefix);
        }
        if (str_starts_with($address, $prefix)) {
            print 'Found vanity PHPCoin address after '. $count . ' tries!' . PHP_EOL;
            print_r($account);
            return $account;
        }
        if ($count % 500 === 0) {
            print 'Generated ' . $count . ' PHPCoin addresses...' . PHP_EOL;
        }
    }
}

/**
 * Parses command-line arguments into options and positional arguments.
 *
 * Supports:
 * - Positional arguments (e.g., "prefix")
 * - Long options (e.g., --foo)
 * - Long options with value (e.g., --value=foo or --value foo)
 * - Short options (e.g., -f)
 * - Short options with value (e.g., -v=foo or -v foo)
 */
function getOptionsOrExit(array $argv): array
{
    global $debug;

    $options = [];
    $arguments = [];

    // Start at 1 to skip the script name ($argv[0])
    for ($i = 1; $i < count($argv); $i++) {
        $item = $argv[$i];
        if (strpos($item, '--') === 0) { // 1. Long Option: --key or --key=value or --key value
            $key = substr($item, 2);
            $value = true; // Default for flags like --verbose
            if (strpos($key, '=') !== false) { // Check for --key=value format
                list($key, $value) = explode('=', $key, 2);
            } else if (isset($argv[$i + 1]) && strpos($argv[$i + 1], '-') !== 0) {
                // Check for --key value format
                // Is there a next item? AND Is the next item NOT another option?
                $value = $argv[$i + 1];
                $i++; // Skip the next item, it's been consumed as a value
            }
            $options[$key] = $value;
        } else if (strpos($item, '-') === 0) { // 2. Short Option: -k or -k=value or -k value
            $key = substr($item, 1);
            $value = true; // Default for flags like -v
            // Check for -k=value format
            if (strpos($key, '=') !== false) {
                list($key, $value) = explode('=', $key, 2);
            }
            // Check for -k value format
            // Is there a next item? AND Is the next item NOT another option?
            else if (isset($argv[$i + 1]) && strpos($argv[$i + 1], '-') !== 0) {
                $value = $argv[$i + 1];
                $i++; // Skip the next item
            }
            $options[$key] = $value;
        } else { // 3. Positional Argument
            $arguments[] = $item;
        }
    }

    if (empty($options) && empty($arguments)) {
        exit(VANITYGEN_USAGE . PHP_EOL);
    }

    if (empty($arguments[0])) {
        exit('ERROR: No prefix provided.' . PHP_EOL . VANITYGEN_USAGE . PHP_EOL);
    }

    if (isset($options['d'])) {
        $debug = true;
    }

    return [
        'prefix' => $arguments[0],
        'case_sensitive' => isset($options['c']) ? true : false,
    ];
}

/**
 * Sets up the environment or exits if conditions are not met.
 *
 * Ensures the script is run from the command line and that the autoload file exists.
 */
function setupOrExit(): void
{
    if (php_sapi_name() !== 'cli') {
        exit('ERROR: This script must be run from the command line' . PHP_EOL);
    };
    $autoload = Phar::running() 
        ? 'vendor/autoload.php' 
        : dirname(__DIR__) . '/vendor/autoload.php';

    if (! file_exists($autoload)) {
        exit('ERROR: Autoload file not found. Please run "composer install".' . PHP_EOL);
    }    
    require_once $autoload;
}

/**
 * Outputs debug messages if debugging is enabled.
 *
 * @param string $message The debug message to output.
 */
function _debug(string $message): void
{
    global $debug;
    if ($debug) {
        print '[DEBUG] ' . $message . PHP_EOL;
    }
}
