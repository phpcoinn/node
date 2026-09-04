<?php
@define("ROOT", dirname(__DIR__));
require_once ROOT . '/include/class/sc/Compiler.php';

$address = $argv[1] ?? 'PdEvtfZwNsbddKLCZQcjTgjpdcznS1w3pG';
$source = ROOT . '/include/templates/dex/simple_offer_dex.php';
$outDir = ROOT . '/tmp';
if (!is_dir($outDir)) {
    @mkdir($outDir, 0777, true);
}
$phar = $outDir . '/simple_offer_dex.phar';

try {
    $pharFile = Compiler::compile($source, $address, $phar);
    $code = base64_encode(file_get_contents($pharFile));
    echo "PHAR: $pharFile\n";
    echo "Deploy address used for compile: $address\n";
    echo "Base64 code length: " . strlen($code) . "\n";
    echo "Next: create a SC_CREATE transaction to your chosen DEX address, then set DEX_CONTRACT_ADDRESS or \$_config['dex_contract'] to that address.\n";
    echo "Do not reuse this compile address unless it is the real deploy address.\n";
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}
