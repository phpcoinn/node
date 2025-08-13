<?php

class CliService
{
    static function checkExpiredCreatedOffers() {
        $expiredOffers = OfferService::getExpiredCreatedOffers();
        _log("Checking created expired offers: found " . count($expiredOffers));
        foreach ($expiredOffers as $offer) {
            $created_at = strtotime($offer['created_at']);
            $now = time();
            $diff = $now - $created_at;
            if ($diff > OfferService::OFFER_WAIT_TIME) {
                _log("Offer #{$offer['id']} expired diff = $diff");
                OfferService::setOfferExpired($offer['id']);
            }
        }
    }

    static function checkExpiredOpenOffers() {
        $expiredOpenOffers = OfferService::getExpiredOpenOffers();
        _log("Checking expired open offers: found " . count($expiredOpenOffers));
        foreach ($expiredOpenOffers as $offer) {
            $expires_at = strtotime($offer['expires_at']);
            $now = time();
            $diff = $now - $expires_at;
            if ($diff > 0) {
                _log("Offer {$offer['id']} expired");
                OfferService::cancelOpenOffer($offer, OfferService::STATUS_EXPIRED);
            }
        }
    }

    public static function checkAssetDeposit($asset) {
        $service = OfferService::getService($asset['service']);
        $symbol = $asset['symbol'];
        $last_block_number = OfferService::getLastTransferHeight('deposit', $asset['id']);
        if(empty($last_block_number)) {
            $last_block_number = $service->getStartBlockNumber();
        }
        $last_block_number = $last_block_number - 10;
        $depositTxIds = $service->findTransfers($last_block_number);
        _log("Checking $symbol deposits last_block_number=$last_block_number deposits: found " . count($depositTxIds));
        $block_height = $service->getLastHeight();
        foreach ($depositTxIds as $depositTxId) {
            self::processDepositTxId($service, $asset, $depositTxId, $block_height);
        }
    }

    static function processDepositTxId($service, $asset, $depositTxId, $block_height=null) {
        if(!$block_height) {
            $block_height = $service->getLastHeight();
        }
        $offer = OfferService::getOfferByReceiveTx($depositTxId);
        if($offer) {
            _log("Found offer {$offer['id']} for deposit #{$depositTxId}");
            if($offer['status'] != OfferService::STATUS_DEPOSITING) {
                _log("offer #{$offer['id']} status is {$offer['status']}");
                return;
            }
            $depositTx = $service->findTransaction($depositTxId);
            $last_height = $depositTx['height'];
            if(!$depositTx) {
                _log("Transaction #{$depositTxId} not found");
                return;
            }
            $confirmations= $block_height - $last_height;
            $requiredConfirmations=$service->getConfirmations('wait');
            if($confirmations <= $requiredConfirmations) {
                _log("Deposit #{$depositTxId} waiting confirmations $confirmations/".$requiredConfirmations);
                return;
            }
            OfferService::setOfferOpen($offer['id']);
            _log("Deposit #{$depositTxId} opened");
        } else {
            _log("Not found offer by deposit tx id {$depositTxId}");
            if($depositTxId == "0x07fbf96ce3b9e25ccfa09e2511c5e43be9c7f882cad09a51f09a12bdcd296aca") {
                $a=1;
            }
            $depositTx = $service->findTransaction($depositTxId);
            if(!$depositTx) {
                _log("Transaction #{$depositTxId} not found");
                return;
            }
            $amount = $depositTx['amount'];
            $offers = OfferService::getOfferByDepositAmount(floatval($amount), $asset['id']);
            _log("Found offers by amount {$amount}: found " . count($offers));
            if(count($offers)>1) {
                _log("Multiple offers found for amount $amount");
                return;
            }
            if(count($offers)==0) {
                _log("No offer found for amount $amount");
                return;
            }
            $offer = $offers[0];
            _log("Set offer #{$offer['id']} deposition for #{$depositTxId}");
            OfferService::setOfferDepositing($offer['id'], $depositTxId);
        }
    }

    public static function checkExpiredAcceptedOffers()
    {
        _log("Checking expired accepted offers");
        $expiredOffers = OfferService::getExpiredAcceoptedOffers();
        foreach ($expiredOffers as $offer) {
            $accepted_at = strtotime($offer['accepted_at']);
            $now = time();
            $diff = $now - $accepted_at;
            if ($diff > OfferService::OFFER_WAIT_TIME) {
                _log("Accepted Offer {$offer['id']} expired");
                OfferService::cancelAcceptedOffer($offer);
            }
        }
    }

    public static function checkAssetsTransferring()
    {
        $assets = OfferService::getAssets();
        foreach($assets as $asset) {
            self::checkAssetTransferring($asset);
        }
    }

    public static function checkAssetTransferring($asset)
    {
        $service = OfferService::getService($asset['service']);
        $symbol = $asset['symbol'];
        $check_block_number = OfferService::getLastTransferHeight('transfer', $asset['id']);
        if(empty($check_block_number)) {
            $check_block_number = $service->getStartBlockNumber();
        }
        $txIds = $service->findTransfers($check_block_number);
        _log("Checking $symbol transfer transactions check_block_number=$check_block_number txs=".count($txIds));
        $block_height = $service->getLastHeight();
        foreach ($txIds as $txId) {
            $offer = OfferService::getOffersByReceiveTxId($txId);
            if($offer) {
                _log("Found offer #{$offer['id']} for transfer transaction $txId status: ".$offer['status']);
                if(!($offer['status']==OfferService::STATUS_TRANSFERRING && $offer['accept_tx_id'] == $txId)) {
                    _log("Not valid offer");
                    continue;
                }
                $tx = $service->findTransaction($txId);
                if(!$tx) {
                    _log("Transaction #{$txId} not found");
                    continue;
                }
                $confirmations = $block_height - $tx['height'];
                $requiredConfirmations = $service->getConfirmations('transferring');
                if($confirmations <= $requiredConfirmations) {
                    _log("Waiting confirmations $confirmations / ".$requiredConfirmations." for offer transfer {$offer['id']}");
                    continue;
                }
                _log("Set offer #{$offer['id']} for transfer transaction $txId completed");
                OfferService::setAcceptedOfferTransferred($offer['id']);
            } else {
                $tx = $service->findTransaction($txId);
                if(!$tx) {
                    _log("Transaction #{$txId} not found");
                    continue;
                }
                $block_number = $tx['height'];
                $amount = $tx['amount'];
                $res = $service->checkTransaction($tx);
                if(!$res) {
                    _log("Transaction check failed");
                    continue;
                }
                _log("No offer found for transfer transaction $txId - looking for amount $amount");
                $offers = OfferService::getAcceptedOfferByAmount($amount, $asset['id']);
                if(count($offers)>1) {
                    _log("Found multiple offers for $amount");
                    continue;
                }
                if(count($offers)==0) {
                    _log("No offer found for amount $amount");
                    continue;
                }
                $offer = $offers[0];
                _log("Found offer #{$offer['id']} for transfer transaction $txId");
                OfferService::setAcceptedOfferTransferring($offer['id'], $txId);
            }
        }
    }

    public static function processTreansferred()
    {
        $offers = OfferService::getTransferredOffers();
        _log("Processing treansferred offers count=".count($offers));
        foreach ($offers as $offer) {
            _log("Processing treansferred offer #{$offer['id']}");
            $base_transfer_tx_id = null;
            $quote_transfer_tx_id = null;
            $market = OfferService::getMarket($offer['market_id']);
            if(empty($offer['base_transfer_tx_id'])) {
                $baseService = OfferService::getService($market['base_service']);
                $amount = $offer['base_amount'];
                $toAddress = $offer['base_receive_address'];
                $base_transfer_tx_id = $baseService->createPayment($amount, $toAddress, $offer);
                if(!$base_transfer_tx_id) {
                    _log("Failed to create PHPCoin Payment");
                } else {
                    $res = OfferService::setBaseTransferTxId($base_transfer_tx_id, $offer['id'], $market['base_asset_id']);
                    if(!$res) {
                        _log("Failed to set base_transfer_tx_id");
                    }
                }
            }
            if(empty($offer['quote_transfer_tx_id'])) {
                $quoteService = OfferService::getService($market['quote_service']);
                $amount = $offer['base_amount'] * $offer['base_price'];
                $toAddress = $offer['quote_receive_address'];
                $quote_transfer_tx_id = $quoteService->createPayment($amount, $toAddress, $offer);
                if(!$quote_transfer_tx_id) {
                    _log("Failed to create Coin Payment");
                } else {
                    $res = OfferService::setQuoteTransferTxId($quote_transfer_tx_id, $offer['id'], $market['quote_asset_id']);
                    if(!$res) {
                        _log("Failed to set quote_transfer_tx_id");
                    }
                }
            }
            $res = OfferService::setOfferPaying($offer['id']);
            if(!$res) {
                _log("Failed to set offer paying");
            }
        }
    }

    public static function processPayingOffers()
    {
        $offers = OfferService::getPayingOffers();
        _log("Processing paying offers count=".count($offers));
        foreach ($offers as $offer) {
            $market = OfferService::getMarket($offer['market_id']);
            if(empty($offer['base_transfer_tx_id'])) {
                _log("No base_transfer_tx_id");
                continue;
            }
            $baseService = OfferService::getService($market['base_service']);
            $tx = $baseService->findTransaction($offer['base_transfer_tx_id']);
            if(!$tx) {
                _log("Not found php payment tx id");
                continue;
            }
            $block_height = $baseService->getLastHeight();
            $confirmations= $block_height - $tx['height'];
            $requiredConfirmations=$baseService->getConfirmations('paying');
            if($confirmations <= $requiredConfirmations) {
                _log("Waiting confirmations $confirmations / ".$requiredConfirmations);
                continue;
            }
            if(empty($offer['quote_transfer_tx_id'])) {
                _log("No quote_transfer_tx_id");
                continue;
            }
            $quoteService = OfferService::getService($market['quote_service']);
            $tx = $quoteService->findTransaction($offer['quote_transfer_tx_id']);
            if(!$tx) {
                _log('Not found coin payment tx id');
                continue;
            }
            $block_height = $quoteService->getLastHeight();
            $confirmations= $block_height - $tx['height'];
            $requiredConfirmations=$baseService->getConfirmations('paying');
            if($confirmations <= $requiredConfirmations) {
                _log("Waiting confirmations $confirmations / ".$requiredConfirmations);
                continue;
            }
            OfferService::setOfferClosed($offer);
        }
    }

    public static function generateAddress()
    {
        $wordlist = new \BitWasp\Bitcoin\Mnemonic\Bip39\Wordlist\EnglishWordList();
        $wallet = new \Web3p\EthereumWallet\Wallet($wordlist);
        $wallet = $wallet->generate(12);
        $privateKey = $wallet->getPrivateKey();
        $address = $wallet->getAddress();
        echo "Private Key: $privateKey\n";
        echo "Wallet Address: $address\n";
    }

    public static function checkAssetsDeposits()
    {
        $assets = OfferService::getAssets();
        foreach($assets as $asset) {
            self::checkAssetDeposit($asset);
        }
    }

    public static function resendTx()
    {
        $service="UsdtPolygonService";
        $txId = "0x5423f71ee295afb10f7095608c78d4dff06cbb939df919920114a16788555602";
        $service=OfferService::getService($service);
        $service->resendTx($txId);
    }

    public static function checkIncompleteTransferLogs()
    {
        $logs = OfferService::getIncompleteTransferLogs();
        foreach($logs as $log) {
            $asset_id = $log['asset_id'];
            $asset = OfferService::getAssetById($asset_id);
            $service = OfferService::getService($asset['service']);
            $tx = $service->findTransaction($log['tx_id']);
            if(!$tx) {
                _log("Not found tx id");
                continue;
            }
            OfferService::updateTransferLogWithTransaction($log['id'], $tx);
        }
    }
}