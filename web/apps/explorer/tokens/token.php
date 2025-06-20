<?php
require_once dirname(dirname(__DIR__))."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Explorer");
require_once ROOT. '/web/apps/explorer/include/functions.php';

if(!FEATURE_SMART_CONTRACTS) {
    header("location: /apps/explorer");
    exit;
}

require_once __DIR__. '/../../common/include/top.php';
require_once __DIR__. '/inc.php';

$id=$_GET['id'];

if(empty($id)) {
    header("location: /apps/explorer/tokens/list.php");
    exit;
}

$loggedIn = false;
if(isset($_SESSION['account'])) {
    $accountBalance = Account::getBalance($_SESSION['account']['address']);
    $loggedIn = true;
    $address = $_SESSION['account']['address'];
}

global $db;

if($loggedIn) {
    $sql="select * from token_balances where token = ? and address = ?";
    $row = $db->row($sql,[$id, $address], false);
    $balance = $row['balance'];
    $coinBalance = Account::getBalance($address);
}

$transfers_start = $_GET['transfers_start'] ?? 0;
$rpp = 10;

$sql="select * from token_txs tt where tt.token = ? order by tt.height desc limit $transfers_start, $rpp";
$transfers = $db->run($sql,[$id], false);

$sql="select * from token_mempool_txs mt where mt.token = ? order by mt.height desc";
$mempoolTransfers = $db->run($sql,[$id], false);

if($loggedIn) {

    $sql="select * from token_txs tt where tt.token = ? and (tt.src = ? or tt.dst = ?) order by tt.height desc";
    $myTransfers = $db->run($sql,[$id, $address, $address], false);

    $sql="select * from token_mempool_txs tt where tt.token = ? and (tt.src = ? or tt.dst = ?) order by tt.height desc";
    $myMempoolTransfers = $db->run($sql,[$id, $address, $address], false);
}

$sql="select * from tokens t where t.address = ?";
$token = $db->row($sql,[$id], false);

$metadata = json_decode($token['metadata'], true);

$sql="select * from transactions t where t.type = :type and t.dst = :dst limit 1";
$createTx = $db->row($sql,[":type"=>TX_TYPE_SC_CREATE,":dst"=>$id]);

$color = stringToHex($token['address']);
$indexes=[5,10,15,20,25,30];
$c= "";
foreach ($indexes as $index) {
    $c.=$color[$index];
}

$sql="select * from token_balances tb where tb.token = ? order by cast(replace(tb.balance,',','') as double) desc limit 10";
$topHolders = $db->run($sql,[$id], false);
$scExecFee = Blockchain::getSmartContractExecFee();

$mintable = false;
$interface = SmartContractEngine::getInterface($id);
$burnable = false;
foreach ($interface['methods'] as $method) {
    if($method['name'] === 'mint') {
        $mintable = true;
    }
    if($method['name'] === 'burn') {
        $burnable = true;
    }
}
$decimals = $metadata['decimals'];

$sql="select var_value from smart_contract_state
        where variable = 'totalSupply' and sc_address = ?
        order by height desc limit 1";
$row=$db->row($sql,[$id], false);
$totalSupply = $row['var_value'];
$totalSupply = $totalSupply / pow(10, $decimals);

?>


<ol class="breadcrumb m-0 ps-0 h4">
    <li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
    <li class="breadcrumb-item"><a href="/apps/explorer/tokens/list.php">Tokens</a></li>
    <li class="breadcrumb-item"><?php echo $token['name'] ?> (<?php echo $metadata['symbol'] ?>)</li>
</ol>

<div class="card">
    <div class="card-body d-flex p-2 flex-wrap">

        <div class="row">
            <div class="col d-flex align-items-center">
                <div class="me-2 align-items-center d-flex">
                    <?php if (!empty($metadata['image'])) { ?>
                        <img src="<?php echo $metadata['image'] ?>" style="width:64px; height: 64px;"/>
                    <?php } else {?>
                        <div class="token-image-placeholder" style="background-color: #<?php echo $c ?>; color: <?php echo getContrastingTextColor("#$c") ?>">
                            <?php echo substr($metadata['symbol'],0,3) ?>
                        </div>
                    <?php } ?>
                </div>
                <div>
                    <h4><?php echo $metadata['name'] ?></h4>
                    <h5><?php echo $metadata['symbol'] ?></h5>
                    <div><?php echo $metadata['description'] ?></div>
                </div>
            </div>
            <div class="col">
                <dl class="row">
                    <dt class="col-sm-3">Address:</dt>
                    <dd class="col-sm-9"><?php echo explorer_address_link($token['address']) ?></dd>
                    <dt class="col-sm-3">Decimals:</dt>
                    <dd class="col-sm-9"><?php echo $metadata['decimals'] ?></dd>
                    <dt class="col-sm-3">Initial supply:</dt>
                    <dd class="col-sm-9"><?php echo num($metadata['initialSupply'], $metadata['decimals']) ?></dd>
                    <dt class="col-sm-3">Total supply:</dt>
                    <dd class="col-sm-9"><?php echo num($totalSupply,$decimals) ?></dd>
                    <dt class="col-sm-3">Created:</dt>
                    <dd class="col-sm-9"><?php echo $createTx['height'] ?> (<?php echo display_date($createTx['date']) ?>)</dd>
                    <dt class="col-sm-3">Creator:</dt>
                    <dd class="col-sm-9"><?php echo explorer_address_link($createTx['src']) ?></dd>
                </dl>
            </div>
            <div class="col">
                <?php if($loggedIn) { ?>
                    <div class="ms-0 ms-sm-5 d-flex flex align-items-center flex-wrap">
                        <div>
                            <h5>Your balance</h5>
                            <h5><?php echo explorer_address_link($_SESSION['account']['address']) ?></h5>
                            <h4><?php echo $balance ?> <?php echo $metadata['symbol'] ?></h4>
                            <h5 class="text-muted"><?php echo $coinBalance ?> PHP</h5>
                        </div>
                        <div class="ms-2">
                            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#send-token">Send</button>
                            <?php if($mintable) { ?>
                                <button class="btn btn-success m-2" data-bs-toggle="modal" data-bs-target="#mint-token">Mint</button>
                            <?php } ?>
                            <?php if($burnable) { ?>
                                <button class="btn btn-danger m-2" data-bs-toggle="modal" data-bs-target="#burn-token">Burn</button>
                            <?php } ?>
                        </div>

                    </div>
                <?php } else { ?>
                    <div class="ms-0 ms-sm-5 d-flex flex align-items-center flex-grow-1">
                        <div class="alert alert-info w-100 mb-0 d-flex align-items-center">
                            Login to access your founds
                            <a class="btn btn-info ms-auto" href="/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/wallet?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']) ?>">Login</a>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>

    </div>
</div>

<div id="token-app">
    <div class="modal fade" id="send-token" ref="sendTokenModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel"
         style="display: none;" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="staticBackdropLabel">Send token</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="token-address" class="form-label">Receiver address:</label>
                        <input class="form-control" type="text" v-model="address" id="token-address"/>
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount:</label>
                        <input class="form-control" type="text" v-model="amount" id="amount"/>
                    </div>
                    <div class="mb-3">
                        <label for="fee" class="form-label">Fee:</label>
                        <?php if($accountBalance < $scExecFee) { ?>
                            <div class="text-danger"><?php echo $scExecFee ?> PHPCoin</div>
                            <div class="text-danger">You do not have enough coins to execute this transfer</div>
                        <?php } else { ?>
                            <div><?php echo $scExecFee ?> PHPCoin</div>
                        <?php } ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" @click="sendToken">Send</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="mint-token" ref="mintTokenModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel"
         style="display: none;" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="staticBackdropLabel">Mint token</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount:</label>
                        <input class="form-control" type="text" v-model="mintAmount"/>
                    </div>
                    <div class="mb-3">
                        <label for="fee" class="form-label">Fee:</label>
                        <?php if($accountBalance < $scExecFee) { ?>
                            <div class="text-danger"><?php echo $scExecFee ?> PHPCoin</div>
                            <div class="text-danger">You do not have enough coins to execute this transfer</div>
                        <?php } else { ?>
                            <div><?php echo $scExecFee ?> PHPCoin</div>
                        <?php } ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" @click="mintToken">Mint</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="burn-token" ref="burnTokenModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel"
         style="display: none;" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="staticBackdropLabel">Burn token</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount:</label>
                        <input class="form-control" type="text" v-model="burnAmount"/>
                    </div>
                    <div class="mb-3">
                        <label for="fee" class="form-label">Fee:</label>
                        <?php if($accountBalance < $scExecFee) { ?>
                            <div class="text-danger"><?php echo $scExecFee ?> PHPCoin</div>
                            <div class="text-danger">You do not have enough coins to execute this transfer</div>
                        <?php } else { ?>
                            <div><?php echo $scExecFee ?> PHPCoin</div>
                        <?php } ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" @click="burnToken">Burn</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col">
        <h4>Token transfers</h4>

        <?php if(count($mempoolTransfers)>0) { ?>
            <h5>Mempool</h5>
            <div class="table-responsive">
                <table class="table table-sm table-striped dataTable">
                    <thead class="table-light">
                    <tr>
                        <th>Height</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mempoolTransfers as $transfer) {
                        ?>
                        <tr>
                            <td><?php echo $transfer['height'] ?></td>
                            <td><?php echo display_date($transfer['date']) ?></td>
                            <td><?php echo $transfer['method'] ?></td>
                            <td><?php echo $transfer['src'] ?></td>
                            <td><?php echo $transfer['dst'] ?></td>
                            <td><?php echo $transfer['amount'] ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

        <h5>Completed</h5>
        <div class="table-responsive">
            <table class="table table-sm table-striped dataTable">
                <thead class="table-light">
                    <tr>
                        <th>Height</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>From</th>
                        <th>To</th>
                        <th style="text-align: right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transfers as $transfer) {
                        ?>
                        <tr>
                            <td><?php echo explorer_height_link($transfer['height']) ?></td>
                            <td><?php echo display_date($transfer['date']) ?></td>
                            <td><?php echo $transfer['method'] ?></td>
                            <td><?php echo explorer_address_link($transfer['src']) ?></td>
                            <td><?php echo explorer_address_link($transfer['dst']) ?></td>
                            <td style="text-align: right"><?php echo num($transfer['amount'],$decimals) ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <nav aria-label="Page navigation example">
            <ul class="pagination">
                <?php if ($transfers_start>0) { ?>
                    <li class="page-item">
                        <a class="page-link" href="/apps/explorer/tokens/token.php?id=<?php echo $id ?>&transfers_start=<?php echo $transfers_start-$rpp ?>">Prev</a>
                    </li>
                <?php } ?>
                <li class="page-item ">
                    <a class="page-link" href="/apps/explorer/tokens/token.php?id=<?php echo $id ?>&transfers_start=<?php echo $transfers_start+$rpp ?>">Next</a>
                </li>
            </ul>
        </nav>


    </div>
    <div class="col">
        <h4>Top 10 holders</h4>
        <div class="table-responsive">
            <table class="table table-sm table-striped dataTable">
                <thead class="table-light">
                <tr>
                    <th>Address</th>
                    <th style="text-align: right">Amount</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($topHolders as $holder) {
                    ?>
                    <tr>
                        <td><?php echo explorer_address_link($holder['address']) ?></td>
                        <td style="text-align: right"><?php echo $holder['balance'] ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if($loggedIn) { ?>
        <div class="col">
            <h4>Your transfers</h4>

            <?php if(count($myMempoolTransfers)>0) { ?>
                <h5>Mempool</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-striped dataTable">
                        <thead class="table-light">
                        <tr>
                            <th>Height</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Amount</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($myMempoolTransfers as $transfer) {
                            ?>
                            <tr>
                                <td><?php echo $transfer['height'] ?></td>
                                <td><?php echo display_date($transfer['date']) ?></td>
                                <td><?php echo $transfer['method'] ?></td>
                                <td><?php echo $transfer['src'] ?></td>
                                <td><?php echo $transfer['dst'] ?></td>
                                <td><?php echo $transfer['amount'] ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>

            <h5>Completed</h5>
            <div class="table-responsive">
                <table class="table table-sm table-striped dataTable">
                    <thead class="table-light">
                    <tr>
                        <th>Height</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>From</th>
                        <th>To</th>
                        <th class="text-end">Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($myTransfers as $transfer) {
                        ?>
                        <tr>
                            <td><?php echo explorer_height_link($transfer['height']) ?></td>
                            <td><?php echo display_date($transfer['date']) ?></td>
                            <td><?php echo $transfer['method'] ?></td>
                            <td><?php echo explorer_address_link($transfer['src']) ?></td>
                            <td><?php echo explorer_address_link($transfer['dst']) ?></td>
                            <td class="text-end"><?php echo num($transfer['amount'],$decimals) ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php } ?>
</div>

<hr/>
<?php if ($loggedIn) { ?>

    <script src="/apps/common/js/phpcoin-crypto.js" type="text/javascript"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <script src="/apps/explorer/tokens/tokens.js" type="text/javascript"></script>
    <script type="text/javascript">

        const publicKey = '<?php echo $_SESSION['account']['public_key'] ?>';
        const scAddress = '<?php echo $id ?>';
        const chainId = '<?php echo CHAIN_ID ?>';

        const { createApp, ref } = Vue;
        createApp({

            data() {
                return {
                    amount: null,
                    address: null,
                    mintAmount: null,
                    burnAmount: null
                };
            },
            methods: {
                sendToken() {
                    if(!this.address || !verifyAddress(this.address)) {
                        Swal.fire(
                            {
                                title: 'Invalid address',
                                text: 'Enter valid receiver address',
                                icon: 'error'
                            }
                        )
                        return;
                    }
                    if(!this.amount || this.amount <= 0 || isNaN(this.amount)) {
                        Swal.fire(
                            {
                                title: 'Invalid amount',
                                text: 'Enter valid amount',
                                icon: 'error'
                            }
                        )
                        return;
                    }
                    confirmMsg("Confirm sending token","Are you sure to want to execute this transfer?",()=>{
                        this.execSendToken();
                    })
                },
                mintToken() {
                    if(!this.mintAmount || this.mintAmount <= 0 || isNaN(this.mintAmount)) {
                        Swal.fire(
                            {
                                title: 'Invalid amount',
                                text: 'Enter valid amount',
                                icon: 'error'
                            }
                        )
                        return;
                    }
                    confirmMsg("Confirm mint token","Are you sure to want to execute this transfer?",()=>{
                        this.execMintToken();
                    })
                },
                burnToken() {
                    if(!this.burnAmount || this.burnAmount <= 0 || isNaN(this.burnAmount)) {
                        Swal.fire(
                            {
                                title: 'Invalid amount',
                                text: 'Enter valid amount',
                                icon: 'error'
                            }
                        )
                        return;
                    }
                    confirmMsg("Confirm burning token","Are you sure to want to execute this transfer?",()=>{
                        this.execBurnToken();
                    })
                },
                execSendToken() {
                    let data = {
                        public_key: publicKey,
                        sc_address: scAddress,
                        amount: 0,
                        method: 'transfer',
                        params: [this.address, this.amount]
                    };

                    axios.post('/api.php?q=generateSmartContractExecTx', data).then(res=>{
                        if(res.data.status === 'ok') {
                            let tx = res.data.data.tx;
                            let signature_base = res.data.data.signature_base;

                            enterPrivateKey(privateKey=>{
                                sendTransaction(tx, signature_base, privateKey);
                                bootstrap.Modal.getInstance(document.getElementById('send-token')).hide();
                            })


                        } else {
                            Swal.fire(
                                {
                                    title: 'Error generating transaction',
                                    text: 'Error from API server when generating transaction',
                                    icon: 'error'
                                }
                            )
                        }
                    }).catch(err=>{
                        console.error(err);
                        Swal.fire(
                            {
                                title: 'Error sending transaction',
                                text: 'Error contacting API server',
                                icon: 'error'
                            }
                        )
                    });
                },
                execMintToken() {
                    let data = {
                        public_key: publicKey,
                        sc_address: scAddress,
                        amount: 0,
                        method: 'mint',
                        params: [this.mintAmount]
                    };

                    axios.post('/api.php?q=generateSmartContractExecTx', data).then(res=>{
                        if(res.data.status === 'ok') {
                            let tx = res.data.data.tx;
                            let signature_base = res.data.data.signature_base;

                            enterPrivateKey(privateKey=>{
                                sendTransaction(tx, signature_base, privateKey);
                                bootstrap.Modal.getInstance(document.getElementById('mint-token')).hide();
                            })


                        } else {
                            Swal.fire(
                                {
                                    title: 'Error generating transaction',
                                    text: 'Error from API server when generating transaction',
                                    icon: 'error'
                                }
                            )
                        }
                    }).catch(err=>{
                        console.error(err);
                        Swal.fire(
                            {
                                title: 'Error sending transaction',
                                text: 'Error contacting API server',
                                icon: 'error'
                            }
                        )
                    });
                },
                execBurnToken() {
                    let data = {
                        public_key: publicKey,
                        sc_address: scAddress,
                        amount: 0,
                        method: 'burn',
                        params: [this.burnAmount]
                    };

                    axios.post('/api.php?q=generateSmartContractExecTx', data).then(res=>{
                        if(res.data.status === 'ok') {
                            let tx = res.data.data.tx;
                            let signature_base = res.data.data.signature_base;

                            enterPrivateKey(privateKey=>{
                                sendTransaction(tx, signature_base, privateKey);
                                bootstrap.Modal.getInstance(document.getElementById('burn-token')).hide();
                            })


                        } else {
                            Swal.fire(
                                {
                                    title: 'Error generating transaction',
                                    text: 'Error from API server when generating transaction',
                                    icon: 'error'
                                }
                            )
                        }
                    }).catch(err=>{
                        console.error(err);
                        Swal.fire(
                            {
                                title: 'Error sending transaction',
                                text: 'Error contacting API server',
                                icon: 'error'
                            }
                        )
                    });
                },
            }

        }).mount("#token-app");
    </script>


<?php } ?>


<style>
    .token-image-placeholder {
        width: 64px;
        height: 64px;
        border-radius: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        letter-spacing: -1px;
        font-size: x-large;
    }
</style>

<?php
require_once __DIR__ . '/../../common/include/bottom.php';
?>

