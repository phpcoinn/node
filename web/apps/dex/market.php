<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once ROOT. '/web/apps/explorer/include/functions.php';
define("PAGE", true);
define("APP_NAME", "Dex");
define("HEAD_CSS", ["/apps/dex/dex.css?v=" . filemtime(ROOT . "/web/apps/dex/dex.css")]);

if (!Dex::isEnabled()) {
    header("location: /apps/explorer");
    exit;
}

$info = Dex::getInfo();
$multi = !empty($info['multi']);
$listed = $info['listed'] ?? [];
$requested = (string)($_GET['token'] ?? '');
$selectedToken = '';
$selectedMeta = null;

if ($multi) {
    foreach ($listed as $meta) {
        if ($requested !== '' && $meta['token'] === $requested) {
            $selectedToken = $meta['token'];
            $selectedMeta = $meta;
            break;
        }
    }
    if ($selectedToken === '') {
        header("location: /apps/dex/");
        exit;
    }
} else {
    $selectedToken = $info['address'] ?: '';
    $selectedMeta = [
        'token' => $selectedToken,
        'name' => $info['name'] ?: 'DEX token',
        'symbol' => $info['symbol'] ?: 'TOKEN',
        'decimals' => (string)($info['decimals'] ?: 8),
    ];
}

$view = Dex::getMarketView($selectedToken);
$scExecFee = Blockchain::getSmartContractExecFee();
$symbol = $selectedMeta['symbol'] ?? 'TOKEN';
$decimals = intval($selectedMeta['decimals'] ?? 8);
$ticker = $view['ticker'] ?? [];

require_once dirname(__DIR__). '/common/include/top.php';

$loggedIn = false;
$address = null;
$phpBalance = "0";
$tokenBalance = "0";
if (isset($_SESSION['account'])) {
    $loggedIn = true;
    $address = $_SESSION['account']['address'];
    $phpBalance = Account::getBalance($address);
    if ($info['deployed'] && !empty($info['address'])) {
        if ($multi && $selectedToken !== '') {
            $tokenState = SmartContract::getState($selectedToken) ?: [];
            $rawBal = $tokenState['balances'][$address] ?? "0";
            $tokenBalance = Dex::tokenToDisplay($rawBal, $decimals);
        } else {
            $state = SmartContract::getState($info['address']) ?: [];
            $rawBal = $state['balances'][$address] ?? "0";
            $tokenBalance = Dex::tokenToDisplay($rawBal, $decimals);
        }
    }
}

$canTrade = $loggedIn && !empty($info['address']) && $info['deployed'] && $info['verified'] && empty($selectedMeta['pending']);
$marketTitle = h($symbol) . ' / PHP';
?>

<div class="dex-hero">
    <div>
        <a href="/apps/dex/" class="text-muted font-size-13">← Markets</a>
        <h3 class="mb-0 mt-1"><?php echo $marketTitle ?> <span class="text-muted font-size-16">spot</span></h3>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="/apps/dex/nodes.php">DEX nodes</a>
</div>

<div class="dex-terminal">
    <div class="dex-ticker">
        <div class="dex-ticker-pair">
            <?php echo $marketTitle ?>
            <small><?php echo h($selectedMeta['name'] ?: $symbol) ?></small>
        </div>
        <div class="dex-stat">
            <div class="lbl">Last price</div>
            <div class="val" id="dex-last"><?php echo h($ticker['last_display'] ?? '—') ?></div>
        </div>
        <div class="dex-stat">
            <div class="lbl">Change</div>
            <div class="val" id="dex-chg">—</div>
        </div>
        <div class="dex-stat">
            <div class="lbl">Bid</div>
            <div class="val dex-up" id="dex-bid"><?php echo h($ticker['best_bid_display'] ?? '—') ?></div>
        </div>
        <div class="dex-stat">
            <div class="lbl">Ask</div>
            <div class="val dex-down" id="dex-ask"><?php echo h($ticker['best_ask_display'] ?? '—') ?></div>
        </div>
        <div class="dex-stat">
            <div class="lbl">Spread</div>
            <div class="val" id="dex-spread"><?php echo h($ticker['spread_display'] ?? '—') ?></div>
        </div>
        <div class="dex-stat">
            <div class="lbl">Book</div>
            <div class="val" id="dex-vol"><?php echo h(($ticker['book_php'] ?? '0') . ' PHP') ?></div>
        </div>
        <div class="dex-live"><span class="dot"></span><span id="dex-updated">LIVE</span></div>
    </div>

    <div class="dex-grid">
        <div class="dex-panel dex-chart">
            <div class="dex-chart-tabs">
                <button type="button" class="active" data-dex-chart="price">Price</button>
                <button type="button" data-dex-chart="depth">Depth</button>
            </div>
            <div class="dex-chart-stage">
                <canvas id="dex-price-chart"></canvas>
                <canvas id="dex-depth-chart"></canvas>
            </div>
        </div>

        <div class="dex-panel dex-book">
            <h5>Order book</h5>
            <div class="dex-book-head"><div>Price (PHP)</div><div><?php echo h($symbol) ?></div><div>Total</div></div>
            <div class="dex-book-body dex-book-bids" id="dex-bids"><div class="dex-empty">Loading bids…</div></div>
            <div class="dex-spread">
                <div class="px" id="dex-mid-px"><?php echo h($ticker['last_display'] ?? '—') ?></div>
                <div class="sp" id="dex-mid-sp">Spread —</div>
            </div>
            <div class="dex-book-body dex-book-asks" id="dex-asks"><div class="dex-empty">Loading asks…</div></div>
        </div>

        <div class="dex-panel dex-ticket" id="dex-app">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active buy" data-bs-toggle="tab" data-bs-target="#dex-buy-tab" type="button">Buy</button></li>
                <li class="nav-item"><button class="nav-link sell" data-bs-toggle="tab" data-bs-target="#dex-sell-tab" type="button">Sell</button></li>
            </ul>
            <div class="tab-content">
                <?php if (!$canTrade) { ?>
                    <div class="dex-empty">
                        <?php if (!$loggedIn) { ?>
                            Login to trade this pair.
                            <div class="mt-3">
                                <a class="btn btn-warning btn-sm" href="/apps/common/login.php?app=Dex&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']) ?>">Login</a>
                            </div>
                        <?php } else { ?>
                            Trading is not available on this market yet.
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="tab-pane fade show active" id="dex-buy-tab">
                        <p class="font-size-12 text-muted mb-2">Lock PHP. A seller fills by delivering <?php echo h($symbol) ?>. Fractions work; this pair uses <?php echo (int)$decimals ?> decimals (minimum 0.<?php echo str_pad('1', (int)$decimals, '0', STR_PAD_LEFT) ?>).</p>
                        <div class="mb-2">
                            <label class="form-label"><?php echo h($symbol) ?> wanted</label>
                            <input class="form-control" type="text" inputmode="decimal" v-model="buyToken" placeholder="0.1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">PHP to lock</label>
                            <input class="form-control" type="text" inputmode="decimal" v-model="buyPhp" placeholder="0.1">
                        </div>
                        <button class="dex-btn-up" type="button" @click="postBuy">Buy <?php echo h($symbol) ?></button>
                    </div>
                    <div class="tab-pane fade" id="dex-sell-tab">
                        <p class="font-size-12 text-muted mb-2"><?php if ($multi) { ?>Approve runs in the background. Your order stays in the table below until it is open on the book.<?php } else { ?>Lock tokens in a sell offer.<?php } ?> Buyer pays PHP on fill. Same <?php echo (int)$decimals ?>-decimal amounts as buy.</p>
                        <div class="mb-2">
                            <label class="form-label"><?php echo h($symbol) ?> amount</label>
                            <input class="form-control" type="text" inputmode="decimal" v-model="sellToken" placeholder="0.1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">PHP wanted</label>
                            <input class="form-control" type="text" inputmode="decimal" v-model="sellPhp" placeholder="0.1">
                        </div>
                        <button class="dex-btn-down" type="button" @click="postSell">Sell <?php echo h($symbol) ?></button>
                    </div>
                    <div class="font-size-12 text-muted mt-2">Fee <?php echo h(num($scExecFee)) ?> PHP · Available <?php echo h($phpBalance) ?> PHP / <?php echo h($tokenBalance) ?> <?php echo h($symbol) ?></div>
                <?php } ?>
            </div>
        </div>

        <div class="dex-panel dex-tape">
            <h5>Open liquidity</h5>
            <div class="dex-tape-row dex-tape-head">
                <div>Side</div><div>Price</div><div><?php echo h($symbol) ?></div><div>PHP</div>
            </div>
            <div id="dex-tape-body" class="dex-book-body"><div class="dex-empty">Loading…</div></div>
        </div>

        <div class="dex-panel dex-wallet">
            <h5>Wallet</h5>
            <div class="p-3 font-size-13">
                <?php if ($loggedIn) { ?>
                    <div class="text-muted mb-1">Account</div>
                    <div class="mb-3"><?php echo explorer_address_link($address, true) ?></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">PHP</span><span><?php echo h($phpBalance) ?></span></div>
                    <div class="d-flex justify-content-between"><span class="text-muted"><?php echo h($symbol) ?></span><span><?php echo h($tokenBalance) ?></span></div>
                    <div class="mt-3 font-size-12 text-muted">Token <?php echo explorer_address_link($selectedToken, true) ?></div>
                <?php } else { ?>
                    <div class="dex-empty">Login to see balances and place orders.</div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php if ($loggedIn) { ?>
<div class="dex-terminal dex-pending" id="dex-orders">
    <div class="dex-ticker">
        <div class="dex-ticker-pair">
            Your orders
            <small>Approve-wait, mempool, and open offers for this pair</small>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm dex-markets-table mb-0">
            <thead>
            <tr>
                <th>Side</th>
                <th>Price</th>
                <th><?php echo h($symbol) ?></th>
                <th>PHP</th>
                <th>Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody id="dex-orders-body">
                <tr><td colspan="6" class="text-muted text-center py-3">No open orders yet. Sells and bids you place show up here.</td></tr>
            </tbody>
        </table>
    </div>
</div>
<?php } ?>

<p class="text-muted font-size-13">
    On-chain spot book for PHPCoin ERC-20s versus PHP. The chart tracks live mid price in this browser; depth is the current bid/ask wall.
</p>

<script>
    window.DEX_MARKET = <?php echo json_encode([
        'token' => $selectedToken,
        'symbol' => $symbol,
        'canTrade' => $canTrade,
        'address' => $address,
        'multi' => $multi,
        'scAddress' => $info['address'],
        'tokenBalance' => $tokenBalance,
        'chainId' => CHAIN_ID,
    ]) ?>;
    window.DEX_PUBLIC_KEY = <?php echo json_encode($loggedIn ? $_SESSION['account']['public_key'] : null) ?>;
</script>
<script src="/apps/dex/terminal.js?v=<?php echo filemtime(ROOT . '/web/apps/dex/terminal.js') ?>"></script>
<?php if ($canTrade) { ?>
<script src="/apps/common/js/phpcoin-crypto.browser.js" type="text/javascript"></script>
<script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<script src="/apps/explorer/tokens/tokens.js" type="text/javascript"></script>
<script src="/apps/dex/trade.js?v=<?php echo filemtime(ROOT . '/web/apps/dex/trade.js') ?>"></script>
<script type="text/javascript">
    const dexMulti = <?php echo $multi ? 'true' : 'false' ?>;
    const selectedToken = <?php echo json_encode($selectedToken) ?>;

    const { createApp } = Vue;
    createApp({
        data() {
            return { sellToken: '', sellPhp: '', buyToken: '', buyPhp: '' };
        },
        methods: {
            postSell() {
                const tokenAmt = window.dexNormalizeAmount ? window.dexNormalizeAmount(this.sellToken) : String(this.sellToken || '').trim();
                const phpAmt = window.dexNormalizeAmount ? window.dexNormalizeAmount(this.sellPhp) : String(this.sellPhp || '').trim();
                if (!tokenAmt || !phpAmt) {
                    Swal.fire({
                        title: 'Invalid amount',
                        text: 'Use amounts greater than 0. Fractions are allowed down to 8 decimals (0.1, 0.01, 0.00000001).',
                        icon: 'error'
                    });
                    return;
                }
                const waitHint = dexMulti
                    ? 'Approve, if needed, runs in the background. Your order stays in Your orders at the bottom of the page.'
                    : 'Lock tokens and list this sell offer?';
                confirmMsg('Post ask', waitHint, () => {
                    window.dexPostSell(tokenAmt, phpAmt);
                });
            },
            postBuy() {
                const tokenAmt = window.dexNormalizeAmount ? window.dexNormalizeAmount(this.buyToken) : String(this.buyToken || '').trim();
                const phpAmt = window.dexNormalizeAmount ? window.dexNormalizeAmount(this.buyPhp) : String(this.buyPhp || '').trim();
                if (!tokenAmt || !phpAmt) {
                    Swal.fire({
                        title: 'Invalid amount',
                        text: 'Use amounts greater than 0. Fractions are allowed down to 8 decimals (0.1, 0.01, 0.00000001).',
                        icon: 'error'
                    });
                    return;
                }
                confirmMsg('Post bid', 'Lock ' + phpAmt + ' PHP with this buy offer?', () => {
                    if (typeof window.dexPostBuy === 'function') {
                        window.dexPostBuy(tokenAmt, phpAmt);
                    } else if (dexMulti) {
                        window.dexExec('postBuy', [selectedToken, tokenAmt], phpAmt);
                    } else {
                        window.dexExec('postBuy', [tokenAmt], phpAmt);
                    }
                });
            }
        }
    }).mount('#dex-app');
</script>
<?php } ?>

<?php
require_once dirname(__DIR__). '/common/include/bottom.php';
