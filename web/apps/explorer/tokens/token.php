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
    $sql="select * from (
    select sc.address, sc.metadata, scs.var_value as balance,
           row_number() over (partition by scs.var_key order by scs.height desc) as rn
    from smart_contracts sc
    join smart_contract_state scs on (scs.sc_address = sc.address)
    where json_extract(sc.metadata, '$.class') = 'ERC-20' and sc.address = ?
    and scs.variable = 'balances' and scs.var_key = ?) as states
    where states.rn =1";

    $token = $db->row($sql,[$id, $address], false);

    $sql="select scs.var_value as decimals from smart_contracts sc
         join smart_contract_state scs on (sc.height = scs.height)
            where json_extract(sc.metadata, '$.class') = 'ERC-20' and sc.address = ?
              and scs.variable = 'decimals'";
    $decimals = $db->single($sql,[$id, $address], false);
    $balance = bcdiv($token['balance'], bcpow(10, $decimals), $decimals);
}

$transfers_start = $_GET['transfers_start'] ?? 0;
$rpp = 10;
$sql="select * from transactions t where t.type = 6 and t.dst = ? order by t.height desc limit $transfers_start, $rpp";
$transactions = $db->run($sql,[$id], false);

function get_token_transfers($transactions) {
    $list = [];
    foreach ($transactions as $transaction) {
        $message = $transaction['message'];
        $data = base64_decode($message);
        $data = json_decode($data, true);
        $method = $data['method'];
        if ($method != "transferFrom" && $method != "transfer") {
            continue;
        }
        $params = $data['params'];
        if ($method == "transfer") {
            $src = $transaction['src'];
            $dst = $params[0];
            $amount = $params[1];
        } else if ($method == "transferFrom") {
            $src = $params[0];
            $dst = $params[1];
            $amount = $params[2];
        }
        $list[]=[
            "transaction" => $transaction,
            "src"=>$src,
            "dst"=>$dst,
            "amount"=>$amount,
        ];
    }
    return $list;
}

$transfers = get_token_transfers($transactions);

$sql="select * from mempool m where m.type = 6 and m.dst = ? order by m.height desc";
$mempoolTxs = $db->run($sql,[$id], false);
$mempoolTransfers = get_token_transfers($mempoolTxs);

$myTransfers  = array_filter($transfers, function($transfer) use ($address) {
    return $transfer['src'] == $address || $transfer['dst'] == $address;
});

$myMempoolTransfers  = array_filter($mempoolTransfers, function($transfer) use ($address) {
    return $transfer['src'] == $address || $transfer['dst'] == $address;
});

$token = SmartContract::getById($id);
$metadata = json_decode($token['metadata'], true);

$sql="select * from transactions t where t.type = :type and t.dst = :dst limit 1";
$createTx = $db->row($sql,[":type"=>TX_TYPE_SC_CREATE,":dst"=>$id]);

$color = stringToHex($token['address']);
$indexes=[5,10,15,20,25,30];
$c= "";
foreach ($indexes as $index) {
    $c.=$color[$index];
}

?>


<ol class="breadcrumb m-0 ps-0 h4">
    <li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
    <li class="breadcrumb-item"><a href="/apps/explorer/tokens/list.php">Tokens</a></li>
    <li class="breadcrumb-item"><?php echo $token['name'] ?> (<?php echo $metadata['symbol'] ?>)</li>
</ol>

<div class="card">
    <div class="card-body d-flex p-2 flex-wrap">
        <div class="me-2 align-items-center d-flex">
            <?php if (!empty($metadata['image'])) { ?>
                <img src="<?php echo $metadata['image'] ?>" style="width:64px; height: 64px;"/>
            <?php } else {?>
                <div class="token-image-placeholder" style="background-color: #<?php echo $c ?>; color: <?php echo getContrastingTextColor("#$c") ?>">
                    <?php echo $metadata['symbol'] ?>
                </div>
            <?php } ?>
        </div>
        <div>
            <h4><?php echo $metadata['name'] ?></h4>
            <h5><?php echo $metadata['symbol'] ?></h5>
            <div><?php echo $metadata['description'] ?></div>
        </div>
        <div class="ms-0 ms-sm-5 d-flex flex-column">
            <div>
                <span class="text-muted">Decimals:</span>
            </div>
            <div>
                <span class="text-muted">Total supply: </span>
            </div>
            <div>
                <span class="text-muted">Created: </span>
            </div>
            <div>
                <span class="text-muted">Creator: </span>
            </div>
        </div>
        <div class="ms-0 ms-sm-5 d-flex flex-column">
            <div>
                <?php echo $metadata['decimals'] ?>
            </div>
            <div>
                <?php echo num($metadata['initialSupply'], $metadata['decimals']) ?>
            </div>
            <div>
                <?php echo $createTx['height'] ?> (<?php echo display_date($createTx['date']) ?>)
            </div>
            <div>
                <?php echo $createTx['src'] ?>
            </div>
        </div>
        <?php if($loggedIn) { ?>
            <div class="ms-0 ms-sm-5 d-flex flex align-items-center">
                <div>
                    <h5>Your balance</h5>
                    <h5><?php echo explorer_address_link($_SESSION['account']['address']) ?></h5>
                    <h4><?php echo $balance ?></h4>
                </div>
                <div class="ms-2">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#send-token">Send</button>
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
                    <?php if($accountBalance < TX_SC_EXEC_FEE) { ?>
                        <div class="text-danger"><?php echo TX_SC_EXEC_FEE ?> PHPCoin</div>
                        <div class="text-danger">You do not have enough coins to execute this transfer</div>
                    <?php } else { ?>
                        <div><?php echo TX_SC_EXEC_FEE ?> PHPCoin</div>
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
                        <th>From</th>
                        <th>To</th>
                        <th>Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mempoolTransfers as $transfer) {
                        ?>
                        <tr>
                            <td><?php echo $transfer['transaction']['height'] ?></td>
                            <td><?php echo $transfer['src'] ?></td>
                            <td><?php echo $transfer['dst'] ?></td>
                            <td><?php echo num($transfer['amount'],$decimals) ?></td>
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
                        <th>From</th>
                        <th>To</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transfers as $transfer) {
                        ?>
                        <tr>
                            <td><?php echo $transfer['transaction']['height'] ?></td>
                            <td><?php echo $transfer['src'] ?></td>
                            <td><?php echo $transfer['dst'] ?></td>
                            <td><?php echo num($transfer['amount'],$decimals) ?></td>
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
                            <th>From</th>
                            <th>To</th>
                            <th>Amount</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($myMempoolTransfers as $transfer) {
                            ?>
                            <tr>
                                <td><?php echo $transfer['transaction']['height'] ?></td>
                                <td><?php echo $transfer['src'] ?></td>
                                <td><?php echo $transfer['dst'] ?></td>
                                <td><?php echo num($transfer['amount'],$decimals) ?></td>
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
                        <th>From</th>
                        <th>To</th>
                        <th>Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($myTransfers as $transfer) {
                        ?>
                        <tr>
                            <td><?php echo $transfer['transaction']['height'] ?></td>
                            <td><?php echo $transfer['src'] ?></td>
                            <td><?php echo $transfer['dst'] ?></td>
                            <td><?php echo num($transfer['amount'],$decimals) ?></td>
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

            }

        }).mount("#send-token");
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

