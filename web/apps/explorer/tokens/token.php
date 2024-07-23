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

$id=$_GET['id'];

if(empty($id)) {
    header("location: /apps/explorer/tokens/list.php");
    exit;
}

$loggedIn = false;
if(isset($_SESSION['account'])) {
    $balance = Account::getBalance($_SESSION['account']['address']);
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

?>


<ol class="breadcrumb m-0 ps-0 h4">
    <li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
    <li class="breadcrumb-item"><a href="/apps/explorer/tokens/list.php">Tokens</a></li>
    <li class="breadcrumb-item"><?php echo $id ?></li>
</ol>

<div class="d-flex align-items-center">
    <h3>Token <?php echo $id ?></h3>
    <?php if (!$loggedIn) { ?>
        <div class="ms-auto">
            Login to access token
        </div>
    <?php } else { ?>
        <div class="ms-auto d-flex align-items-center gap-2">
            <?php echo explorer_address_link($_SESSION['account']['address']) ?>
            <div><?php echo $balance ?></div>
        </div>
    <?php } ?>
</div>

<?php if ($loggedIn) { ?>
    Token balance <?php echo $balance ?>
<?php } ?>

<div class="row">
    <div class="col-6">
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
    <div class="col-6">
        <h4>My transfers</h4>

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
</div>

<hr/>
<?php if ($loggedIn) { ?>
    <div id="app">
        <h3>Send token</h3>



        Amount:
        <input type="text" v-model="amount"/>
        Address:
        <input type="text" v-model="address"/>
        <button @click="sendToken">Send</button>

        <hr/>
        <h4>Create token</h4>


    </div>

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
                    amount: 1,
                    address: 'Pt91kDK44KsAVrE4CuQ1r8fnkD4Q6nhTda',
                };
            },
            methods: {
                sendToken() {
                    confirmMsg("Confirm sending token","Are you sure to want to execute this transfer?",()=>{
                        this.execSendToken();
                    })
                },
                execSendToken() {
                    if(!this.amount || this.amount < 0 || isNaN(this.amount)) {
                        Swal.fire(
                            {
                                title: 'Invalid amount',
                                text: 'Enter valid amount',
                                icon: 'error'
                            }
                        )
                    }
                    if(!this.address || !verifyAddress(this.address)) {
                        Swal.fire(
                            {
                                title: 'Invalid address',
                                text: 'Enter valid address',
                                icon: 'error'
                            }
                        )
                    }
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

        }).mount("#app");
    </script>


<?php } ?>


<?php
require_once __DIR__ . '/../../common/include/bottom.php';
?>

