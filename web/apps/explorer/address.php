<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once ROOT. '/web/apps/explorer/include/functions.php';
define("PAGE", true);
define("APP_NAME", "Explorer");

if(isset($_GET['address'])) {
    $address = $_GET['address'];
} else if (isset($_GET['pubkey'])) {
    $pubkey = $_GET['pubkey'];
	$address=Account::getAddress($pubkey);
	$pubkeyCheck = Account::publicKey($address);
	if($pubkeyCheck != $pubkey) {
		header("location: /apps/explorer");
		exit;
    }
}

if(!Account::valid($address)) {
	header("location: /apps/explorer");
	exit;
}


$balance = Account::pendingBalance($address);
$public_key = Account::publicKey($address);

$show_balances = isset($_GET['show_balances']);
$address_link = '/apps/explorer/address.php?address='.$address;
if ($show_balances) {
    $address_link .= '&show_balances=1';
}

$dm=get_data_model(PHP_INT_MAX, $address_link, "", 100);

$transactions = Account::getTransactions($address, $dm);
$addressStat = Transaction::getAddressStat($address);

$mempool = Account::getMempoolTransactions($address);
$nativeTransfers = SmartContract::getNativeTransfersForAddress($address, 100);
$nativeTransfersByTx = [];
foreach ($nativeTransfers as $transfer) {
    if (!empty($transfer['tx_id'])) {
        $nativeTransfersByTx[$transfer['tx_id']][] = $transfer;
    }
}

// Token transactions are represented by a view over type-6 transactions.
// Also include ERC-20 events emitted by nested contract calls (for example
// DEX transferFrom/transfer), which are not separate blockchain transactions.
$tokenTransactions = $db->run(
    "select tt.*, t.name as token_name, t.metadata as token_metadata
     from token_txs tt left join tokens t on t.address = tt.token
     where tt.src = ? or tt.dst = ?
     order by tt.height desc limit 100",
    [$address, $address], false
) ?: [];
$tokenTransactionIds = [];
foreach ($tokenTransactions as $row) {
    $tokenTransactionIds[$row['id']] = true;
}
$tokenContracts = $db->run("select address from tokens", [], false) ?: [];
foreach ($tokenContracts as $tokenContract) {
    foreach (SmartContract::getTokenEvents($tokenContract['address'], $address) as $event) {
        if (empty($tokenTransactionIds[$event['id']])) {
            $event['token'] = $tokenContract['address'];
            $event['token_name'] = '';
            $event['formatted_amount'] = true;
            $tokenTransactions[] = $event;
            $tokenTransactionIds[$event['id']] = true;
        }
    }
}
usort($tokenTransactions, function ($a, $b) {
    return intval($b['height'] ?? 0) <=> intval($a['height'] ?? 0);
});
$tokenBalances = $db->run(
    "select tb.token, tb.balance, t.name as token_name, t.metadata as token_metadata
     from token_balances tb left join tokens t on t.address = tb.token
     where tb.address = ? order by tb.token",
    [$address], false
) ?: [];

$addressTypes = Block::getAddressTypes($address);

if(NETWORK == "mainnet") {
    $url = "http://".$_SERVER['SERVER_NAME']."/dapps.php?url=PoApBr2zi84BEw2wtseaA2DtysEVCUnJd7/labeler/api.php?q=getAddressLabel&address=$address";
    $res = file_get_contents($url);
    $res = json_decode($res, true);
    $label = $res['data'];
}

?>
<?php
require_once __DIR__. '/../common/include/top.php';
?>

<ol class="breadcrumb m-0 ps-0 h4">
    <li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
    <li class="breadcrumb-item">Address</li>
    <li class="breadcrumb-item active text-truncate"><?php echo $address ?></li>
</ol>

<table class="table table-sm table-striped">
    <tr>
        <td>Address</td>
        <td>
            <?php echo $address ?>
            <?php if($addressTypes['is_generator']) { ?>
                <a href="/apps/explorer/address_info.php?address=<?php echo $address ?>&type=generator">
                    <span class="badge rounded-pill bg-success font-size-12">Generator</span>
                </a>
            <?php } ?>
	        <?php if($addressTypes['is_miner']) { ?>
                <a href="/apps/explorer/address_info.php?address=<?php echo $address ?>&type=miner">
                    <span class="badge rounded-pill bg-warning font-size-12">Miner</span>
                </a>
	        <?php } ?>
	        <?php if($addressTypes['is_masternode']) { ?>
                <a href="/apps/explorer/address_info.php?address=<?php echo $address ?>&type=masternode">
                    <span class="badge rounded-pill bg-info font-size-12">Masternode</span>
                </a>
	        <?php } ?>
	        <?php if($addressTypes['is_stake']) { ?>
                <a href="/apps/explorer/address_info.php?address=<?php echo $address ?>&type=stake">
                    <span class="badge rounded-pill bg-pink font-size-12">Stake</span>
                </a>
	        <?php } ?>
	        <?php if($addressTypes['is_smart_contract']) { ?>
                <a href="/apps/explorer/smart_contract.php?id=<?php echo $address ?>">
                    <span class="badge rounded-pill bg-danger font-size-12">Smart Contract</span>
                </a>
	        <?php } ?>
        </td>
    </tr>
    <?php if( NETWORK == "mainnet" && !empty($label)) { ?>
        <tr>
            <td>Label</td>
            <td><?php echo $label ?></td>
        </tr>
    <?php } ?>
    <tr>
        <td>Public key</td>
        <td><?php echo $public_key ?></td>
    </tr>
    <tr>
        <td>Total received</td>
        <td><?php if ($addressStat['total_received']) echo $addressStat['total_received'] ." (" .  $addressStat['count_received'] . ")"?></td>
    </tr>
    <tr>
        <td>Total sent</td>
        <td><?php if ($addressStat['total_sent']) echo $addressStat['total_sent']  . " (" .  $addressStat['count_sent'] .")" ?></td>
    </tr>
    <tr>
        <td class="h4">Balance</td>
        <td class="h4"><?php echo $balance ?></td>
    </tr>
</table>

<div class="btn-group mb-3" role="tablist" aria-label="Address transaction type">
    <button type="button" class="btn btn-primary" id="native-transactions-tab">Native transactions</button>
    <button type="button" class="btn btn-outline-primary" id="token-transactions-tab" <?php echo empty($tokenTransactions) ? 'disabled' : '' ?>>Token transactions</button>
    <button type="button" class="btn btn-outline-primary" id="token-balances-tab" <?php echo empty($tokenBalances) ? 'disabled' : '' ?>>Token balances</button>
</div>

<?php if(!empty($mempool)) { ?>
    <h4 class="native-content">Mempool transactions</h4>
    <div class="table-responsive native-content">
        <table class="table table-sm table-striped">
            <thead class="table-light">
            <tr>
                <th>Id</th>
                <th>Date</th>
                <th>Height</th>
                <th>Block</th>
                <th>From/To</th>
                <th>Type</th>
                <th>Value</th>
                <th>Fee</th>
                <th>Message</th>
            </tr>
            </thead>
            <tbody>
			<?php foreach($mempool as $transaction) {
                $party = "";
                if($transaction['type'] != TX_TYPE_REWARD && $transaction['type'] != TX_TYPE_FEE) {
                    if ($address == $transaction['dst']) {
                        $party = Account::getAddress($transaction['public_key']);
                    } else {
                        $party = $transaction['dst'];
                    }
                }
			    ?>
                <tr>
                    <td>
                        <a href="/apps/explorer/tx.php?id=<?php echo $transaction['id'] ?>">
                            <?php echo truncate_hash($transaction['id']) ?>
                        </a>
                    </td>
                    <td><?php echo display_date($transaction['date']) ?></td>
                    <td><a href="/apps/explorer/block.php?height=<?php echo $transaction['block'] ?>">
							<?php echo $transaction['height'] ?></a></td>
                    <td><a href="/apps/explorer/block.php?height=<?php echo $transaction['block'] ?>">
							<?php echo $transaction['block'] ?></a></td>
                    <td><a href="/apps/explorer/address.php?address=<?php echo $party ?>">
			                <?php echo $party ?></a></td>
                    <td><?php echo $transaction['type_label'] ?></td>
                    <td><?php echo num($transaction['val']) ?></td>
                    <td><?php echo num($transaction['fee']) ?></td>
                    <td style="word-break: break-all"><?php echo $transaction['message'] ?></td>
                </tr>
			<?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<?php if(!empty(array_filter($nativeTransfers, function ($transfer) { return empty($transfer['tx_id']); }))) { ?>
    <h4 class="native-content">Smart-contract PHP transfers</h4>
    <div class="table-responsive native-content">
        <table class="table table-sm table-striped">
            <thead class="table-light">
            <tr>
                <th>Height</th>
                <th>Transaction</th>
                <th>From/To</th>
                <th class="text-end">Amount</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($nativeTransfers as $transfer) {
                if (!empty($transfer['tx_id'])) continue;
                $incoming = $transfer['to'] === $address;
                $party = $incoming ? $transfer['from'] : $transfer['to'];
                ?>
                <tr>
                    <td><?php echo explorer_height_link($transfer['height']) ?></td>
                    <td>
                        <?php if(!empty($transfer['tx_id'])) { ?>
                            <a href="/apps/explorer/tx.php?id=<?php echo urlencode($transfer['tx_id']) ?>">
                                <?php echo truncate_hash($transfer['tx_id']) ?>
                            </a>
                        <?php } else { ?>
                            <span class="text-muted">legacy</span>
                        <?php } ?>
                    </td>
                    <td>
                        <span class="<?php echo $incoming ? 'text-success' : 'text-danger' ?>">
                            <?php echo $incoming ? '+' : '-' ?>
                        </span>
                        <?php echo explorer_address_link($party) ?>
                    </td>
                    <td class="text-end <?php echo $incoming ? 'text-success' : 'text-danger' ?>">
                        <?php echo ($incoming ? '+' : '-') . h(num($transfer['amount'])) ?> PHP
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<?php if(!empty($tokenTransactions)) { ?>
    <div id="token-transactions" style="display:none">
    <h4>Token transactions</h4>
    <div class="table-responsive">
        <table class="table table-sm table-striped">
            <thead class="table-light">
            <tr>
                <th>Height</th>
                <th>Date</th>
                <th>Token</th>
                <th>Method</th>
                <th>Transaction</th>
                <th>From/To</th>
                <th class="text-end">Amount</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($tokenTransactions as $tokenTransaction) {
                $incoming = ($tokenTransaction['dst'] ?? '') === $address;
                $party = $incoming ? ($tokenTransaction['src'] ?? '') : ($tokenTransaction['dst'] ?? '');
                $metadata = json_decode($tokenTransaction['token_metadata'] ?? '', true) ?: [];
                $decimals = intval($metadata['decimals'] ?? 8);
                $amount = !empty($tokenTransaction['formatted_amount'])
                    ? $tokenTransaction['amount']
                    : num($tokenTransaction['amount'] ?? '0', $decimals);
                $symbol = $metadata['symbol'] ?? ($tokenTransaction['token_name'] ?? '');
                ?>
                <tr>
                    <td><?php echo explorer_height_link($tokenTransaction['height']) ?></td>
                    <td><?php echo display_date($tokenTransaction['date']) ?></td>
                    <td><?php echo explorer_address_link($tokenTransaction['token']) ?><?php echo $symbol ? ' ('.h($symbol).')' : '' ?></td>
                    <td><?php echo h($tokenTransaction['method']) ?></td>
                    <td><?php echo !empty($tokenTransaction['id']) ? explorer_tx_link($tokenTransaction['id'], true) : '' ?></td>
                    <td>
                        <span class="<?php echo $incoming ? 'text-success' : 'text-danger' ?>"><?php echo $incoming ? '+' : '-' ?></span>
                        <?php echo $party ? explorer_address_link($party) : '' ?>
                    </td>
                    <td class="text-end <?php echo $incoming ? 'text-success' : 'text-danger' ?>"><?php echo ($incoming ? '+' : '-') . h($amount) ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    </div>
<?php } ?>

<?php if(!empty($tokenBalances)) { ?>
    <div id="token-balances" style="display:none">
        <h4>Token balances</h4>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead class="table-light">
                <tr>
                    <th>Token</th>
                    <th>Contract</th>
                    <th class="text-end">Balance</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach($tokenBalances as $tokenBalance) {
                    $metadata = json_decode($tokenBalance['token_metadata'] ?? '', true) ?: [];
                    $decimals = intval($metadata['decimals'] ?? 8);
                    $symbol = $metadata['symbol'] ?? ($tokenBalance['token_name'] ?? '');
                    ?>
                    <tr>
                        <td><?php echo h($tokenBalance['token_name'] ?? '') ?><?php echo $symbol ? ' ('.h($symbol).')' : '' ?></td>
                        <td><?php echo explorer_address_link($tokenBalance['token']) ?></td>
                        <td class="text-end"><?php echo h(Dex::tokenToDisplay($tokenBalance['balance'], $decimals)) ?><?php echo $symbol ? ' '.h($symbol) : '' ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
<?php } ?>

<div class="native-content">
<div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="mb-0">Transactions</h4>
    <form method="get" class="mb-0">
        <input type="hidden" name="address" value="<?php echo $address ?>">
        <div class="form-check mb-0">
            <input type="checkbox" class="form-check-input" name="show_balances" id="show_balances" value="1"
                onclick="this.form.submit()" <?php if ($show_balances) { ?>checked="checked"<?php } ?>>
            <label class="form-check-label" for="show_balances">Show balances</label>
        </div>
    </form>
</div>
<div class="table-responsive">
<table class="table table-sm table-striped">
    <thead class="table-light">
        <tr>
            <th>Id</th>
            <th>Date</th>
            <th>Height</th>
            <th>Conf</th>
            <th>Block</th>
            <th>From/To</th>
            <th>Type</th>
            <th class="text-end">Value</th>
            <?php if ($show_balances) { ?>
            <th class="text-end">Balance</th>
            <?php } ?>
<!--            <th>Message</th>-->
            <th class="text-end">Fee</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($show_balances) {
            $offset = ($dm['page'] - 1) * $dm['limit'];
            $running_balance = floatvalue($balance);
            if ($offset > 0) {
                $running_balance += Account::getTransactionsBalanceOffset($address, $offset);
            }
        }

        foreach($transactions as $transaction) {
	        $party="";
            if($transaction['type'] != TX_TYPE_REWARD && $transaction['type'] != TX_TYPE_FEE) {
                if ($address == $transaction['dst']) {
                    $party = Account::getAddress($transaction['public_key']);
                } else {
                    $party = $transaction['dst'];
                }
            }
            ?>
            <tr>
                <td>
                    <a href="/apps/explorer/tx.php?id=<?php echo $transaction['id'] ?>">
                        <?php echo truncate_hash($transaction['id']) ?>
                    </a>
                </td>
                <td><?php echo display_date($transaction['date']) ?></td>
                <td><a href="/apps/explorer/block.php?height=<?php echo $transaction['height'] ?>">
                        <?php echo $transaction['height'] ?></a></td>
                <td><?php echo $transaction['confirmations'] ?></td>
                <td><a href="/apps/explorer/block.php?height=<?php echo $transaction['height'] ?>">
                        <?php echo truncate_hash($transaction['block']) ?></a></td>
                <td><a href="/apps/explorer/address.php?address=<?php echo urlencode($party) ?>">
			            <?php echo truncate_hash($party) ?></a></td>
                <td>
                    <?php echo Transaction::typeLabel($transaction['type']) ?>
                    <?php if($transaction['type'] == TX_TYPE_REWARD) { ?>
                        <?php if($transaction['message']=="generator") { ?>
                            <span class="badge rounded-pill bg-success">Generator</span>
                        <?php } ?>
                        <?php if($transaction['message']=="miner") { ?>
                            <span class="badge rounded-pill bg-warning">Miner</span>
                        <?php } ?>
                        <?php if($transaction['message']=="nodeminer") { ?>
                            <span class="badge rounded-pill bg-warning">Nodeminer</span>
                        <?php } ?>
                        <?php if($transaction['message']=="masternode") { ?>
                            <span class="badge rounded-pill bg-info">Masternode</span>
                        <?php } ?>
                        <?php if($transaction['message']=="stake") { ?>
                            <span class="badge rounded-pill bg-pink">Stake</span>
                        <?php } ?>
                    <?php } ?>
                </td>
                <td class="<?php echo $transaction['sign']=='-' ? 'text-danger' : 'text-success' ?> text-end">
                    <?php echo $transaction['sign'] .  num($transaction['val']) ?>
                </td>
                <?php if ($show_balances) { ?>
                <td class="text-end">
                    <?php echo num($running_balance) ?>
                </td>
                <?php } ?>
                <td class="text-end">
                    <?php echo !empty(floatval($transaction['fee']) && $transaction['src']==$address) ? "-".num($transaction['fee']) : '' ?>
                </td>
<!--                <td style="word-break: break-all">--><?php //echo $transaction['message'] ?><!--</td>-->
            </tr>
    <?php
            if ($show_balances) {
                $running_balance = Account::reverseTransactionBalance($running_balance, $transaction, $address);
            }

            foreach (($nativeTransfersByTx[$transaction['id']] ?? []) as $transfer) {
                $incoming = $transfer['to'] === $address;
                $party = $incoming ? $transfer['from'] : $transfer['to'];
                ?>
                <tr class="table-info">
                    <td>
                        <a href="/apps/explorer/tx.php?id=<?php echo urlencode($transaction['id']) ?>">
                            <?php echo truncate_hash($transaction['id']) ?>
                        </a>
                    </td>
                    <td><?php echo display_date($transaction['date']) ?></td>
                    <td><a href="/apps/explorer/block.php?height=<?php echo $transaction['height'] ?>"><?php echo $transaction['height'] ?></a></td>
                    <td><?php echo $transaction['confirmations'] ?></td>
                    <td><a href="/apps/explorer/block.php?height=<?php echo $transaction['height'] ?>"><?php echo truncate_hash($transaction['block']) ?></a></td>
                    <td><a href="/apps/explorer/address.php?address=<?php echo urlencode($party) ?>"><?php echo truncate_hash($party) ?></a></td>
                    <td>Smart-contract transfer</td>
                    <td class="<?php echo $incoming ? 'text-success' : 'text-danger' ?> text-end"><?php echo $incoming ? '+' : '-' ?><?php echo h(num($transfer['amount'])) ?></td>
                    <?php if ($show_balances) { ?><td></td><?php } ?>
                    <td class="text-end"></td>
                </tr>
                <?php
            }
        } ?>
    </tbody>
</table>
</div>
<?php echo $dm['paginator'] ?>
</div>
<script>
    (() => {
        const nativeTab = document.getElementById('native-transactions-tab');
        const tokenTab = document.getElementById('token-transactions-tab');
        const tokenSection = document.getElementById('token-transactions');
        const balancesTab = document.getElementById('token-balances-tab');
        const balancesSection = document.getElementById('token-balances');
        const nativeSections = document.querySelectorAll('.native-content');
        const selectTab = (active) => {
            nativeSections.forEach((section) => section.style.display = active === 'native' ? '' : 'none');
            if (tokenSection) tokenSection.style.display = active === 'tokens' ? '' : 'none';
            if (balancesSection) balancesSection.style.display = active === 'balances' ? '' : 'none';
            nativeTab.className = active === 'native' ? 'btn btn-primary' : 'btn btn-outline-primary';
            tokenTab.className = active === 'tokens' ? 'btn btn-primary' : 'btn btn-outline-primary';
            balancesTab.className = active === 'balances' ? 'btn btn-primary' : 'btn btn-outline-primary';
        };
        nativeTab.addEventListener('click', () => {
            selectTab('native');
        });
        tokenTab.addEventListener('click', () => {
            if (!tokenSection) return;
            selectTab('tokens');
        });
        balancesTab.addEventListener('click', () => {
            if (!balancesSection) return;
            selectTab('balances');
        });
    })();
</script>
<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
