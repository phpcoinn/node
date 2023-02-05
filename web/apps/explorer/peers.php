<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once ROOT. '/web/apps/explorer/include/functions.php';
define("PAGE", true);
define("APP_NAME", "Explorer");

$peers = Peer::getAll();
$current_height = Block::getHeight();

global $db;
$sql="select p.height, count(distinct p.block_id) as block_cnt
from peers p
where p.blacklisted < ".DB::unixTimeStamp()."
group by p.height
having block_cnt > 1
order by p.height desc";
$forked_peers = $db->run($sql);

$sql="select p.height, count(p.id) as peer_cnt
from peers p
where p.blacklisted < ".DB::unixTimeStamp()."
and ".DB::unixTimeStamp()."-p.ping < 60*60*2
group by p.height
order by p.height desc;";
$peers_by_height = $db->run($sql);

$peers_by_height_map = [];
foreach($peers_by_height as $peer) {
	$peers_by_height_map[$peer['height']]=$peer['peer_cnt'];
}

if(!isset($peers_by_height_map[$current_height])) {
	$peers_by_height_map[$current_height]="current";
}

krsort($peers_by_height_map);

$sql="select p.version, count(p.id) as peer_cnt
from peers p
where p.blacklisted < ".DB::unixTimeStamp()."
group by p.version
order by p.version desc";
$peers_by_version = $db->run($sql);
$show_all = isset($_GET['show_all']);
?>

<?php
require_once __DIR__. '/../common/include/top.php';
?>

<ol class="breadcrumb m-0 ps-0 h4">
    <li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
    <li class="breadcrumb-item active">Peers</li>
</ol>

<h3>Peers <span class="float-end badge bg-primary"><?php echo count($peers) ?></span> </h3>

<div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead class="table-light">
            <tr>
                <th>Hostname</th>
                <th>Ip</th>
                <th>Ping</th>
                <th>Height</th>
                <th>Version</th>
                <?php if(FEATURE_APPS) { ?>
                    <th>Apps hash</th>
                <?php } ?>
                <?php if($show_all) { ?>
                    <th>Blacklisted</th>
                    <th>Blacklist reason</th>
                <?php } ?>
                <th>Score</th>
            </tr>
        </thead>
        <tbody>
            <?php
                $blacklisted_cnt = 0;
                foreach($peers as $peer) {
                $blacklisted = $peer['blacklisted'] > time();
                if($blacklisted) {
	                $blacklisted_cnt++;
                    if(!$show_all) {
                    continue;
                }
                }
                $color = '';
                $latest_version = version_compare($peer['version'], VERSION.".".BUILD_VERSION) >= 0;
                $blocked_version = version_compare($peer['version'], MIN_VERSION) < 0;
                $color = $latest_version ? 'success' : ($blocked_version ? 'danger' : '');
                $live = (time() - $peer['ping']) < Peer::PEER_PING_MAX_MINUTES * 60;
                $bg_color = $blacklisted ? 'table-danger' : ($live ? 'table-success' : '') ;
                ?>
                <tr class="<?php echo $show_all ? $bg_color : '' ?>">
                    <td>
                        <a href="/apps/explorer/peer.php?id=<?php echo $peer['id'] ?>"><?php echo $peer['hostname'] ?></a>
                        <a href="<?php echo $peer['hostname'] ?>" target="_blank" class="float-end"
                           data-bs-toggle="tooltip" data-bs-placement="top" title="Open in new window">
                            <span class="fa fa-external-link-alt"></span>
                        </a>
                    </td>
                    <td><?php echo $peer['ip'] ?></td>
                    <td>
                        <?php echo display_date($peer['ping']) ?>
                        <?php echo $show_all ? " | " .durationFormat(time() - $peer['ping']) : ''?>
                    </td>
                    <td><?php echo $peer['height'] ?></td>
                    <td>
                        <span class="<?php if (!empty($color)) { ?>text-<?php echo $color ?><?php } ?>"><?php echo $peer['version'] ?></span>
                    </td>
	                <?php if(FEATURE_APPS) { ?>
                        <td class="">
                            <?php if($peer['appshash']) { ?>
                                <?php echo truncate_hash($peer['appshash']) ?>
                                <div class="app-hash">
                                    <?php echo hashimg($peer['appshash'], "Apps hash: ". $peer['appshash']) ?>
                                </div>
                            <?php } ?>
                        </td>
	                <?php } ?>
	                <?php if($show_all) { ?>
                        <td>
                            <?php echo display_date($peer['blacklisted']) ?>
                            <?php if ($peer['blacklisted'] > time()) {
                                echo " | " . durationFormat($peer['blacklisted']-time());
                            } ?>
                        </td>
                        <td><?php echo $peer['blacklist_reason'] ?></td>
                    <?php } ?>
                    <td>
                        <?php if ($peer['score']) { ?>
                            <div class="ns">
                                <div class="progress progress-lg node-score me-1">
                                    <div class="progress-bar bg-<?php echo ($peer['score'] < MIN_NODE_SCORE / 2 ? 'danger' : ($peer['score'] < MIN_NODE_SCORE ? 'warning' : 'success')) ?>" role="progressbar" style="width: <?php echo $peer['score'] ?>%;" aria-valuenow="<?php echo $peer['score'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <?php echo round($peer['score'],2) ?>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php if ($blacklisted_cnt> 0) { ?>
    <div><?php echo $blacklisted_cnt ?> blacklisted</div>
<?php } ?>
<div>Node score: <?php echo round($_config['node_score'],2); ?>%</div>

<hr/>
<div class="row">
    <div class="col-4">
        <h4>Forked peers</h4>
        <table class="table table-sm table-striped">
            <thead class="table-light">
            <tr>
                <th>Height</th>
                <th>Different Blocks</th>
            </tr>
            </thead>
            <tbody>
			<?php foreach ($forked_peers as $forked_peer) { ?>
                <tr>
                    <td><?php echo $forked_peer['height'] ?></td>
                    <td><?php echo $forked_peer['block_cnt'] ?></td>
                </tr>
			<?php } ?>
            </tbody>
        </table>
    </div>
    <div class="col-4">
        <h4>Peers by height</h4>
        <table class="table table-sm table-striped">
            <thead class="table-light">
            <tr>
                <th>Height</th>
                <th>Peers</th>
            </tr>
            </thead>
            <tbody>
			<?php foreach ($peers_by_height_map as $height => $cnt) { ?>
                <tr class="<?php if ($height == $current_height) { ?>table-success<?php } ?>">
                    <td>
                        <?php echo $height ?>
                    </td>
                    <td><?php echo $cnt ?></td>
                </tr>
			<?php } ?>
            </tbody>
        </table>
    </div>
    <div class="col-4">
        <h4>Peers by version</h4>
        <table class="table table-sm table-striped">
            <thead class="table-light">
            <tr>
                <th>Version</th>
                <th>Peers</th>
            </tr>
            </thead>
            <tbody>
			<?php foreach ($peers_by_version as $peer) { ?>
                <tr>
                    <td><?php echo $peer['version'] ?></td>
                    <td><?php echo $peer['peer_cnt'] ?></td>
                </tr>
			<?php } ?>
            </tbody>
        </table>
    </div>
</div>


<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
<style>
    .app-hash, .ns {
        display:inline-block;
    }
</style>
