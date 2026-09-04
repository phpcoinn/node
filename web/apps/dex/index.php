<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once ROOT. '/web/apps/explorer/include/functions.php';
define("PAGE", true);
define("APP_NAME", "Dex");

if (!Dex::isEnabled()) {
    header("location: /apps/explorer");
    exit;
}

$info = Dex::getInfo();
$offers = $info['deployed'] ? Dex::getOffers($info['address']) : [];
$scExecFee = Blockchain::getSmartContractExecFee();
$symbol = $info['symbol'] ?: 'TOKEN';
$decimals = intval($info['decimals'] ?: 8);

$loggedIn = false;
$address = null;
$phpBalance = "0";
$tokenBalance = "0";
if (isset($_SESSION['account'])) {
    $loggedIn = true;
    $address = $_SESSION['account']['address'];
    $phpBalance = Account::getBalance($address);
    if ($info['deployed'] && !empty($info['address'])) {
        $state = SmartContract::getState($info['address']) ?: [];
        $rawBal = $state['balances'][$address] ?? "0";
        $tokenBalance = Dex::tokenToDisplay($rawBal, $decimals);
    }
}

$sellOffers = array_values(array_filter($offers, function ($o) { return ($o['side'] ?? '') === 'sell'; }));
$buyOffers = array_values(array_filter($offers, function ($o) { return ($o['side'] ?? '') === 'buy'; }));
$myOffers = [];
if ($loggedIn) {
    $myOffers = array_values(array_filter($offers, function ($o) use ($address) {
        return ($o['maker'] ?? '') === $address;
    }));
}

$canTrade = $loggedIn && !empty($info['address']) && $info['deployed'] && $info['verified'];

require_once dirname(__DIR__). '/common/include/top.php';
?>

<ol class="breadcrumb m-0 ps-0 h4">
    <li class="breadcrumb-item active">DEX</li>
</ol>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h3 class="mb-0">DEX</h3>
    <?php if ($info['verified']) { ?>
        <span class="badge rounded-pill bg-success">Live</span>
    <?php } elseif ($info['deployed']) { ?>
        <span class="badge rounded-pill bg-danger">Unavailable</span>
    <?php } ?>
    <a class="ms-auto btn btn-outline-primary btn-sm" href="/apps/dex/nodes.php">DEX nodes</a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="text-muted">Market</div>
                        <div class="h4 mb-1"><?php echo h($info['name'] ?: 'Market not live') ?> <?php if ($info['symbol']) { ?><span class="text-muted">(<?php echo h($info['symbol']) ?>)</span><?php } ?></div>
                        <div class="font-size-13">
                            Contract:
                            <?php if ($info['address']) { ?>
                                <?php echo explorer_address_link($info['address']) ?>
                            <?php } else { ?>
                                <span class="text-muted">Not deployed</span>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-6">
                                <div class="text-muted">Token supply</div>
                                <div><?php echo h($info['totalSupply'] ?? '-') ?></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted">PHP in contract</div>
                                <div><?php echo h($info['phpBalance']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <?php if ($loggedIn) { ?>
                    <div class="text-muted">Your wallet</div>
                    <div><?php echo explorer_address_link($address, true) ?></div>
                    <div class="row mt-2">
                        <div class="col-6">
                            <div class="text-muted">PHP</div>
                            <div><?php echo h($phpBalance) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted"><?php echo h($symbol) ?></div>
                            <div><?php echo h($tokenBalance) ?></div>
                        </div>
                    </div>
                    <div class="mt-2 font-size-13 text-muted">Exec fee: <?php echo h(num($scExecFee)) ?> PHP</div>
                <?php } else { ?>
                    <div class="alert alert-info mb-0 d-flex align-items-center">
                        Login to trade
                        <a class="btn btn-info ms-auto" href="/dapps.php?url=<?php echo GATEWAY ?>/wallet?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']) ?>">Login</a>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php if (!$info['deployed']) { ?>
    <div class="alert alert-warning">
        The DEX market is not live on this network yet. You can still open this page on any node; trading will start once the market is deployed.
    </div>
<?php } elseif (!$info['verified']) { ?>
    <div class="alert alert-danger">
        This market is not available for trading on this DEX.
    </div>
<?php } ?>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h4>Sell <?php echo h($symbol) ?></h4>
                <div class="table-responsive">
                    <table class="table table-sm table-striped dataTable">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th><?php echo h($symbol) ?></th>
                            <th>PHP</th>
                            <th>Maker</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (count($sellOffers) === 0) { ?>
                            <tr><td colspan="5" class="text-muted">No sell offers</td></tr>
                        <?php } ?>
                        <?php foreach ($sellOffers as $offer) { ?>
                            <tr>
                                <td><?php echo h($offer['id']) ?></td>
                                <td><?php echo h($offer['token_display']) ?></td>
                                <td><?php echo h($offer['php_display']) ?></td>
                                <td><?php echo explorer_address_link($offer['maker'], true) ?></td>
                                <td class="text-end">
                                    <?php if ($canTrade && $offer['maker'] !== $address) { ?>
                                        <button class="btn btn-primary btn-sm" type="button"
                                                onclick="dexFillSell('<?php echo h($offer['id']) ?>','<?php echo h($offer['php_display']) ?>')">Fill</button>
                                    <?php } elseif ($canTrade) { ?>
                                        <button class="btn btn-outline-danger btn-sm" type="button"
                                                onclick="dexCancel('cancelSell','<?php echo h($offer['id']) ?>')">Cancel</button>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h4>Buy <?php echo h($symbol) ?></h4>
                <div class="table-responsive">
                    <table class="table table-sm table-striped dataTable">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th><?php echo h($symbol) ?></th>
                            <th>PHP</th>
                            <th>Maker</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (count($buyOffers) === 0) { ?>
                            <tr><td colspan="5" class="text-muted">No buy offers</td></tr>
                        <?php } ?>
                        <?php foreach ($buyOffers as $offer) { ?>
                            <tr>
                                <td><?php echo h($offer['id']) ?></td>
                                <td><?php echo h($offer['token_display']) ?></td>
                                <td><?php echo h($offer['php_display']) ?></td>
                                <td><?php echo explorer_address_link($offer['maker'], true) ?></td>
                                <td class="text-end">
                                    <?php if ($canTrade && $offer['maker'] !== $address) { ?>
                                        <button class="btn btn-success btn-sm" type="button"
                                                onclick="dexFillBuy('<?php echo h($offer['id']) ?>')">Fill</button>
                                    <?php } elseif ($canTrade) { ?>
                                        <button class="btn btn-outline-danger btn-sm" type="button"
                                                onclick="dexCancel('cancelBuy','<?php echo h($offer['id']) ?>')">Cancel</button>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canTrade) { ?>
<div class="row" id="dex-app">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h4>Post sell</h4>
                <p class="text-muted font-size-13">List <?php echo h($symbol) ?> for sale. The buyer pays PHP when they fill the offer.</p>
                <div class="mb-3">
                    <label class="form-label"><?php echo h($symbol) ?> amount</label>
                    <input class="form-control" type="text" v-model="sellToken">
                </div>
                <div class="mb-3">
                    <label class="form-label">PHP wanted</label>
                    <input class="form-control" type="text" v-model="sellPhp">
                </div>
                <button class="btn btn-primary" type="button" @click="postSell">Post sell</button>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h4>Post buy</h4>
                <p class="text-muted font-size-13">Lock PHP in a buy offer. A seller fills it by sending <?php echo h($symbol) ?>.</p>
                <div class="mb-3">
                    <label class="form-label"><?php echo h($symbol) ?> wanted</label>
                    <input class="form-control" type="text" v-model="buyToken">
                </div>
                <div class="mb-3">
                    <label class="form-label">PHP to lock</label>
                    <input class="form-control" type="text" v-model="buyPhp">
                </div>
                <button class="btn btn-success" type="button" @click="postBuy">Post buy</button>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h4>Send <?php echo h($symbol) ?></h4>
                <div class="mb-3">
                    <label class="form-label">Receiver</label>
                    <input class="form-control" type="text" v-model="transferTo">
                </div>
                <div class="mb-3">
                    <label class="form-label">Amount</label>
                    <input class="form-control" type="text" v-model="transferAmount">
                </div>
                <button class="btn btn-outline-primary" type="button" @click="transferToken">Send token</button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php if ($loggedIn && count($myOffers) > 0) { ?>
    <div class="card">
        <div class="card-body">
            <h4>Your open offers</h4>
            <div class="table-responsive">
                <table class="table table-sm table-striped dataTable">
                    <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Side</th>
                        <th><?php echo h($symbol) ?></th>
                        <th>PHP</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($myOffers as $offer) { ?>
                        <tr>
                            <td><?php echo h($offer['id']) ?></td>
                            <td><?php echo h($offer['side']) ?></td>
                            <td><?php echo h($offer['token_display']) ?></td>
                            <td><?php echo h($offer['php_display']) ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php } ?>

<p class="text-muted font-size-13">
    Offers and trades are settled on the blockchain. This page is a local interface; the order book is the same on every DEX node.
</p>

<?php if ($canTrade) { ?>
<script src="/apps/common/js/phpcoin-crypto.browser.js" type="text/javascript"></script>
<script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<script src="/apps/explorer/tokens/tokens.js" type="text/javascript"></script>
<script type="text/javascript">
    const publicKey = <?php echo json_encode($_SESSION['account']['public_key']) ?>;
    const scAddress = <?php echo json_encode($info['address']) ?>;
    const chainId = <?php echo json_encode(CHAIN_ID) ?>;

    function dexExec(method, params, amount) {
        const data = {
            public_key: publicKey,
            sc_address: scAddress,
            amount: amount || 0,
            method: method,
            params: params || []
        };
        axios.post('/api.php?q=generateSmartContractExecTx', data).then(res => {
            if (res.data.status === 'ok') {
                enterPrivateKey(privateKey => {
                    if (!privateKey) return;
                    sendTransaction(res.data.data.tx, res.data.data.signature_base, privateKey);
                });
            } else {
                Swal.fire({ title: 'Error generating transaction', text: res.data.data || 'API error', icon: 'error' });
            }
        }).catch(() => {
            Swal.fire({ title: 'Error', text: 'Could not contact this node API', icon: 'error' });
        });
    }

    function dexFillSell(id, phpAmount) {
        confirmMsg('Fill sell offer', 'Send ' + phpAmount + ' PHP to fill offer #' + id + '?', () => {
            dexExec('fillSell', [id], phpAmount);
        });
    }
    function dexFillBuy(id) {
        confirmMsg('Fill buy offer', 'Deliver tokens to fill offer #' + id + '?', () => {
            dexExec('fillBuy', [id], 0);
        });
    }
    function dexCancel(method, id) {
        confirmMsg('Cancel offer', 'Cancel offer #' + id + '?', () => {
            dexExec(method, [id], 0);
        });
    }

    const { createApp } = Vue;
    createApp({
        data() {
            return {
                sellToken: '',
                sellPhp: '',
                buyToken: '',
                buyPhp: '',
                transferTo: '',
                transferAmount: ''
            };
        },
        methods: {
            postSell() {
                if (!this.sellToken || !this.sellPhp) {
                    Swal.fire({ title: 'Invalid amount', icon: 'error' });
                    return;
                }
                confirmMsg('Post sell', 'Lock tokens and list this sell offer?', () => {
                    dexExec('postSell', [this.sellToken, this.sellPhp], 0);
                });
            },
            postBuy() {
                if (!this.buyToken || !this.buyPhp) {
                    Swal.fire({ title: 'Invalid amount', icon: 'error' });
                    return;
                }
                confirmMsg('Post buy', 'Lock ' + this.buyPhp + ' PHP with this buy offer?', () => {
                    dexExec('postBuy', [this.buyToken], this.buyPhp);
                });
            },
            transferToken() {
                if (!this.transferTo || !phpcoinCrypto.verifyAddress(this.transferTo)) {
                    Swal.fire({ title: 'Invalid address', icon: 'error' });
                    return;
                }
                confirmMsg('Send token', 'Send tokens to this address?', () => {
                    dexExec('transferToken', [this.transferTo, this.transferAmount], 0);
                });
            }
        }
    }).mount('#dex-app');
</script>
<?php } ?>

<?php
require_once dirname(__DIR__). '/common/include/bottom.php';
