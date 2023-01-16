<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once ROOT. '/web/apps/explorer/include/functions.php';
define("PAGE", true);
define("APP_NAME", "Explorer");

if(!isset($_GET['id'])) {
    header("location: /apps/explorer");
    exit;
}

$id = $_GET['id'];

function PeergetById($id)
{
    global $db;
    $sql="select * from peers p where p.id = :id";
    return $db->row($sql, [":id"=>$id]);
}


$sel_peer = PeergetById($id);



if(!$sel_peer) {
	header("location: /apps/explorer");
	exit;
}

$ip=$sel_peer['ip'];
$url = "https://node1.phpcoin.net/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/nodemap/api.php?q=getLocationInfo&ip=$ip";
$locationData = json_decode(file_get_contents($url), true);

$color = '';
$latest_version = version_compare($sel_peer['version'], VERSION.".".BUILD_VERSION) >= 0;
$blocked_version = version_compare($sel_peer['version'], MIN_VERSION) < 0;
$color = $latest_version ? 'success' : ($blocked_version ? 'danger' : '');

$current = Block::current();
?>

<?php
require_once __DIR__. '/../common/include/top.php';
unset($peer);
$peer = $sel_peer;
?>
<ol class="breadcrumb m-0 ps-0 h4">
	<li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
	<li class="breadcrumb-item"><a href="/apps/explorer/peers.php">Peers</a></li>
	<li class="breadcrumb-item active"><?php echo $peer['hostname'] ?></li>
</ol>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.8.0/dist/leaflet.css"
      integrity="sha512-hoalWLoI8r4UszCkZ5kL8vayOGVae1oxXe/2A4AO6J9+580uKHDO3JdHb7NzwwzK5xr/Fs0W40kiNHxM9vyTtQ=="
      crossorigin=""/>


<div class="table-responsive">
    <table class="table table-sm table-striped">
        <tr>
            <td><strong>ID</strong></td>
            <td><?php echo $peer['id'] ?></td>
        </tr>
        <tr>
            <td><strong>Hostname</strong></td>
            <td>
                <?php echo $peer['hostname'] ?>
                <a href="<?php echo $peer['hostname'] ?>" target="_blank" class="ms-2"
                   data-bs-toggle="tooltip" data-bs-placement="top" title="Open in new window">
                    <span class="fa fa-external-link-alt"></span>
                </a>
            </td>
        </tr>
        <tr>
            <td><strong>IP</strong></td>
            <td><?php echo $peer['ip'] ?></td>
        </tr>
        <tr>
            <td><strong>Status</strong></td>
            <td>
                <?php if($peer['blacklisted'] > time()) { ?>
                    <div class="badge rounded-pill bg-danger font-size-12">Blacklisted</div>
                    <span class="text-muted ps-2">Blacklisted until <?php echo display_date($peer['blacklisted']) ?>
                        (for <?php echo durationFormat($peer['blacklisted'] - time()) ?>)</span>
	                <?php if (!empty($peer['blacklist_reason'])) { ?>
                        <span class="text-muted ps-2"><?php echo $peer['blacklist_reason'] ?></span>
	                <?php } ?>
                <?php } else {?>
                    <div class="badge rounded-pill bg-success font-size-12">Active</div>
	                <?php if (!empty($peer['blacklisted'])) { ?>
                        <span class="text-muted ps-2">Last blacklisted <?php echo display_date($peer['blacklisted']) ?>
                        (<?php echo durationFormat(time()-$peer['blacklisted']) ?> ago)</span>
	                <?php } ?>
	                <?php if (!empty($peer['blacklist_reason'])) { ?>
                        <span class="text-muted ps-2"><?php echo $peer['blacklist_reason'] ?></span>
	                <?php } ?>
                <?php } ?>
                <br/>
                <strong>Reserved: </strong>
                <?php if($peer['reserved']) { ?>
                    <span class="badge rounded-pill bg-success">Yes</span>
                <?php } else { ?>
                    <span class="badge rounded-pill bg-danger">No</span>
                <?php } ?>
                <strong>Fails: </strong>
                <span class="badge rounded-pill bg-<?php if ($peer['fails']>0) { ?>danger<?php } else { ?>success<?php } ?>">
                    <?php echo $peer['fails'] ?>
                </span>
                <strong>Stuckfails: </strong>
                <span class="badge rounded-pill bg-<?php if ($peer['stuckfail']>0) { ?>danger<?php } else { ?>success<?php } ?>">
                    <?php echo $peer['stuckfail'] ?>
                </span>
            </td>
        </tr>
        <tr>
            <td><strong>Location</strong></td>
            <td>
                <div class="d-flex">
                    <div id="map" style="width: 300px; height: 150px"></div>
                    <div class="ms-5">
                        <table>
                            <tr>
                                <td class="pe-5"><strong>Position</strong></td>
                                <td>
                                    <a class="me-2" target="_blank" href="https://www.google.com/maps/search/?api=1&query=<?php echo $locationData['latitude'] ?>,<?php echo $locationData['longitude'] ?>">
                                        <?php echo $locationData['latitude'] ?>, <?php echo $locationData['longitude'] ?>
                                        <span class="fa fa-external-link-alt"></span>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Country</strong></td>
                                <td><?php echo $locationData['country_name'] ?> (<?php echo $locationData['country_code'] ?>)</td>
                            </tr>
                            <tr>
                                <td><strong>Region</strong></td>
                                <td><?php echo $locationData['region_name'] ?></td>
                            </tr>
                            <tr>
                                <td><strong>City</strong></td>
                                <td><?php echo $locationData['city_name'] ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td><strong>Ping</strong></td>
            <td>
                <?php echo display_date($peer['ping']) ?>
                (<?php echo durationFormat(time()-$peer['ping']) ?> ago)
            </td>
        </tr>
        <tr>
            <td><strong>Height</strong></td>
            <td>
                <a href="/apps/explorer/block.php?height=<?php echo $peer['height'] ?>" class="text-<?php if ($current['height'] > $peer['height']) { ?>danger<?php } else { ?>success<?php } ?>">
                    <?php echo $peer['height'] ?>
                </a>
            </td>
        </tr>
        <tr>
            <td><strong>Block</strong></td>
            <td>
                <?php echo explorer_block_link($peer['block_id']) ?>
            </td>
        </tr>
        <tr>
            <td><strong>Apps hash</strong></td>
            <td>
                <div class="d-flex">
                    <div class="app-hash me-5">
                        <?php echo hashimg($peer['appshash'], "Apps hash: ". $peer['appshash']) ?>
                    </div>
                    <?php echo $peer['appshash'] ?>
                </div>
            </td>
        </tr>
        <tr>
            <td><strong>Score</strong></td>
            <td>
                <div class="d-flex">
                    <div class="ns me-5">
                        <div class="progress progress-lg node-score me-1">
                            <div class="progress-bar bg-<?php echo ($peer['score'] < MIN_NODE_SCORE / 2 ? 'danger' : ($peer['score'] < MIN_NODE_SCORE ? 'warning' : 'success')) ?>" role="progressbar" style="width: <?php echo $peer['score'] ?>%;" aria-valuenow="<?php echo $peer['score'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <?php echo round($peer['score'],2) ?>
                </div>
            </td>
        </tr>
        <tr>
            <td><strong>Version</strong></td>
            <td>
                <span class="<?php if (!empty($color)) { ?>text-<?php echo $color ?><?php } ?>"><?php echo $peer['version'] ?></span>
            </td>
        </tr>
        <tr>
            <td><strong>Generator</strong></td>
            <td>
                <?php if (!empty($peer['generator'])) { ?>
                    <?php echo explorer_address_link($peer['generator']) ?>
                <?php } else { ?>
                    <span class="badge rounded-pill bg-danger">No</span>
                <?php } ?>
            </td>
        </tr>
        <tr>
            <td><strong>Miner</strong></td>
            <td>
                <?php if (!empty($peer['miner'])) { ?>
                    <?php echo explorer_address_link($peer['miner']) ?>
                <?php } else { ?>
                    <span class="badge rounded-pill bg-danger">No</span>
                <?php } ?>
            </td>
        </tr>
        <tr>
            <td><strong>Masternode</strong></td>
            <td>
                <?php if (!empty($peer['masternode'])) { ?>
                    <?php echo explorer_address_link($peer['masternode']) ?>
                <?php } else { ?>
                    <span class="badge rounded-pill bg-danger">No</span>
                <?php } ?>
            </td>
        </tr>
        <tr>
            <td><strong>Reponses</strong></td>
            <td>
                <?php
                $avg = null;
                if($peer['response_cnt'] > 0) {
                    $avg = $peer['response_time'] / $peer['response_cnt'];
                }
                ?>
                <?php if(!empty($avg)) { ?>
                    <span>Average: <?php echo number_format($avg, 2) ?> seconds</span>
                <?php } ?>
                <span>Total: <?php echo number_format($peer['response_time'],2) ?></span>
                <span>Count: <?php echo $peer['response_cnt'] ?></span>
            </td>
        </tr>
        <tr>
            <td><strong>Dapps</strong></td>
            <td>
                <?php if(!empty($peer['dapps_id'])) { ?>
                    <a href="/dapps.php?url=<?php echo $peer['dapps_id'] ?>" target="_blank">
                        <?php echo $peer['dapps_id'] ?>
                        <span class="fa fa-external-link-alt"></span>
                    </a>
                <?php } ?>
	            <?php if(!empty($peer['dappshash'])) { ?>
                    <br/>
                    Hash: <?php echo $peer['dappshash'] ?>
	            <?php } ?>
            </td>
        </tr>
    </table>
</div>


<script src="https://unpkg.com/leaflet@1.8.0/dist/leaflet.js"
        integrity="sha512-BB3hKbKWOc9Ez/TAwyWxNXeoV9c1v6FIeYiBieIWkpLjauysF18NzgR1MBNBXf8/KABdlkX68nAhlwcDFLGPCQ=="
        crossorigin=""></script>

<script type="text/javascript">
    let point = [<?php echo $locationData['latitude'] ?>, <?php echo $locationData['longitude'] ?>]
    let mapOptions = {
        center: point,
        zoom: 10
    }
    let map = new L.map('map', mapOptions);
    let layer = new L.TileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
    map.addLayer(layer);
    let marker = L.marker(point)
    map.addLayer(marker);
</script>

<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
