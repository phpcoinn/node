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

$dm=get_data_model(AccountgetCountByAddress($address),
    '/apps/explorer/address.php?address='.$address);

$transactions = Account::getTransactions($address, $dm);
$addressStat = Transaction::getAddressStat($address);

$mempool = Account::getMempoolTransactions($address);

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
        <td><?php echo $address ?></td>
    </tr>
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

<?php if(!empty($mempool)) { ?>
    <h4>Mempool transactions</h4>
    <div class="table-responsive">
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

<h4>Transactions</h4>
<div class="table-responsive">
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
<!--            <th>Message</th>-->
            <th>Fee</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($transactions as $transaction) {
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
                <td><a href="/apps/explorer/block.php?height=<?php echo $transaction['height'] ?>">
                        <?php echo truncate_hash($transaction['block']) ?></a></td>
                <td><a href="/apps/explorer/address.php?address=<?php echo $party ?>">
			            <?php echo truncate_hash($party) ?></a></td>
                <td><?php echo TransactionTypeLabel($transaction['type']) ?></td>
                <td class="<?php echo $transaction['sign']=='-' ? 'text-danger' : 'text-success' ?>">
                    <?php echo $transaction['sign'] .  num($transaction['val']) ?>
                </td>
                <td><?php echo !empty(floatval($transaction['fee'])) ? num($transaction['fee']) : '' ?></td>
<!--                <td style="word-break: break-all">--><?php //echo $transaction['message'] ?><!--</td>-->
            </tr>
    <?php } ?>
    </tbody>
</table>
</div>
<?php echo $dm['paginator'] ?>
<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
