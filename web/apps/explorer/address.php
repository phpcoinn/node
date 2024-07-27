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
    '/apps/explorer/address.php?address='.$address, "", 100);

$transactions = Account::getTransactions($address, $dm);
$addressStat = Transaction::getAddressStat($address);

$mempool = Account::getMempoolTransactions($address);

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
            <th>Conf</th>
            <th>Block</th>
            <th>From/To</th>
            <th>Type</th>
            <th class="text-end">Value</th>
<!--            <th>Message</th>-->
            <th class="text-end">Fee</th>
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
                <td><?php echo $transaction['confirmations'] ?></td>
                <td><a href="/apps/explorer/block.php?height=<?php echo $transaction['height'] ?>">
                        <?php echo truncate_hash($transaction['block']) ?></a></td>
                <td><a href="/apps/explorer/address.php?address=<?php echo $party ?>">
			            <?php echo truncate_hash($party) ?></a></td>
                <td>
                    <?php echo TransactionTypeLabel($transaction['type']) ?>
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
                <td class="text-end">
                    <?php echo !empty(floatval($transaction['fee']) && $transaction['src']==$address) ? "-".num($transaction['fee']) : '' ?>
                </td>
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
