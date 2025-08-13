<?php

if(php_sapi_name() != 'cli') {
    die("This script must be run from the command line.\n");
}

define("REMOTE", true);

ini_set('display_errors', 1);
error_reporting(E_ALL ^ E_DEPRECATED);
require_once dirname(__DIR__)."/apps.inc.php";
require_once __DIR__ . "/vendor/autoload.php";

$_config['log_file']='tmp/p2p.log';

$command = @$argv[1];

$command = "all";
//$command = "resendTx";


//$command="processDeposit";
//$argv[2]="PHP";
//$argv[3]="GSjU1gfsVzkHPJ3R4bs3yJHzpQo57EMASyuYMaoaDiN8";

if($command == "checkExpiredCreatedOffers") {
    CliService::checkExpiredCreatedOffers();
} else if ($command == "checkExpiredOpenOffers") {
    CliService::checkExpiredOpenOffers();
} else if ($command == "checkPhpDeposits") {
//    CliService::checkPhpDeposits();
} else if ($command == "checkPhpTransferring") {
//    CliService::checkPhpTransferring();
} else if ($command == "checkCoinDeposits") {
//    CliService::checkCoinDeposits();



} else if ($command == "checkCanceledOffers") {
//    CliService::checkCanceledOffers();
} else if ($command == "checkExpiredAcceptedOffers") {
    CliService::checkExpiredAcceptedOffers();
} else if ($command == "checkCoinTransferring") {
//    CliService::checkCoinTransferring();
} else if ($command == "processTreansferred") {
    CliService::processTreansferred();
} else if ($command == "processPayingOffers") {
    CliService::processPayingOffers();
} else if ($command == "generateAddress") {
    CliService::generateAddress();
} else if ($command == "all") {
    CliService::checkIncompleteTransferLogs();
    CliService::processPayingOffers();
    CliService::processTreansferred();
    CliService::checkAssetsTransferring();
    CliService::checkExpiredAcceptedOffers();
    CliService::checkExpiredCreatedOffers();
    CliService::checkAssetsDeposits();
} else if ($command == "manualSetOfferDepositing") {
    $offer_id = @$argv[2];
    $deposit_tx_id = @$argv[3];
    if(empty($offer_id) || empty($deposit_tx_id)) {
        _log("Empty offer_id and deposit_tx_id");
        exit;
    }
//    CliService::manualSetOfferDepositing($offer_id, $deposit_tx_id);
} else if ($command == "resendTx") {
    CliService::resendTx();
} else if ($command == "processDeposit") {
    $symbol = @$argv[2];
    $deposit_tx_id = @$argv[3];
    $asset = OfferService::getAssetBySymbol($symbol);
    if(!$asset) {
        die("Invalid asset symbol: ".$symbol);
    }
    $service = OfferService::getService($asset['service']);
    CliService::processDepositTxId($service, $asset, $deposit_tx_id);
}

