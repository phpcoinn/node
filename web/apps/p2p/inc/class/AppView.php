<?php

use Web3\Web3;

class AppView
{

    const APP_NAME = "PHPCoin Trader";
    const BASE_URL = "/apps/p2p/index.php";
    const GATEWAY_DAPP_ID = "PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3";

    private $market;
    private $base;
    private $quote;

    function __construct() {
        if(isset($_GET['market'])) {
            $market = OfferService::getMarketByName($_GET['market']);
            $this->market_id = $market['id'];
        }
        $this->market = OfferService::getMarket($this->market_id);
        $this->base = $this->market['base'];
        $this->quote = $this->market['quote'];
        $this->base_receive_address = OfferService::getUserCoinAddress(OfferService::userAddress(), $this->base);
        $this->quote_receive_address = OfferService::getUserCoinAddress(OfferService::userAddress(), $this->quote);
    }
    function market_name() {
        return $this->market['market_name'];
    }

    function login() {
        Pajax::login(self::APP_NAME, self::BASE_URL);
    }

    function logout() {
        Pajax::logout(self::BASE_URL);
    }

    function error($msg) {
        Pajax::executeScript('showError', $msg);
    }
    function success($msg) {
        Pajax::executeScript('showSuccess', $msg);
    }

    function logUserError($msg) {
        _log("[".OfferService::userAddress()."] Error: ".$msg);
    }

    function logUserInfo($msg) {
        _log("[".OfferService::userAddress()."]: ".$msg);
    }

    function cancelOffer($offer_id) {
        $offer = OfferService::getOffer($offer_id);
        if(!$offer) {
            $this->error("Offer $offer_id not found");
            return;
        }
        if(!$this->canCancelOffer($offer)) {
            $this->error("You cannot cancel offer $offer_id");
            return;
        }
        if($offer['status']==OfferService::STATUS_CREATED) {
            OfferService::cancelCreatedOffer($offer['id']);
            $this->success("Offer #$offer_id has been cancelled");
            Pajax::executeScript('focusOffer', $offer['id']);
            return;
        }
        if($offer['status']==OfferService::STATUS_OPEN) {
            OfferService::cancelOpenOffer($offer);
            $this->success("Offer #$offer_id has been cancelled");
            Pajax::executeScript('focusOffer', $offer['id']);
            return;
        }
        if($offer['status']==OfferService::STATUS_ACCEPTED) {
            OfferService::cancelAcceptedOffer($offer);
            $this->success("Offer #$offer_id has been cancelled");
        }
    }

    public $quote_receive_address;
    public $type;
    public $market_id = 1;
    function createBuyOffer() {
        if(empty($this->buy_base_amount)) {
            $this->error("Empty {$this->base} amount");
            return;
        }
        $this->buy_base_amount = floatval($this->buy_base_amount);
        if(empty($this->buy_base_price)) {
            $this->error("Empty price");
            return;
        }
        $this->buy_base_price = floatval($this->buy_base_price);
        $maxBuyPrice = floatval($this->maxBuyPrice());
        if(!empty($maxBuyPrice) && $this->buy_base_price >= $maxBuyPrice) {
            $this->error("You can not set price grater then best sell offer!");
            return;
        }
        $quoteService = OfferService::getService($this->market['quote_service']);
        $buy_quote_total = $this->buy_base_amount * $this->buy_base_price;
        $buy_quote_total = round($buy_quote_total, $quoteService->getDecimals());
        $min_trade = OfferService::getMinCoinTrade();
        if($buy_quote_total < $min_trade) {
            $this->error("Minimum coin trade id $min_trade ".$this->quote);
            return;
        }
        if(empty($this->base_receive_address)) {
            $this->error("Empty {$this->base} receiving address");
            return;
        }
        $valid = Account::valid($this->base_receive_address);
        if(!$valid) {
            $this->error("Invalid {$this->base} receive address");
            return;
        }
        $quoteService = OfferService::getService($this->market['quote_service']);
        $quote_dust_amount = OfferService::checkQuoteDustAmount($buy_quote_total, $quoteService, $this->market_id);
        if($quote_dust_amount === false) {
            $this->error("Error creating buy offer");
            return;
        }
        $user_address = OfferService::userAddress();
        $offer = [
            'type' => OfferService::TYPE_BUY,
            'market_id' => $this->market_id,
            'base_amount'=>$this->buy_base_amount,
            'base_price'=>$this->buy_base_price,
            'expires_at'=>null,
            'user_created'=>$user_address,
            'quote_receive_address'=>null,
            'base_receive_address'=>$this->base_receive_address,
            'base_dust_amount'=>0,
            'quote_dust_amount'=>$quote_dust_amount,
        ];
        $offer_id = OfferService::createOffer($offer);
        if(!$offer_id) {
            $this->error("Unable to create offer");
            return;
        }
        OfferService::storeUserCoinAddress(OfferService::userAddress(), $this->base, $this->base_receive_address);
        $this->clearTradeInputs();
        $this->success("Your offer has been created");
        Pajax::executeScript('focusOffer', $offer_id);
    }
    function createSellOffer() {
        if(empty($this->sell_base_amount)) {
            $this->error("Empty {$this->base} amount");
            return;
        }
        $this->sell_base_amount = floatval($this->sell_base_amount);
        if(empty($this->sell_base_price)) {
            $this->error("Empty price");
            return;
        }
        $this->sell_base_price = floatval($this->sell_base_price);
        $minSellPrice = $this->minSellPrice();
        $minSellPrice = floatval($minSellPrice);
        if(!empty($minSellPrice) && $this->sell_base_price <= $minSellPrice) {
            $this->error("You can not set price less then best buy offer!");
            return;
        }
        $quoteService = OfferService::getService($this->market['quote_service']);
        $sell_quote_total = $this->sell_base_amount * $this->sell_base_price;
        $sell_quote_total = round($sell_quote_total, $quoteService->getDecimals());
        $min_trade = OfferService::getMinCoinTrade();
        if($sell_quote_total < $min_trade) {
            $this->error("Minimum coin trade id $min_trade ".$this->quote);
            return;
        }
        if(empty($this->quote_receive_address)) {
            $this->error("Empty ".$this->quote." receiving address");
            return;
        }
        $valid = $quoteService->checkAddress($this->quote_receive_address);
        if(!$valid) {
            $this->error("Invalid ".$this->quote." receive address");
            return;
        }
        $baseService = OfferService::getService($this->market['base_service']);
        $base_dust_amount = OfferService::checkBaseDustAmount($this->sell_base_amount,$baseService, $this->market_id);
        if($base_dust_amount === false) {
            $this->error("Error creating sell offer");
            return;
        }
        $user_address = OfferService::userAddress();
        $offer = [
            'type' => OfferService::TYPE_SELL,
            'market_id' => $this->market_id,
            'base_amount'=>$this->sell_base_amount,
            'base_price'=>$this->sell_base_price,
            'expires_at'=>null,
            'user_created'=>$user_address,
            'quote_receive_address'=>$this->quote_receive_address,
            'base_receive_address'=>null,
            'base_dust_amount'=>$base_dust_amount,
            'quote_dust_amount'=>0,
        ];
        $offer_id = OfferService::createOffer($offer);
        if(!$offer_id) {
            $this->error("Unable to create offer");
            return;
        }
        OfferService::storeUserCoinAddress(OfferService::userAddress(), $this->quote, $this->quote_receive_address);
        $this->clearTradeInputs();
        $this->success("Your offer has been created");
        Pajax::executeScript('focusOffer', $offer_id);
    }

    function clearTradeInputs() {
        $this->buy_base_price = null;
        $this->buy_base_amount = null;
        $this->buy_quote_total = null;
        $this->sell_base_amount = null;
        $this->sell_base_price = null;
        $this->sell_quote_total = null;
        $this->sell_offer = null;
    }

    public $offer_id;
    function openOffer($offer_id) {
        $this->offer_id = $offer_id;
    }

    function getTemplate() {
        require_once dirname(__DIR__).'/template.php';
    }


    function offerRemainTime($offer) {
       $elapsed = time() - strtotime($offer['created_at']);
       $remain = OfferService::OFFER_WAIT_TIME - $elapsed;
       $perc = 100 * $elapsed /  OfferService::OFFER_WAIT_TIME;
       if($remain > 0) {
           $time = date("H:i", $remain);
       } else {
           $time = "- " . date("H:i", -$remain);
       }
       return ["time" => $time, "perc" => $perc];
    }

    function acceptedfferRemainTime($offer) {
       $elapsed = time() - strtotime($offer['accepted_at']);
       $remain = OfferService::OFFER_WAIT_TIME - $elapsed;
       $perc = 100 * $elapsed /  OfferService::OFFER_WAIT_TIME;
        if($remain > 0) {
            $time = date("H:i", $remain);
        } else {
            $time = "- " . date("H:i", -$remain);
        }
        return ["time" => $time, "perc" => $perc];
    }

    function acceptOffer() {
        $offer = OfferService::getOffer($this->offer_id);
        if(!$offer) {
            $this->error("Offer not found");
            return;
        }
        if($offer['type']==OfferService::TYPE_SELL) {
            if(empty($this->base_receive_address)) {
                $this->error("Empty {$this->base} receive address");
                return;
            }
            $valid = Account::valid($this->base_receive_address);
            if(!$valid) {
                $this->error("Invalid {$this->base} receive address");
                return;
            }
            $quote_total = $offer['base_amount'] * $offer['base_price'];
            $quoteService = OfferService::getService($this->market['quote_service']);
            $quote_dust_amount = OfferService::checkQuoteDustAmount($quote_total, $quoteService, $this->market_id);
            if($quote_dust_amount === false) {
                $this->error("Error creating offer");
                return;
            }
            $res = OfferService::acceptSellOffer($this->offer_id, $this->base_receive_address, OfferService::userAddress(), $quote_dust_amount);
            if(!$res) {
                $this->error("Unable to accept sell offer");
                return;
            }
        }
        if($offer['type']==OfferService::TYPE_BUY) {
            if(empty($this->quote_receive_address)) {
                $this->error('Empty '.$this->quote.' receive address');
                return;
            }
            $quoteService = OfferService::getService($this->market['quote_service']);
            $valid = $quoteService->checkAddress($this->quote_receive_address);
            if(!$valid) {
                $this->error('Invalid '.$this->quote.' receive address');
                return;
            }
            $baseService = OfferService::getService($this->market['base_service']);
            $base_dust_amount = OfferService::checkBaseDustAmount($offer['base_amount'], $baseService, $this->market_id);
            if($base_dust_amount === false) {
                $this->error("Error creating offer");
                return;
            }
            $res = OfferService::acceptBuyOffer($this->offer_id, $this->quote_receive_address, OfferService::userAddress(), $base_dust_amount);
            if(!$res) {
                $this->error("Unable to accept sell offer");
                return;
            }
        }
    }

    private function canOpenOffer($offer)
    {
        return OfferService::userAddress() != null && ($offer['user_created']==OfferService::userAddress() || $offer['user_accepted']==OfferService::userAddress());
    }
    function canCancelOffer($offer) {
        $user_created_cancel = ($offer['status']==OfferService::STATUS_CREATED || $offer['status']==OfferService::STATUS_OPEN)
            && $offer['user_created']==OfferService::userAddress();
        $user_accepted_cancel =  ($offer['status']==OfferService::STATUS_ACCEPTED)
            && $offer['user_accepted']==OfferService::userAddress();
        if($user_created_cancel || $user_accepted_cancel) {
            return true;
        }
        return false;
    }
    function canAcceptOffer($offer) {
        if((!empty(OfferService::userAddress()) && $offer['user_created']!=OfferService::userAddress()
            && $offer['status']==OfferService::STATUS_OPEN)) {
            return true;
        }
        return false;
    }
    public $base_receive_address;

    function depositFromWallet() {
        $offer = OfferService::getOffer($this->offer_id);
        if($offer['type']==OfferService::TYPE_SELL) {
            $amount = $offer['base_amount'] + $offer['base_dust_amount'];
            $service = OfferService::getService($offer['base_service']);
            $symbol = $offer['base'];
        } else {
            $amount = $offer['base_amount']*$offer['base_price'] + $offer['quote_dust_amount'];
            $service = OfferService::getService($offer['quote_service']);
            $symbol = $offer['quote'];
        }
        $_SESSION['offer'] = $offer;
        _log("depositFromWallet: $symbol Offer #".$offer['id']." amount=$amount");
        $service->depositFromWallet($amount, $offer);
    }
    function depositFromWalletCallback($data) {
        $offer = $_SESSION['offer'];
        if($offer['type']==OfferService::TYPE_SELL) {
            $service = OfferService::getService($offer['base_service']);
            $symbol = $offer['base'];
        } else {
            $service = OfferService::getService($offer['quote_service']);
            $symbol = $offer['quote'];
        }
        _log("depositFromWalletCallback: $symbol Offer #".$offer['id']." data=".json_encode($data));
        $service->depositFromWalletCallback($offer, $data);
    }
    function transferFromWallet() {
        $offer = OfferService::getOffer($this->offer_id);
        if($offer['type']==OfferService::TYPE_SELL) {
            $service = OfferService::getService($offer['quote_service']);
            $amount = $offer['base_amount']*$offer['base_price'] + $offer['quote_dust_amount'];
            $symbol = $offer['base'];
        } else {
            $service = OfferService::getService($offer['base_service']);
            $amount = $offer['base_amount'] + $offer['base_dust_amount'];
            $symbol = $offer['quote'];
        }
        $_SESSION['offer'] = $offer;
        _log("transferFromWallet: $symbol Offer #".$offer['id']." amount=$amount");
        $service->transferFromWallet($amount, $offer);
    }
    function transferFromWalletCallback($data) {
        $offer = $_SESSION['offer'];
        if($offer['type']==OfferService::TYPE_SELL) {
            $service = OfferService::getService($offer['quote_service']);
            $symbol = $offer['base'];
        } else {
            $service = OfferService::getService($offer['base_service']);
            $symbol = $offer['quote'];
        }
        _log("transferFromWalletCallback: $symbol Offer #".$offer['id']." data=".json_encode($data));
        $service->transferFromWalletCallback($offer, $data);
    }

    function createdViewTemplate($offer) {
            $remain = $this->offerRemainTime($offer);
            if($offer['type']==OfferService::TYPE_SELL) {
                $service = OfferService::getService($offer["base_service"]);
                $depositCoin = $this->base;
                $depositAmount = $offer['base_amount'] + $offer['base_dust_amount'];
                $depositAddress = $service->getEscrowAddress();
                $depositAddressLink = $service->addressLink($depositAddress);
                $maxTradeFee = num($service->getMaxTradeFee(),$service->getDecimals());
                $dustAmount = $offer['base_dust_amount'];
            } else {
                $service = OfferService::getService($offer["quote_service"]);
                $depositCoin = $this->quote;
                $depositAmount = $offer['base_amount']*$offer['base_price'] + $offer['quote_dust_amount'];
                $depositAddress = $service->getEscrowAddress();
                $depositAddressLink = $service->addressLink($depositAddress);
                $maxTradeFee = num($service->getMaxTradeFee(),$service->getDecimals());
                $dustAmount = $offer['quote_dust_amount'];
            }
            ?>
            <div class="card p-2">
                <div class="d-flex flex-wrap flex-sm-nowrap">
                    <div>
                        <h5>Your offer is in status created</h5>
                        <h6>You need to deposit <?= $depositCoin ?> to make offer available for other users</h6>
                        <dl class="row my-3">
                            <dt class="col-sm-3">Deposit amount:</dt>
                            <dd class="col-sm-9 fw-bold"><?= $depositAmount ?> <?= $depositCoin ?></dd>
                            <dt class="col-sm-3">Deposit address:</dt>
                            <dd class="col-sm-9">
                                <a class="fw-bold" href="<?=$depositAddressLink ?>" target="_blank"><?=$depositAddress ?></a>
                                <span class="fa fa-copy" data-bs-toggle="tooltip" title="Copy" onclick="copyToClipboard(event, '<?=$depositAddress ?>')"
                                      style="cursor: pointer"></span>
                            </dd>
                        </dl>
                        <p class="fw-bold">
                            Please send the exact amount so your deposit will be processed automatically.
                        </p>
                        <?php if ($dustAmount > 0) { ?>
                            <p>
                            <span class="fa fa-info-circle" style="cursor: pointer" data-bs-toggle="tooltip" title="
                                💡 To ensure your deposit is uniquely linked to your offer and processed securely,we include a very small “trade fee” (less than <?=$maxTradeFee?> <?=$depositCoin?>). This fee helps us identify your transaction without manual intervention.
                            "></span>
                                This amount includes a <strong>small system fee</strong> to ensure secure, automated trade matching.
                            </p>
                        <?php } ?>
                        <p class="text-muted">
                            You have 2 hours to make a deposit. After that time offer will expire.
                        </p>
                        <div class="d-flex align-items-baseline gap-2 flex-wrap">
                            <a href="" class="btn btn-primary" onclick="depositFromWallet(event); return false">Deposit from wallet</a>
                            <p class="text-muted">
                                You can also deposit directly from your wallet
                            </p>
                        </div>
                    </div>
                    <div class="text-center">
                        Remain time
                        <div class="circle-progress mt-2" style="--progress: <?=$remain['perc']?>">
                            <div class="circle-inner">
                                <div class="minutes"><?=$remain['time']?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
    }

    function canceledViewTemplate($offer) {
        ?>
        <div class="card p-2">
            <h5>Offer is canceled</h5>
            <h6>You canceled this offer</h6>
            <?php if (!empty($offer['return_tx_id'])) {
                if($offer['type']==OfferService::TYPE_SELL) {
                    $service = OfferService::getService($offer['base_service']);
                } else {
                    $service = OfferService::getService($offer['quote_service']);
                }
                ?>
                <div>
                    Your deposited coin is returned to your address
                </div>
                <div>
                    Return transaction ID:
                    <a href="<?= $service->txLink($offer['return_tx_id']) ?>" target="_blank"><?=$offer['return_tx_id']?></a>
                </div>
            <?php } ?>
        </div>
        <?php
    }

    function expiredViewTemplate($offer) {
        ?>
        <div class="card p-2">
            <h5>Offer is expired</h5>
            <?php if(empty($offer['return_tx_id'])) { ?>
                <h6>You did not make a deposit in the required time frame</h6>
            <?php } else { ?>
                <h6>Your deposited coin is returned to your address</h6>
            <?php } ?>
        </div>
        <?php
    }

    function depositingViewTemplate($offer) {
        $depositTxId = $offer['deposit_tx_id'];
        if($offer['type']==OfferService::TYPE_SELL) {
            $baseService = OfferService::getService($offer['base_service']);
            $tx = $baseService->findTransaction($depositTxId);
            $last_height = $baseService->getLastheight();
            $confirmations = $last_height - $tx['height'];
            $depositTxLink = $baseService->txLink($depositTxId);
            $minConfirmations = $baseService->getConfirmations('wait');
            $symbol = $offer['base'];
        } else {
            $quoteService = OfferService::getService($offer['quote_service']);
            $tx = $quoteService->findTransaction($depositTxId);
            $last_height = $quoteService->getLastheight();
            $confirmations = $last_height - $tx['height'];
            $depositTxLink = $quoteService->txLink($depositTxId);
            $minConfirmations = $quoteService->getConfirmations('wait');
            $symbol = $offer['quote'];
        }
        ?>
        <div class="card p-2">
            <h5>Your <?=$symbol?> deposit is detected on blockchain</h5>
            <h6>After the required number of confirmations, your offer will be open for <?= $offer['type']==OfferService::TYPE_SELL ? 'buyers' : 'sellers' ?></h6>
            <dl class="row my-3">
                <dt class="col-sm-3">Deposit Transaction ID:</dt>
                <dd class="col-sm-9">
                    <a href="<?=$depositTxLink ?>" target="_blank"><?=$depositTxId ?></a>
                </dd>
                <dt class="col-sm-3">Confirmations:</dt>
                <dd class="col-sm-9">
                    <?=$confirmations ?> / <?=$minConfirmations?>
                </dd>
            </dl>
        </div>
        <?php
    }

    function openViewTemplate($offer) {
        if($offer['type']==OfferService::TYPE_SELL) {
            $depositCoin =  $offer['base'];
        } else {
            $depositCoin = $offer['quote'];
        }
        ?>
        <?php if($offer['user_created']==OfferService::userAddress()) { ?>
            <div class="card p-2">
                <h5>Your offer is opened</h5>
                <h6>You can wait for <?= $offer['type']==OfferService::TYPE_BUY ? 'sellers' : 'buyers' ?> or cancel this offer</h6>
            </div>
        <?php } else { ?>
            <?php if ($offer['type']==OfferService::TYPE_SELL) { ?>
                <div class="card p-2">
                    <h5>Accept this offer</h5>
                    <h6>You are accepting to buy <?=$offer['base_amount']?> <?=$this->base ?>
                        for <?=$offer['base_amount']*$offer['base_price']?> <?=$depositCoin?>
                        (price <?=$offer['base_price']?>)</h6>
                </div>
                <div>
                    <label for="base_receive_address"><?=$this->base ?> receive address</label>
                    <input type="text" class="form-control" id="base_receive_address" name="base_receive_address" value="<?=$this->base_receive_address ?>"/>
                </div>
            <?php } else { ?>
                <div class="card p-2">
                    <h5>Accept this offer</h5>
                    <h6>You are accepting to sell <?=$offer['base_amount']?> <?=$this->base ?>
                        for <?=$offer['base_amount']*$offer['base_price']?> <?=$depositCoin?>
                        (price <?=$offer['base_price']?>)</h6>
                </div>
                <div>
                    <label for="quote_receive_address"><?=$depositCoin?> receive address</label>
                    <input type="text" class="form-control" id="quote_receive_address" name="quote_receive_address" value="<?=$this->quote_receive_address ?>"/>
                </div>
            <?php } ?>
        <?php } ?>



        <?php
    }

    function acceptedViewTemplate($offer) {
        $remain = $this->acceptedfferRemainTime($offer);
        ?>
        <?php if ($offer['user_accepted']==OfferService::userAddress()) {
            $baseService = OfferService::getService($offer['base_service']);
            $quoteService = OfferService::getService($offer['quote_service']);
            if($offer['type']==OfferService::TYPE_SELL) {
                $transferCoin = $this->quote;
                $quote_total = $offer['base_amount']*$offer['base_price'];
                $transferAmount = $quote_total + $offer['quote_dust_amount'];
                $transferAddress = $quoteService->getEscrowAddress();
                $transferAddressLink = $baseService->addressLink($transferAddress);
                $dustAmount = $offer['quote_dust_amount'];
            } else {
                $transferCoin = $this->base;
                $transferAmount = $offer['base_amount'] + $offer['base_dust_amount'];
                $transferAddress = $baseService->getEscrowAddress();
                $transferAddressLink = $quoteService->addressLink($transferAddress);
                $dustAmount = $offer['quote_dust_amount'];
            }
            ?>
            <div class="card p-2">
                <div class="d-flex flex-wrap flex-column flex-sm-row">
                    <div style="flex:1">
                        <h5>You accepted this offer</h5>
                        <h6>You need to transfer your <?= $transferCoin ?> to escrow address</h6>
                        <dl class="row my-3">
                            <dt class="col-sm-3">Transfer amount:</dt>
                            <dd class="col-sm-9 fw-bold">
                                <span><?= $transferAmount ?></span>
                                <span class="fa fa-copy" data-bs-toggle="tooltip" title="Copy" onclick="copyToClipboard(event, '<?=$transferAmount ?>')"
                                      style="cursor: pointer"></span>
                                <?= $transferCoin ?></dd>
                            <dt class="col-sm-3">Transfer to address:</dt>
                            <dd class="col-sm-9">
                                <a class="fw-bold text-break" href="<?= $transferAddressLink ?>" target="_blank"><?=$transferAddress ?></a>
                                <span class="fa fa-copy" data-bs-toggle="tooltip" title="Copy" onclick="copyToClipboard(event, '<?=$transferAddress ?>')"
                                      style="cursor: pointer"></span>
                            </dd>
                        </dl>
                        <p class="fw-bold">
                            Please send the exact amount so your transfer will be processed automatically.
                        </p>
                        <?php if ($dustAmount > 0) { ?>
                            <p>
                                <span class="fa fa-info-circle" style="cursor: pointer" data-bs-toggle="tooltip" title="
                                    💡 To ensure your transfer is uniquely linked to your offer and processed securely,we include a very small “trade fee”. This fee helps us identify your transaction without manual intervention.
                                "></span>
                                This amount includes a <strong>small system fee</strong> to ensure secure, automated trade matching.
                            </p>
                        <?php } ?>
                        <p class="text-muted">
                            You have 2 hours to make transfer. After that time offer will expire
                        </p>
                        <div class="d-flex align-items-baseline gap-2 flex-wrap">
                            <a href="" class="btn btn-primary" onclick="transferFromWallet(event); return false">Transfer from wallet</a>
                            You can also transfer directly from your wallet
                        </div>
                    </div>
                    <div class="text-sm-center text-start">
                        Remain time
                        <div class="circle-progress mt-2" style="--progress: <?=$remain['perc']?>">
                            <div class="circle-inner">
                                <div class="minutes"><?=$remain['time']?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
        <?php if ($offer['user_created']==OfferService::userAddress()) { ?>
            <div class="card p-2 d-flex flex-row">
                <div style="flex:1">
                    <h5>Your offer is accepted</h5>
                    <h6>Please wait until another party starts trade</h6>
                    <p class="text-muted">
                        If another user does not start trade in 2-hour offer will be returned back to status open.
                    </p>
                </div>
                <div class="text-center ms-auto">
                    Remain time
                    <div class="circle-progress mt-2" style="--progress: <?=$remain['perc']?>">
                        <div class="circle-inner">
                            <div class="minutes"><?=$remain['time']?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
        <?php
    }

    function transferringViewTemplate($offer) {
        $accept_tx_id = $offer['accept_tx_id'];
        if ($offer['type'] == OfferService::TYPE_SELL) {
            $quoteService = OfferService::getService($offer['quote_service']);
            $tx = $quoteService->findTransaction($accept_tx_id);
            $last_height = $quoteService->getLastHeight();
            $confirmations = $last_height - $tx['height'];
            $transferTxLink = $quoteService->txLink($accept_tx_id);
            $minConfirmations = $quoteService->getConfirmations('wait');
            $symbol = $offer['quote'];
            $otherSymbol = $offer['base'];
        } else {
            $baseService = OfferService::getService($offer['base_service']);
            $tx = $baseService->findTransaction($accept_tx_id);
            $last_height = $baseService->getLastHeight();
            $confirmations = $last_height - $tx['height'];
            $transferTxLink = $baseService->txLink($accept_tx_id);
            $minConfirmations = $baseService->getConfirmations('wait');
            $symbol = $offer['quote'];
            $otherSymbol = $offer['quote'];
        }
        $creator = $offer['user_created'] == OfferService::userAddress();
        ?>
        <div class="card p-2">
            <h5>
                <?= $creator ? 'Other party ' : ' Your ' ?><?=$symbol?> transfer is detected on blockchain
            </h5>
            <h6>
                After the required number of confirmations, a trade process will begin
                and you will receive your <?= $creator ? $symbol : $otherSymbol ?>
            </h6>
            <dl class="row my-3">
                <dt class="col-sm-3">Transfer Transaction ID:</dt>
                <dd class="col-sm-9">
                    <a class="text-break" href="<?=$transferTxLink ?>" target="_blank"><?=$accept_tx_id ?></a>
                </dd>
                <dt class="col-sm-3">Confirmations:</dt>
                <dd class="col-sm-9">
                    <?=$confirmations ?> / <?=$minConfirmations?>
                </dd>
            </dl>
        </div>
        <?php
    }

    function transferredViewTemplate($offer) {
        $accept_tx_id = $offer['accept_tx_id'];
        if ($offer['type'] == OfferService::TYPE_SELL) {
            $quoteService = OfferService::getService($offer['quote_service']);
            $transferTxLink = $quoteService->txLink($accept_tx_id);
        } else {
            $baseService = OfferService::getService($offer['base_service']);
            $transferTxLink = $baseService->txLink($accept_tx_id);
        }
        $creator = $offer['user_created'] == OfferService::userAddress();
        ?>
        <div class="card p-2">
            <h5>Offer is completed</h5>
            <h6>Please wait while service process trade</h6>
            <?php if(!$creator) { ?>
                <dl class="row my-3">
                    <dt class="col-sm-3">Transfer Transaction ID:</dt>
                    <dd class="col-sm-9">
                        <a class="text-break" href="<?=$transferTxLink ?>" target="_blank"><?=$accept_tx_id ?></a>
                    </dd>
                </dl>
            <?php } ?>
        </div>
        <?php
    }

    function payingViewTemplate($offer) {
        $creator = $offer['user_created'] == OfferService::userAddress();
        if(($offer['type'] == OfferService::TYPE_SELL && $creator) || ($offer['type'] == OfferService::TYPE_BUY && !$creator)) {
            $quoteService = OfferService::getService($offer['quote_service']);
            $payingCoin = $offer['quote'];
            $paymentAddress = $offer['quote_receive_address'];
            $paymentAddressLink = $quoteService->addressLink($paymentAddress);
            $paymentTxId = $offer['quote_transfer_tx_id'];
            $paymentTxLink = $quoteService->txLink($paymentTxId);
            $tx = $quoteService->findTransaction($paymentTxId);
            $last_height = $quoteService->getLastHeight();
            $confirmations = $last_height - $tx['height'];
            $transferTxLink = $quoteService->txLink($paymentTxId);
            $minConfirmations = $quoteService->getConfirmations('wait');
        } else {
            $service = OfferService::getService($offer['base_service']);
            $payingCoin = $this->base;
            $paymentAddress = $offer['base_receive_address'];
            $paymentAddressLink = $service->addressLink($paymentAddress);
            $paymentTxId = $offer['base_transfer_tx_id'];
            $paymentTxLink = $service->txLink($paymentTxId);
            $last_height = $service->getLastHeight();
            $tx = $service->findTransaction($paymentTxId);
            $confirmations = $last_height - $tx['height'];
            $minConfirmations = $service->getConfirmations('wait');
        }

        ?>
        <div class="card p-2">
            <h5>Offer is paid</h5>
            <h6>Please wait while <?=$payingCoin?> is transferred to your address </h6>
            <dl class="row my-3">
                <dt class="col-sm-3">Payment Address:</dt>
                <dd class="col-sm-9">
                    <a class="text-break" href="<?= $paymentAddressLink ?>" target="_blank"><?=$paymentAddress ?></a>
                </dd>
                <dt class="col-sm-3">Payment Transaction ID:</dt>
                <dd class="col-sm-9">
                    <a class="text-break" href="<?= $paymentTxLink ?>" target="_blank"><?=$paymentTxId ?></a>
                </dd>
                <dt class="col-sm-3">Confirmations:</dt>
                <dd class="col-sm-9">
                    <?=$confirmations ?> / <?=$minConfirmations?>
                </dd>
            </dl>
        </div>
        <?php
    }

    function closedViewTemplate($offer) {
        $creator = $offer['user_created'] == OfferService::userAddress();
        if(($offer['type'] == OfferService::TYPE_SELL && $creator) || ($offer['type'] == OfferService::TYPE_BUY && !$creator)) {
            $soldCoin = $offer['base'];
            $soldAmount = $offer['base_amount'];
            $receivedCoin = $offer['quote'];
            $receivedAmount = $offer['base_price']*$offer['base_amount'];
            $receiveAddress = $offer['quote_receive_address'];
            $quoteService = OfferService::getService($offer['quote_service']);
            $receiveAddressLink = $quoteService->addressLink($receiveAddress);
        } else {
            $service = OfferService::getService($offer['base_service']);
            $soldCoin = $offer['quote'];
            $soldAmount = $offer['base_price']*$offer['base_amount'];
            $receivedCoin = $offer['base'];
            $receivedAmount = $offer['base_amount'];
            $receiveAddress = $offer['base_receive_address'];
            $receiveAddressLink = $service->addressLink($receiveAddress);
        }
        ?>
        <div class="card p-2">
            <h5>Offer is closed</h5>
            <dl class="row my-3">
                <dt class="col-sm-3">Sold <?= $soldCoin ?>:</dt>
                <dd class="col-sm-9"><?= $soldAmount ?></dd>
                <dt class="col-sm-3">Received <?=$receivedCoin?>:</dt>
                <dd class="col-sm-9"><?= $receivedAmount ?></dd>
                <dt class="col-sm-3">Price:</dt>
                <dd class="col-sm-9"><?= $offer['base_price'] ?></dd>
                <dt class="col-sm-3">Receive Address:</dt>
                <dd class="col-sm-9">
                    <a href="<?= $receiveAddressLink ?>" target="_blank"><?=$receiveAddress ?></a>
                </dd>
            </dl>
        </div>
        <?php
    }

    public $buy_base_price;
    public $buy_base_amount;
    public $buy_quote_total;
    public $sell_base_price;
    public $sell_base_amount;
    public $sell_quote_total;

    private $sell_offer;


    function selectOffer($id) {
        $offer = OfferService::getOffer($id);
        if($offer['type']==OfferService::TYPE_SELL) {
            $minSellOffer = OfferService::getMinSellOffer($this->market_id);
            if($offer['base_price'] == $minSellOffer['base_price']) {
                $this->buy_base_price = $offer['base_price'];
                $this->buy_base_amount = $offer['base_amount'];
                $this->buy_quote_total = $offer['base_price'] * $offer['base_amount'];
                $this->sell_offer = $offer;
            } else {
                $this->buy_base_price = $minSellOffer['base_price'];
                $this->buy_base_amount = $minSellOffer['base_amount'];
                $this->buy_quote_total = $minSellOffer['base_price'] * $minSellOffer['base_amount'];
                $this->sell_offer = $minSellOffer;
            }
            $this->sell_base_price = $offer['base_price'];
            $this->sell_base_amount = $offer['base_amount'];
            $this->sell_quote_total = $offer['base_amount'] * $offer['base_price'];

        } else {
            $maxBuyOffer = OfferService::getMaxBuyOffer($this->market_id);
            if($offer['base_price'] == $maxBuyOffer['base_price']) {
                $this->sell_base_price = $offer['base_price'];
                $this->sell_base_amount = $offer['base_amount'];
                $this->sell_quote_total = $offer['base_price'] * $offer['base_amount'];
                $this->sell_offer = $offer;
            } else {
                $this->sell_base_price = $maxBuyOffer['base_price'];
                $this->sell_base_amount = $maxBuyOffer['base_amount'];
                $this->sell_quote_total = $maxBuyOffer['base_price'] * $maxBuyOffer['base_amount'];
                $this->sell_offer = $maxBuyOffer;
            }
            $this->buy_base_price = $offer['base_price'];
            $this->buy_base_amount = $offer['base_amount'];
            $this->buy_quote_total = $offer['base_amount'] * $offer['base_price'];
        }
    }

    function disablebBuyInputs() {
        return $this->sell_offer != null && $this->sell_offer['type']==OfferService::TYPE_SELL;
    }

    function disablebSellInputs() {
        return $this->sell_offer != null && $this->sell_offer['type']==OfferService::TYPE_BUY;
    }

    function minSellPrice() {
        $maxBuyOffer = OfferService::getMaxBuyOffer($this->market_id);
        return $maxBuyOffer['base_price'];
    }

    function maxBuyPrice() {
        $minSellOffer = OfferService::getMinSellOffer($this->market_id);
        return $minSellOffer['base_price'];
    }

    function acceptSellOffer($offer_id) {
        $offer = OfferService::getOffer($offer_id);
        if(!$offer) {
            $this->error("Offer not found");
            return;
        }
        if($offer['user_created']==OfferService::userAddress()) {
            $this->error("You cannot accept your offer");
            return;
        }
        if(empty($this->base_receive_address)) {
            $this->error("Empty {$this->base} receive address");
            return;
        }
        $valid = Account::valid($this->base_receive_address);
        if(!$valid) {
            $this->error("Invalid {$this->base} receive address");
            return;
        }
        $quote_total = $offer['base_amount'] * $offer['base_price'];
        $quoteService = OfferService::getService($this->market['quote_service']);
        $quote_dust_amount = OfferService::checkQuoteDustAmount($quote_total, $quoteService, $this->market_id);
        if($quote_dust_amount === false) {
            $this->error("Error creating offer");
            return;
        }
        $res = OfferService::acceptSellOffer($offer['id'], $this->base_receive_address, OfferService::userAddress(), $quote_dust_amount);
        if(!$res) {
            $this->error("Unable to accept sell offer");
            return;
        }
        $this->clearTradeInputs();
        OfferService::storeUserCoinAddress(OfferService::userAddress(), $this->base, $this->base_receive_address);
        $this->success("You accepted offer");
        _log("acceptSellOffer: " .$offer['base']." User ".OfferService::userAddress()." accepted offer #".$offer['id']);
        Pajax::executeScript('focusOffer', $offer['id']);
    }

    function acceptBuyOffer($offer_id) {
        $offer = OfferService::getOffer($offer_id);
        if(!$offer) {
            $this->error("Offer not found");
            return;
        }
        if($offer['user_created']==OfferService::userAddress()) {
            $this->error("You cannot accept your offer");
            return;
        }
        if(empty($this->quote_receive_address)) {
            $this->error('Empty '.$this->quote.' receive address');
            return;
        }
        $quoteService = OfferService::getService($this->market['quote_service']);
        $valid = $quoteService->checkAddress($this->quote_receive_address);
        if(!$valid) {
            $this->error('Invalid '.$this->quote.' receive address');
            return;
        }
        $baseService = OfferService::getService($this->market['base_service']);
        $this->sell_base_amount = floatval($this->sell_base_amount);
        $base_dust_amount = OfferService::checkBaseDustAmount($this->sell_base_amount, $baseService, $this->market_id);
        if($base_dust_amount === false) {
            $this->error("Error creating offer");
            return;
        }
        $res = OfferService::acceptBuyOffer($offer['id'], $this->quote_receive_address, OfferService::userAddress(), $base_dust_amount);
        if(!$res) {
            $this->error("Unable to accept buy offer");
            return;
        }
        $this->clearTradeInputs();
        OfferService::storeUserCoinAddress(OfferService::userAddress(), $this->quote, $this->quote_receive_address);
        $this->success("You accepted offer");
        _log("acceptBuyOffer: " .$offer['base']." User ".OfferService::userAddress()." accepted offer #".$offer['id']);
        Pajax::executeScript('focusOffer', $offer['id']);
    }

    function getChartData($params) {
        echo json_encode(OfferService::getChartData($params, $this->market_id));
        exit;
    }

    function baseDecimals() {
        $baseService = OfferService::getService($this->market['base_service']);
        return $baseService->getDecimals();
    }
    function quoteDecimals() {
        $quoteService = OfferService::getService($this->market['quote_service']);
        return $quoteService->getDecimals();
    }

    function refresh() {

    }

    function offerDetails($offer) {
        ?>
        <div class="card mb-0 table-responsive collapse" id="offer-info">
        <table class="table table-striped table-sm">
            <tbody>
            <?php foreach($offer as $key => $val) {
                if(empty($val)) continue;
                if($key == 'expires_at') continue;
                if($key == 'market_id') continue;
                if($key == 'base_service') continue;
                if($key == 'quote_service') continue;
                if($key == 'base_asset_id') continue;
                if($key == 'quote_asset_id') continue;
                $label = OfferService::label($key);
                ?>
                <tr>
                    <td class="text-muted fw-bold">
                        <?= $label ?>:
                    </td>
                    <td class="text-break">
                        <?= $val ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        </div>

        <?php
    }

    function offerSummary($offer) {
        $quote_total = $offer['base_amount']*$offer['base_price'];
        ?>
        <div class="card fw-bold">
            <div class="card-body p-2">
                <div class="card-title d-flex justify-content-between align-items-center">
                    <h4 class="">Offer info</h4>
                    <a href="#" data-bs-toggle="collapse" data-bs-target="#offer-info" class="btn btn-sm btn-outline-primary">Show detailed info</a>
                </div>
                <dl class="row">
                    <dt class="col-4">Offer ID:</dt>
                    <dd class="col-8 mb-0"><?= $offer['id'] ?></dd>
                    <dt class="col-4">Status:</dt>
                    <dd class="col-8 mb-0"><?= strtoupper($offer['status']) ?></dd>
                    <dt class="col-4">Type:</dt>
                    <dd class="col-8 mb-0">
                        <span class="<?php if ($offer['type']==OfferService::TYPE_SELL) { ?>text-danger<?php } else { ?>text-success<?php } ?>"><?= strtoupper($offer['type']) ?></span>
                    </dd>
                    <dt class="col-4">Amount:</dt>
                    <dd class="col-8 mb-0">
                        <?= $offer['base_amount'] ?>
                        <?= $offer['base'] ?>
                    </dd>
                    <dt class="col-4">Price:</dt>
                    <dd class="col-8 mb-0">
                        <?= $offer['base_price'] ?>
                    </dd>
                    <dt class="col-4">Total :</dt>
                    <dd class="col-8 mb-0">
                        <?= $quote_total ?>  <?= $offer['quote'] ?>
                    </dd>
                    <dt class="col-4">Created :</dt>
                    <dd class="col-8 mb-0">
                        <?= $offer['created_at'] ?>
                    </dd>
                </dl>
            </div>
        </div>
        <?php
    }

}