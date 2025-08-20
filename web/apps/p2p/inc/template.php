<?php

/** @var AppView $this */

global $db;
$loggedIn = isset($_SESSION['account']);





$user_address = $_SESSION['account']['address'];

require_once __DIR__ . '/functions.php';







if($loggedIn) {
    $myCompletedOffers = OfferService::getMyCompletedOffers($this->market_id);
}

$tradeHistory = OfferService::getTradeHistory($this->market_id);


/** @var AppView $this */
?>


<div class="row">
    <div class="col-12">
        <div class="d-flex mt-2" id="header-top">
            <h3 class="d-flex align-items-center w-100 gap-2">
                PHPCoin P2P Trader
                <span class="badge bg-danger d-none d-sm-inline">Demo</span>
                <?php if (!$loggedIn) { ?>
                    <a href="" onclick="paction(event, 'login'); return false;" class="btn btn-primary ms-auto">Login</a>
                <?php } ?>
            </h3>
            <?php if ($loggedIn) { ?>
                <div class="d-flex gap-2 align-items-center ms-auto" id="header-address">
                    <div class="text-truncate">
                        <div class="text-truncate">
                            <a class="text-truncate fw-bold" href="/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/wallet"><?=$_SESSION['account']['address']?></a>
                        </div>
                        <?= Account::getBalance(OfferService::userAddress())?>
                    </div>
                    <a href="" class="btn btn-outline-primary" onclick="paction(event, 'logout')">Logout</a>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<hr/>

<div class="row">
    <div class="col-sm-12">
        <?php
        $lastOfferType = $tradeHistory[0]['type'];
        $lastPrice = $tradeHistory[0]['base_price'];
        $prevPrice = $tradeHistory[1]['base_price'];
        $openBaseAmount = OfferService::getOpenBaseAmount($this->market_id) ?? 0;
        $openQuoteAmount = OfferService::getOpenQuoteAmount($this->market_id);
        $marketStat = OfferService::getMarketStat($this->market_id);
        $baseService = OfferService::getService($this->market['base_service']);
        $quoteService = OfferService::getService($this->market['quote_service']);
        ?>
        <div class="card p-2" id="market-info">
            <div class="card-body p-0 d-flex flex-wrap align-items-center gap-2">
                <?=$this->market['image'] ?>
                <div class="fw-bold font-size-16"><?= $this->market_name() ?></div>
                <?php if(!empty($lastOfferType)) { ?>
                    <div>
                        <div class="font-size-16 fw-bold <?=  $lastOfferType == 'sell' ? 'text-danger' : 'text-success' ?>">
                            <?= $lastPrice ?>
                            <span class="fa fa-arrow-down"></span>
                        </div>
                        <div class="text-muted font-size-12"><?= $prevPrice ?></div>
                    </div>
                <?php } ?>
                <a class="fa fa-info-circle fa-lg ms-auto d-none" data-bs-toggle="collapse" data-bs-target="#market-info-details" id="market-info-details-toggle"></a>
                <div id="market-info-details" class="d-flex ms-auto gap-2 collapse flex-wrap scale-up-ver-top">
                    <div>
                        <div class="text-muted font-size-12">24h change</div>
                        <div class="font-size-12">
                            <?=$marketStat['change24h'] ?>
                            <?=$marketStat['change24hPerc'] ?>%
                        </div>
                    </div>
                    <div>
                        <div class="text-muted font-size-12">24h high</div>
                        <div class="font-size-12">
                            <?=num($marketStat['maxPrice']) ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-muted font-size-12">24h low</div>
                        <div class="font-size-12">
                            <?=num($marketStat['minPrice']) ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-muted font-size-12">24h volume (<?=$this->base ?>)</div>
                        <div class="font-size-12">
                            <?=num($marketStat['baseVolume'], $baseService->getDecimals()) ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-muted font-size-12">24h volume (<?= $this->quote ?>)</div>
                        <div class="font-size-12">
                            <?=num($marketStat['quoteVolume'], $quoteService->getDecimals()) ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-muted font-size-12">Open (<?=$this->base ?>)</div>
                        <div class="font-size-12">
                            <a href="<?= $baseService->addressLink($baseService->getEscrowAddress()) ?>" target="_blank"><?=$openBaseAmount?></a>
                        </div>
                    </div>
                    <div>
                        <div class="text-muted font-size-12">Open (<?= $this->quote ?>)</div>
                        <div class="font-size-12">
                            <a href="<?= $quoteService->addressLink($quoteService->getEscrowAddress()) ?>" target="_blank"><?=num($openQuoteAmount,$quoteService->getDecimals())?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<ul class="d-none nav nav-tabs nav-tabs-custom nav-justified mb-2" role="tablist" id="offers-switch">
    <li class="nav-item" role="presentation">
        <a class="nav-link active" data-bs-toggle="tab" role="tab" aria-selected="false" tabindex="-1" onclick="toggleOffers()">
            <span>Offers</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" data-bs-toggle="tab" role="tab" aria-selected="false" tabindex="-1" onclick="toggleChart()">
            <span>Chart</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" data-bs-toggle="tab" role="tab" aria-selected="false" tabindex="-1" onclick="toggleMarkets()">
            <span>Markets</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" data-bs-toggle="tab" role="tab" aria-selected="false" tabindex="-1" onclick="toggleTrades()">
            <span>Trades</span>
        </a>
    </li>
</ul>

<div class="row">
    <div class="col-sm-3 d-flex flex-column left-col">

        <?php
            Pajax::block('offers-book', function() use ($tradeHistory) {
                $openOffers = OfferService::getOpenffers($this->market_id);
                $lastOfferType = $tradeHistory[0]['type'];
                $lastPrice = $tradeHistory[0]['base_price'];
                $prevPrice = $tradeHistory[1]['base_price'];

                $offersList = [];
                foreach ($openOffers as $offer) {
                    $type = $offer['type'];
                    $offersList[$type][]=$offer;
                }

                ?>
                <div class="card flex-grow-1" id="offers-card">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="offers-book">
                            <tbody class="sell-tbody">
                                <tr class="sell-tbody-th-top">
                                    <th>Price</th>
                                    <th class="text-end">Amount<span class="amount-asset"> (<?= $this->base ?>)</span></th>
                                    <th class="text-end total">Total (<?= $this->quote ?>)</th>
                                </tr>
                                <?php foreach($offersList[OfferService::TYPE_SELL] as $offer) {
                                    $quote_total = $offer['base_price'] * $offer['base_amount'];
                                    ?>
                                    <tr onclick="selectOffer(event, <?= $offer['id'] ?>, '<?= $offer['type'] ?>')"
                                        class="<?php if($offer['user_created']==OfferService::userAddress()) { ?>fw-bold<?php } ?> <?php if ($offer['type']==OfferService::TYPE_SELL) { ?>type-sell<?php } else { ?>type-buy<?php } ?>
                                        <?php if ($this->sell_offer && $offer['id']==$this->sell_offer['id']) { ?>sel-offers-book<?php } ?>">
                                        <td class="<?php if ($offer['type']==OfferService::TYPE_SELL) { ?>text-danger<?php } else { ?>text-success<?php } ?>"><?=$offer['base_price'] ?></td>
                                        <td class="text-end"><?=num($offer['base_amount'],2) ?></td>
                                        <td class="text-end total"><?=$quote_total ?></td>
                                    </tr>
                                <?php } ?>
                                <tr class="sell-tbody-th-bottom d-none">
                                    <th>Price</th>
                                    <th class="text-end">Amount<span class="amount-asset"> (<?= $this->base ?>)</span></th>
                                    <th class="text-end total">Total (<?= $this->quote ?>)</th>
                                </tr>
                            </tbody>
                            <tbody class="price-tbody">
                                <tr>
                                    <td colspan="3">
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if(!empty($lastOfferType)) { ?>
                                                <span class="font-size-16 fw-bold <?= $lastOfferType == 'sell' ? 'text-danger' : 'text-success' ?>">
                                                    <?=  $lastPrice  ?>
                                                    <span class="fa fa-arrow-down"></span>
                                                </span>
                                                <span class="text-muted"><?= $prevPrice ?></span>
                                            <?php } ?>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tbody class="buy-tbody">
                                <tr class="header d-none">
                                    <th>Price</th>
                                    <th class="text-end">Amount<span class="amount-asset"> (<?= $this->base ?>)</span></th>
                                    <th class="text-end total">Total (<?= $this->quote ?>)</th>
                                </tr>
                                <?php foreach($offersList[OfferService::TYPE_BUY] as $offer) {
                                    $quote_total = $offer['base_price'] * $offer['base_amount'];
                                    ?>
                                    <tr onclick="selectOffer(event, <?= $offer['id'] ?>, '<?= $offer['type'] ?>')"
                                        class="<?php if($offer['user_created']==OfferService::userAddress()) { ?>fw-bold<?php } ?> <?php if ($offer['type']==OfferService::TYPE_SELL) { ?>type-sell<?php } else { ?>type-buy<?php } ?>
                                        <?php if ($this->sell_offer && $offer['id']==$this->sell_offer['id']) { ?>sel-offers-book<?php } ?>">
                                        <td class="<?php if ($offer['type']==OfferService::TYPE_SELL) { ?>text-danger<?php } else { ?>text-success<?php } ?>"><?=$offer['base_price'] ?></td>
                                        <td class="text-end"><?=num($offer['base_amount'],2) ?></td>
                                        <td class="text-end total"><?=$quote_total ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row d-none" id="buy-sell-switch">
                        <div class="col-6">
                            <button type="button" id="buy-switch" class="btn btn-success w-100" onclick="openBuyCard()">Buy <?=$this->base ?></button>
                        </div>
                        <div class="col-6">
                            <button type="button" id="sell-switch" class="btn btn-danger w-100" onclick="openSellCard()">Sell <?=$this->base ?></button>
                        </div>
                    </div>

                </div>

                <?php
            });

        ?>


    </div>

    <div class="col-sm-6 center-col">
        <div class="card" style="flex:1" id="market-chart">
            <div id="controls">
                <select id="interval" onchange="changeChartInterval(this.value)" class="form-select form-select-sm w-auto ms-auto">
                    <option value="5m">5 Min</option>
                    <option value="1h" selected>1 Hour</option>
                    <option value="1d">1 Day</option>
                </select>
            </div>
            <div style="height:100%">
                <div id="chart"></div>
            </div>
        </div>

        <?php

        Pajax::block('trade-cards', function() use ($loggedIn) {
            /** @var AppView $this */
            ?>
            <div class="row" id="trade-cards">
                <div class="col-sm-6">
                    <div class="card p-2" id="buy-card">
                        <div class="card-body p-0">
                            <div class="row">
                                <div id="base_price_div" class="col-6 col-sm-12 mb-2">
                                    <label  class="form-label" for="buy_base_price">Price</label>
                                    <input type="text" id="buy_base_price" name="buy_base_price" class="form-control"
                                           oninput="changedBuyBasePrice(<?= $this->quoteDecimals() ?>)" data-max-price="<?= $this->maxBuyPrice() ?>"
                                           <?php if($this->disablebBuyInputs()) { ?>disabled<?php } ?>
                                           value="<?=$this->buy_base_price ?>"/>
                                </div>
                                <div id="base_amount_div" class="col-6 col-sm-12  mb-2">
                                    <label class="form-label" for="buy_base_amount"><?=$this->base ?> amount</label>
                                    <input type="text" id="buy_base_amount" name="buy_base_amount" class="form-control"
                                           oninput="changedBuyBasePrice(<?= $this->quoteDecimals() ?>)"
                                           <?php if($this->disablebBuyInputs()) { ?>disabled<?php } ?>
                                           value="<?=$this->buy_base_amount ?>"/>
                                </div>
                                <div id="quote_total_div" class="col-6 col-sm-12  mb-2">
                                    <label class="form-label" for="buy_quote_total"><?=$this->quote ?> Total</label>
                                    <input type="text" id="buy_quote_total" name="buy_quote_total" class="form-control"
                                           oninput="changedBuyQuoteTotal()"
                                           <?php if($this->disablebBuyInputs()) { ?>disabled<?php } ?>
                                           value="<?=$this->buy_quote_total ?>"/>
                                </div>
                                <div class="col-12 mb-2">
                                    <label class="form-label" for="base_receive_address"><?=$this->base ?> Receiving address</label>
                                    <input type="text" id="base_receive_address" name="base_receive_address" class="form-control"
                                           value="<?=$this->base_receive_address ?>"/>
                                </div>
                            </div>
                        </div>
                        <?php if($loggedIn) { ?>
                            <?php if($this->sell_offer['type']==OfferService::TYPE_SELL) { ?>
                                <button type="button" class="btn btn-success" onclick="acceptSellOffer(event,<?=$this->sell_offer['id']?>)">Buy <?=$this->base ?></button>
                            <?php } else { ?>
                                <button type="button" class="btn btn-success" onclick="createBuyOffer(event)">Buy <?=$this->base ?></button>
                            <?php } ?>
                        <?php } else { ?>
                            <button type="button" class="btn btn-secondary" disabled>Login to trade</button>
                        <?php } ?>
                        <button type="button" class="btn btn-secondary close-btn d-none" onclick="closeBuyCard()">Close</button>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="card p-2" id="sell-card">
                        <div class="card-body p-0">
                            <div class="row">
                                <div id="base_price_div" class="col-6 col-sm-12 mb-2">
                                    <label  class="form-label" for="sell_base_price">Price</label>
                                    <input type="text" id="sell_base_price" name="sell_base_price" class="form-control"
                                           oninput="changedSellBasePrice(<?=$this->baseDecimals()?>)" data-min-price="<?= $this->minSellPrice() ?>"
                                           <?php if($this->disablebSellInputs()) { ?>disabled<?php } ?>
                                           value="<?=$this->sell_base_price ?>"/>
                                </div>
                                <div id="base_amount_div" class="col-6 col-sm-12 mb-2">
                                    <label class="form-label" for="sell_base_amount"><?=$this->base ?> amount</label>
                                    <input type="text" id="sell_base_amount" name="sell_base_amount" class="form-control"
                                           oninput="changedSellBasePrice(<?=$this->baseDecimals()?>)"
                                           <?php if($this->disablebSellInputs()) { ?>disabled<?php } ?>
                                           value="<?=$this->sell_base_amount ?>"/>
                                </div>
                                <div id="quote_total_div" class="col-6 col-sm-12 mb-2">
                                    <label class="form-label" for="sell_quote_total"><?=$this->quote ?> Total</label>
                                    <input type="text" id="sell_quote_total" name="sell_quote_total" class="form-control"
                                           oninput="changedSellQuoteTotal()"
                                           <?php if($this->disablebSellInputs()) { ?>disabled<?php } ?>
                                           value="<?=$this->sell_quote_total ?>"/>
                                </div>
                                <div class="col-12 mb-2">
                                    <label class="form-label" for="quote_receive_address"><?=$this->quote ?> Receiving address</label>
                                    <input type="text" id="quote_receive_address" name="quote_receive_address" class="form-control"
                                           value="<?=$this->quote_receive_address ?>"/>
                                </div>
                            </div>
                        </div>
                        <?php if($loggedIn) { ?>
                            <?php if($this->sell_offer['type']==OfferService::TYPE_BUY) { ?>
                                <button type="button" class="btn btn-danger" onclick="acceptBuyOffer(event, <?=$this->sell_offer['id']?>)">Sell <?=$this->base ?></button>
                            <?php } else { ?>
                                <button type="button" class="btn btn-danger" onclick="createSellOffer(event)">Sell <?=$this->base ?></button>
                            <?php } ?>
                        <?php } else { ?>
                            <button type="button" class="btn btn-secondary" disabled>Login to trade</button>
                        <?php } ?>
                        <button type="button" class="btn btn-secondary close-btn d-none" onclick="closeSellCard()">Close</button>
                    </div>
                </div>
            </div>
            <?php
        });

        ?>
    </div>

    <div class="col-sm-3 d-flex flex-column right-col">
        <div class="card" id="markets-card">
            <?php
            $lastPrice = $tradeHistory[0]['base_price'];
            $markets = OfferService::getMarkets();
            ?>
            <div class="table-responsive">
                <table class="table table-sm font-size-13">
                    <thead>
                        <tr>
                            <th>Pair</th>
                            <th class="text-end">Last price</th>
                            <th class="text-end">Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($markets as $market) { ?>
                            <tr>
                                <td>
                                    <a href="/apps/p2p/?market=<?=$market['market_name'] ?>">
                                        <?=$market['market_name'] ?>
                                    </a>
                                </td>
                                <td class="text-end"><?=$market['last_price'] ?></td>
                                <td class="text-end"><?=$market['price_change'] ?>%</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card" style="flex-grow:1; min-height: 0" id="trades-card">
            <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link active" data-bs-toggle="tab" href="#market-trades" role="tab" aria-selected="false" tabindex="-1">
                        <span>Market trades</span>
                    </a>
                </li>
                <?php if($loggedIn) { ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" data-bs-toggle="tab" href="#my-trades" role="tab" aria-selected="false" tabindex="-1">
                            <span>My trades</span>
                        </a>
                    </li>
                <?php } ?>
            </ul>
            <div class="tab-content d-flex" style="min-height: 0">
                <div class="tab-pane active" id="market-trades" role="tabpanel">
                    <div class="table-responsive" style="">
                        <table class="table table-sm font-size-13" >
                            <thead>
                            <tr>
                                <th>Price (<?=$this->base ?>)</th>
                                <th class="text-end">Amount (<?= $this->quote ?>)</th>
                                <th class="text-end">Time</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($tradeHistory as $trade) {
                                ?>
                                <tr class="<?php if ($trade['type']==OfferService::TYPE_SELL) { ?>text-success<?php } else { ?>text-danger<?php } ?>">
                                    <td><?= $trade['base_price'] ?></td>
                                    <td class="text-end"><?= num($trade['base_amount']*$trade['base_price'], $this->quoteDecimals()) ?></td>
                                    <td class="text-end"><?= date('H:i:s',strtotime($trade['closed_at'])) ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if($loggedIn) { ?>
                <div class="tab-pane" id="my-trades" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-sm font-size-13" >
                            <thead>
                            <tr>
                                <th>Price (<?=$this->base ?>)</th>
                                <th class="text-end">Amount (<?= $this->quote ?>)</th>
                                <th class="text-end">Time</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($myCompletedOffers as $trade) {
                                ?>
                                <tr class="<?php if ($trade['type']==OfferService::TYPE_SELL) { ?>text-success<?php } else { ?>text-danger<?php } ?>">
                                    <td><?= $trade['base_price'] ?></td>
                                    <td class="text-end"><?= num($trade['base_amount']*$trade['base_price'], $this->quoteDecimals()) ?></td>
                                    <td class="text-end"><?= date('H:i:s',strtotime($trade['closed_at'])) ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>


    <div class="row">
        <?php if ($loggedIn) { ?>
        <div class="col-12">
            <?php Pajax::block('my-offers', function () use ($loggedIn) {
                $myOpenOffers = OfferService::getMyOpenOffers($this->market_id);
                $myOffersHistory = OfferService::getMyOffersHistory($this->market_id);
                $userAddresses = OfferService::getUserAddresses(OfferService::userAddress());
                ?>
                <div class="card" id="my-offers-card">
                    <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" data-bs-toggle="tab" href="#open-offers" role="tab" aria-selected="false" tabindex="-1">
                                <span>Open offers</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#offers-history" role="tab" aria-selected="false" tabindex="-1">
                                <span>Offers history</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#addresses" role="tab" aria-selected="false" tabindex="-1">
                                <span>Addresses</span>
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content" style="height: 30vh; overflow: auto">
                        <div class="tab-pane active" id="open-offers" role="tabpanel">
                            <?php if ($loggedIn) { ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                        <tr>
                                            <th></th>
                                            <th>ID</th>
                                            <th>Date created</th>
                                            <th>Type</th>
                                            <th>Price</th>
                                            <th>Amount</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($myOpenOffers as $offer) {
                                            $quote_total = $offer['base_price'] * $offer['base_amount'];
                                            ?>
                                            <tr class="<?php if ($offer['type']==OfferService::TYPE_SELL) { ?>text-danger<?php } else { ?>text-success<?php } ?>">
                                                <td>
                                                    <?php if($this->canCancelOffer($offer)) { ?>
                                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                                                onclick="if(!confirm('Are you sure you want to cancel this offer?')) return false; cancelOffer(event, <?= $offer['id'] ?>)">Cancel</button>
                                                    <?php } ?>
                                                    <?php if($this->canAcceptOffer($offer)) { ?>
                                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#offer-modal"
                                                                onclick="paction(event,'openOffer',<?=$offer['id'] ?>, {update: '#offer-modal .modal-content', beforeSend: showLoading})">Accept offer</button>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <a href="" data-bs-toggle="modal" data-bs-target="#offer-modal"
                                                       onclick="paction(event,'openOffer',<?=$offer['id'] ?>, {update: '#offer-modal .modal-content',beforeSend: showLoading}); return false;"><?=$offer['id'] ?></a>
                                                </td>
                                                <td><?= $offer['created_at'] ?></td>
                                                <td><?= $offer['type'] ?></td>
                                                <td><?= $offer['base_price'] ?></td>
                                                <td><?= $offer['base_amount'] ?></td>
                                                <td><?= $quote_total ?></td>
                                                <td><?= $offer['status'] ?></td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="tab-pane" id="offers-history" role="tabpanel">
                            <?php if ($loggedIn) { ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                        <tr>
                                            <th></th>
                                            <th>ID</th>
                                            <th>Date created</th>
                                            <th>Type</th>
                                            <th>Price</th>
                                            <th>Amount</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($myOffersHistory as $offer) {
                                            $quote_total = $offer['base_price'] * $offer['base_amount'];
                                            ?>
                                            <tr data-offer-id="<?= $offer['id'] ?>" class="<?php if ($offer['type']==OfferService::TYPE_SELL) { ?>text-danger<?php } else { ?>text-success<?php } ?>">
                                                <td>
                                                    <?php if($this->canCancelOffer($offer)) { ?>
                                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                                                onclick="if(!confirm('Are you sure you want to cancel this offer?')) return false; cancelOffer(event, <?= $offer['id'] ?>)">Cancel</button>
                                                    <?php } ?>
                                                    <?php if($this->canAcceptOffer($offer)) { ?>
                                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#offer-modal"
                                                                onclick="paction(event,'openOffer',<?=$offer['id'] ?>, {update: '#offer-modal .modal-content',beforeSend: showLoading})">Accept offer</button>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <a href="" data-bs-toggle="modal" data-bs-target="#offer-modal"
                                                       onclick="paction(event,'openOffer',<?=$offer['id'] ?>, {update: '#offer-modal .modal-content',beforeSend: showLoading}); return false;"><?=$offer['id'] ?></a>
                                                </td>
                                                <td><?= $offer['created_at'] ?></td>
                                                <td><?= $offer['type'] ?></td>
                                                <td><?= $offer['base_price'] ?></td>
                                                <td><?= $offer['base_amount'] ?></td>
                                                <td><?= $quote_total ?></td>
                                                <td><?= $offer['status'] ?></td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="tab-pane" id="addresses" role="tabpanel">
                            <?php if ($loggedIn) { ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Symbol</th>
                                                <th>Address</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($userAddresses as $address) { ?>
                                            <tr>
                                                <td><?= $address['name'] ?></td>
                                                <td><?= $address['symbol'] ?></td>
                                                <td><?= $address['address'] ?></td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php
            } ) ?>
        </div>
        <?php } ?>
    </div>


    <div class="modal fade" id="offer-modal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Offer details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <?php  if ($this->offer_id) {
                    $offer = OfferService::getOffer($this->offer_id);
                    if($offer['user_created']==OfferService::userAddress() || $offer['user_accepted']==OfferService::userAddress()) {
                    $quote_total = $offer['base_price'] * $offer['base_amount'];
                    ?>
                    <div class="modal-body">

                        <div>

                            <?php $this->offerSummary($offer) ?>
                            <?php $this->offerDetails($offer) ?>

                            <?php

                            switch ($offer['status']) {
                                case OfferService::STATUS_CREATED:
                                    $this->createdViewTemplate($offer); break;
                                case OfferService::STATUS_EXPIRED:
                                    $this->expiredViewTemplate($offer); break;
                                case OfferService::STATUS_CANCELED:
                                    $this->canceledViewTemplate($offer);break;
                                case OfferService::STATUS_DEPOSITING:
                                    $this->depositingViewTemplate($offer);break;
                                case OfferService::STATUS_OPEN:
                                    $this->openViewTemplate($offer);break;
                                case OfferService::STATUS_ACCEPTED:
                                    $this->acceptedViewTemplate($offer);break;
                                case OfferService::STATUS_TRANSFERRING:
                                    $this->transferringViewTemplate($offer);break;
                                case OfferService::STATUS_TRANSFERRED:
                                    $this->transferredViewTemplate($offer);break;
                                case OfferService::STATUS_PAYING:
                                    $this->payingViewTemplate($offer);break;
                                case OfferService::STATUS_CLOSED:
                                    $this->closedViewTemplate($offer);break;
                            } ?>

                        </div>
                    </div>
                    <div class="modal-footer">
                        <?php if($this->canCancelOffer($offer)) { ?>
                            <button type="button" class="btn btn btn-danger" aria-label="Cancel offer" data-bs-dismiss="modal"
                                    onclick="if(!confirm('Are you sure you want to cancel this offer?')) return false;
                                            cancelOffer(event, <?= $offer['id'] ?>)">
                                Cancel offer</button>
                        <?php } ?>
                        <?php if($this->canAcceptOffer($offer)) { ?>
                            <button type="button" class="btn btn btn-success" aria-label="Accept offer"
                                    onclick="if(!confirm('Are you sure you want to accept this offer?')) return false;
                                                paction(event, 'acceptOffer', {}, {update: ['#offer-modal .modal-content','#offers-table','#my-offers']})">
                                Accept offer</button>
                        <?php } ?>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    </div>
                <?php }
                }
                ?>
            </div>
        </div>
    </div>

    <div class="wait-process-div">
        <div class="wait-process-div-inner">
            <h3>Please wait for a process to complete ...</h3>
            <h5>
                Refresh or
                <a href="" onclick="paction(event, 'logout')" class="text-decoration-underline">Logout</a> if a process takes a long time
            </h5>
        </div>
    </div>

<script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function () {
        displayChart();
    });
    if(window.displayChart) displayChart();
</script>