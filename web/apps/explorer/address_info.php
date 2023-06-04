<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once ROOT. '/web/apps/explorer/include/functions.php';
define("PAGE", true);
define("APP_NAME", "Explorer");

if(isset($_GET['address'])) {
	$address = $_GET['address'];
}

if(!Account::valid($address)) {
	header("location: /apps/explorer");
	exit;
}

if(isset($_GET['type'])) {
	$type = $_GET['type'];
}

if(!in_array($type, ["generator", "miner", "masternode"])) {
	header("location: /apps/explorer");
	exit;
}

$addressTypes = Block::getAddressTypes($address);

if(!$addressTypes['is_'.$type]) {
	header("location: /apps/explorer");
	exit;
}

$addressStat = Transaction::getAddressStat($address);
$public_key = Account::publicKey($address);
$balance = Account::pendingBalance($address);

$txStat = Transaction::getTxStatByType($address, $type);

$dm=get_data_model($txStat['tx_cnt'],
	'/apps/explorer/address_info.php?address='.$address.'&type='.$type);

$transactions = Transaction::getByAddressType($address, $type,$dm);

$rewardStat = Transaction::getRewardsStat($address, $type);

$addressPeer = Peer::getPeerByType($address, $type);

if($type == "generator" && $addressPeer) {
    $url = $addressPeer['hostname'] . "/mine.php?q=stat";
    $res = url_get($url);
    $miner_stats = json_decode($res, true);
}

global $_config;

require_once __DIR__. '/../common/include/top.php';
?>


<ol class="breadcrumb m-0 ps-0 h4">
	<li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
	<li class="breadcrumb-item"><?php echo $type ?></li>
	<li class="breadcrumb-item active"><?php echo $address ?></li>
</ol>

<table class="table table-sm table-striped">
    <tr>
        <td width="20%">Address</td>
        <td>
            <?php echo explorer_address_link($address) ?>
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
        </td>
    </tr>
    <?php if ($addressPeer) { ?>
        <tr>
            <td>Peer</td>
            <td>
                <a href="/apps/explorer/peer.php?id=<?php echo $addressPeer['id'] ?>"><?php echo $addressPeer['hostname'] ?></a>
                <a href="<?php echo $addressPeer['hostname'] ?>" target="_blank" class="ms-2"
                   data-bs-toggle="tooltip" data-bs-placement="top" title="Open in new window">
                    <span class="fa fa-external-link-alt"></span>
                </a>
            </td>
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

<?php if($type == "generator") { ?>
    <h4>Earned as generator</h4>
<?php } ?>
<?php if($type == "miner") { ?>
    <h4>Earned as miner</h4>
<?php } ?>
<?php if($type == "masternode") { ?>
    <h4>Earned as masternode</h4>
<?php } ?>
<table class="table table-sm table-striped">
    <tr>
        <td width="20%" class="h5">Earned total</td>
        <td class="h5"><?php echo num($txStat['total']) ?></td>
    </tr>
    <tr>
        <td>Transactions count</td>
        <td><?php echo $txStat['tx_cnt'] ?></td>
    </tr>
    <tr>
        <td>Average reward</td>
        <td><?php echo num($txStat['total'] / $txStat['tx_cnt']) ?></td>
    </tr>
</table>

<div class="row">
    <div class="col">
        <div class="card">
            <div class="card-body p-3">
                <h5>Daily</h5>
                <h3><?php echo num($rewardStat['total']['daily']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card">
            <div class="card-body p-3">
                <h5>Weekly</h5>
                <h3><?php echo num($rewardStat['total']['weekly']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card">
            <div class="card-body p-3">
                <h5>Monthly</h5>
                <h3><?php echo num($rewardStat['total']['monthly']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card">
            <div class="card-body p-3">
                <h5>Yearly</h5>
                <h3><?php echo num($rewardStat['total']['yearly']) ?></h3>
            </div>
        </div>
    </div>
</div>

<?php if($miner_stats) {?>

    <h4>Mining hashrates</h4>

    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body p-3">
                    <h5>Current block</h5>
                    <h3><?php echo $miner_stats['data']['hashRates']['current']['hashRate'] ?>&nbsp;</h3>
                    <div class="text-nowrap">
                         <span class="text-muted font-size-13"><strong><?php echo $miner_stats['data']['hashRates']['current']['miner'] ?></strong> miners</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card">
                <div class="card-body p-3">
                    <h5>Previous block</h5>
                    <h3><?php echo $miner_stats['data']['hashRates']['prev']['hashRate'] ?>&nbsp;</h3>
                    <div class="text-nowrap">
                        <span class="text-muted font-size-13"><strong><?php echo $miner_stats['data']['hashRates']['prev']['miner'] ?></strong> miners</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card">
                <div class="card-body p-3">
                    <h5>Last 10 blocks</h5>
                    <h3><?php echo $miner_stats['data']['hashRates']['last10blocks']['hashRate'] ?>&nbsp;</h3>
                    <div class="text-nowrap">
                        <span class="text-muted font-size-13"><strong><?php echo $miner_stats['data']['hashRates']['last10blocks']['miner'] ?></strong> miners</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card">
                <div class="card-body p-3">
                    <h5>Last 100 blocks</h5>
                    <h3><?php echo $miner_stats['data']['hashRates']['last100blocks']['hashRate'] ?>&nbsp;</h3>
                    <div class="text-nowrap">
                        <span class="text-muted font-size-13"><strong><?php echo $miner_stats['data']['hashRates']['last100blocks']['miner'] ?></strong> miners</span>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <h4>Miner statistics</h4>
    <div class="table-responsive">
        <table class="table table-sm table-striped">
            <tr>
                <td>Running</td>
                <td>
                    <?php
                    if(isset($miner_stats['data']['started'])) {
	                    $elapsed = time() - $miner_stats['data']['started'];
	                    $days = (int) ($elapsed / 60 / 60 / 24);
	                    $remain = $elapsed - $days * 60 * 60 * 24;
	                    if ($days > 0) {
                            echo $days." d ";
	                    }
                        echo date("H:i:s", $remain);
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td>Submits</td>
                <td><?php echo $miner_stats['data']['submits'] ?></td>
            </tr>
            <tr>
                <td>Accepted</td>
                <td><?php echo $miner_stats['data']['accepted'] ?></td>
            </tr>
            <tr>
                <td>Rejected</td>
                <td>
                    <div class="d-flex">
                        <span class="pe-4"><?php echo $miner_stats['data']['rejected'] ?></span>
                        <table class="table table-sm table-striped">
                            <?php foreach($miner_stats['data']['reject-reasons'] as $reason => $count) { ?>
                            <tr>
                                <td><?php echo $reason ?></td>
                                <td><?php echo $count ?></td>
                            </tr>
                            <?php } ?>
                        </table>
                    </div>
                </td>
            </tr>
            <tr>
                <td>Unique Ips</td>
                <td><?php echo count(array_keys($miner_stats['data']['ips'])) ?></td>
            </tr>
            <tr>
                <td>Unique Miners</td>
                <td><?php echo count(array_keys($miner_stats['data']['miners'])) ?></td>
            </tr>
            <tr>
                <td>Miners</td>
                <td>
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Address</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($miner_stats['data']['miners'] as $address => $count) { ?>
                                <tr>
                                    <td><?php echo explorer_address_link($address) ?></td>
                                    <td><?php echo $count ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </div>
<?php } ?>

<h4>Transactions</h4>
<div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Height</th>
                <th>Block</th>
                <th>Date</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($transactions as $tx) { ?>
                <tr>
                    <td><?php echo explorer_tx_link($tx['id']) ?></td>
                    <td><?php echo $tx['height'] ?></td>
                    <td><?php echo explorer_block_link($tx['block']) ?></td>
                    <td><?php echo display_date($tx['date']) ?></td>
                    <td><?php echo num($tx['val']) ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php echo $dm['paginator'] ?>

<div class="mt-5"></div>

<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
