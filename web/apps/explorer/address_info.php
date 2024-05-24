<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once ROOT. '/web/apps/explorer/include/functions.php';
define("PAGE", true);
define("APP_NAME", "Explorer");

global $db;

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

if(!in_array($type, ["generator", "miner", "masternode","stake"])) {
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

if(in_array($type, ["generator", "miner", "masternode"])) {
    $txStat = Transaction::getTxStatByType($address, $type);
    $dm=get_data_model($txStat['tx_cnt'],
        '/apps/explorer/address_info.php?address='.$address.'&type='.$type);
    $transactions = Transaction::getByAddressType($address, $type,$dm);
    $rewardStat = Transaction::getRewardsStat($address, $type);
} else {
    //stake
    $sql="select s2.*, s2.total / s2.days as daily, 30*s2.total / s2.days as monthly,
       100 * 30*s2.total / s2.days / s2.start_balance as roi
     from (select stake_stat.*, stake_stat.balance - stake_stat.total as start_balance, stake_stat.diff / (1440 * 60) as days
          from (select max(t.date) - min(t.date)                                                            as diff,
                       count(t.id)                                                                          as tx_cnt,
                       sum(t.val)                                                                           as total,
                       (select a.balance from accounts a where a.id = '$address') as balance,
                       (max(height) - min(height)) / count(t.id) as avg_maturity
                from transactions t
                where t.dst = '$address'
                  and t.message = 'stake'
                  and t.type = 0
                  and t.height > (select max(t.height)
                                  from transactions t
                                  where (t.src = '$address'
                                      or t.dst = '$address')
                                    and (t.message != 'stake' or t.type != 0))
                order by t.height desc)
                   as stake_stat) as s2;";
    $txStat=$db->row($sql);

    $dm=get_data_model($txStat['tx_cnt'],
        '/apps/explorer/address_info.php?address='.$address.'&type='.$type);
    if(is_array($dm)) {
        $page = $dm['page'];
        $limit = $dm['limit'];
        $offset = ($page-1)*$limit;
    } else {
        $limit = intval($dm);
        if ($limit > 100 || $limit < 1) {
            $limit = 100;
        }
    }
    $sql="select * from transactions t
        where t.dst = '$address'
          and t.message = 'stake'
          and t.type = 0
          and t.height > (select max(t.height)
                          from transactions t
                          where (t.src = '$address'
                              or t.dst = '$address')
                            and (t.message != 'stake' or t.type != 0))
        order by t.height desc limit $offset, $limit";
    $transactions=$db->run($sql);
    $rewardStat['total']['daily']=$txStat['daily'];
    $rewardStat['total']['weekly']=$txStat['daily']*7;
    $rewardStat['total']['monthly']=$txStat['daily']*30;
    $rewardStat['total']['yearly']=$txStat['monthly']*12;
}





global $_config, $db;
if($type=="generator") {
    if(!empty($_config['generator_public_key'])) {
        $generator = Account::getAddress($_config['generator_public_key']);
        if($generator == $address) {
            $hostname = $_config['hostname'];
        }
    }
    $addressPeer = Peer::getPeerByType($address, $type);
    if($addressPeer) {
        $hostname = $addressPeer['hostname'];
    }
    if($hostname) {
        $url = $hostname . "/mine.php?q=stat";
        $res = url_get($url);
        $miner_stats = json_decode($res, true);
    }
}

global $usdPrice, $btcPrice;

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
	        <?php if($addressTypes['is_stake']) { ?>
                <a href="/apps/explorer/address_info.php?address=<?php echo $address ?>&type=stake">
                    <span class="badge rounded-pill bg-pink font-size-12">Stake</span>
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

<?php if($type == "masternode") {

    $sql="select m.*, p.hostname, t.id as createtx_id, case when m.id <> t.dst then 1 else 0 end as cold,
        t.dst
        from  masternode m 
        left join peers p on m.ip = p.ip
        left join transactions t on m.height = t.height and t.type = 2 and ((t.dst = m.id and (t.message='mncreate' or t.message='')) or t.message = m.id)
           where m.id = :id";
    $mn = $db->row($sql, [":id" => $address]);

    $height = Block::getHeight();

    $rewardStat = Transaction::getMasternodeRewardsStat($address);

    ?>
    <h4>Masternode</h4>

    <table class="table table-sm table-striped">
        <tr>
            <td width="20%">IP</td>
            <td><?php echo $mn['ip'] ?></td>
        </tr>
        <tr>
            <td>Hostname</td>
            <td>
                <a href="<?php echo $mn['hostname'] ?>">
                    <?php echo $mn['hostname'] ?>
                </a>
            </td>
        </tr>
        <tr>
            <td>Height</td>
            <td>
                <a href="/apps/explorer/tx.php?id=<?php echo $mn['createtx_id'] ?>">
                    <?php echo $mn['height'] ?>
                </a>
                (<?php echo $height - $mn['height'] ?> blocks ago)
            </td>
        </tr>
        <tr>
            <td>Win height</td>
            <td>
                <?php echo $mn['win_height'] ?>
                (<?php echo $height - $mn['win_height'] ?> blocks ago)
            </td>
        </tr>
        <tr>
            <td>Collateral</td>
            <td><?php echo $mn['collateral'] ?></td>
        </tr>
        <?php if ($mn['cold']) {
            ?>
            <tr>
                <td>Reward address</td>
                <td>
                    <?php echo explorer_address_link($mn['dst']) ?>
                </td>
            </tr>
        <?php } ?>
    </table>

<?php } ?>

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
        <td><?php echo $txStat['tx_cnt']==0 ? 0 : num($txStat['total'] / $txStat['tx_cnt']) ?></td>
    </tr>
</table>

<?php if($type=="stake") { ?>
    <table class="table table-sm table-striped">
        <tr>
            <td width="20%">Stake duration</td>
            <td><?php echo round($txStat['days'],2) ?> day</td>
        </tr>
        <tr>
            <td>Start balance:</td>
            <td><?php echo $txStat['start_balance'] ?></td>
        </tr>
        <tr>
            <td>Monhly Roi:</td>
            <td><?php echo round($txStat['roi'],2) ?> %</td>
        </tr>
        <tr>
            <td>Average maturity:</td>
            <td><?php echo round($txStat['avg_maturity']) ?></td>
        </tr>
    </table>
<?php } ?>

<div class="row">
    <div class="col">
        <div class="card">
            <div class="card-body p-3">
                <h5>Daily</h5>
                <h3><?php echo num($rewardStat['total']['daily']) ?></h3>
                <h6><?php echo num($btcPrice * $rewardStat['total']['daily'],8) ?> ₿</h6>
                <h6><?php echo num($usdPrice * $rewardStat['total']['daily'],4) ?> $</h6>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card">
            <div class="card-body p-3">
                <h5>Weekly</h5>
                <h3><?php echo num($rewardStat['total']['weekly']) ?></h3>
                <h6><?php echo num($btcPrice * $rewardStat['total']['weekly'],8) ?> ₿</h6>
                <h6><?php echo num($usdPrice * $rewardStat['total']['weekly'],4) ?> $</h6>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card">
            <div class="card-body p-3">
                <h5>Monthly</h5>
                <h3><?php echo num($rewardStat['total']['monthly']) ?></h3>
                <h6><?php echo num($btcPrice * $rewardStat['total']['monthly'],8) ?> ₿</h6>
                <h6><?php echo num($usdPrice * $rewardStat['total']['monthly'],4) ?> $</h6>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card">
            <div class="card-body p-3">
                <h5>Yearly</h5>
                <h3><?php echo num($rewardStat['total']['yearly']) ?></h3>
                <h6><?php echo num($btcPrice * $rewardStat['total']['yearly'],8) ?> ₿</h6>
                <h6><?php echo num($usdPrice * $rewardStat['total']['yearly'],4) ?> $</h6>
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
