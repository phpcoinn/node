<?php
require_once dirname(__DIR__)."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Explorer");

global $_config;

$dapps_peers = Peer::getDappsPeers();
$dapps_peers_map = [];
foreach($dapps_peers as $dapp_peer) {
	$dapps_peers_map[$dapp_peer['dapps_id']]=$dapp_peer;
}

$dapps_folders = scandir(ROOT. "/dapps");

$dapps = [];
$localDappsData = Dapps::getLocalData();

foreach($dapps_folders as $dapps_folder) {
    if($dapps_folder == ".") continue;
    if($dapps_folder == "..") continue;
    $folder = ROOT. "/dapps/" . $dapps_folder;
    if(is_link($folder)) {
	    continue;
    }
	$dapps[] = [
        "dapps_id"=>$dapps_folder,
        "mtime"=>filemtime($folder),
        "local"=>$localDappsData['dapps_id']==$dapps_folder
    ];
}

?>

<?php
require_once __DIR__. '/../common/include/top.php';
?>


<ol class="breadcrumb m-0 ps-0 h4">
	<li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
	<li class="breadcrumb-item active">Dapps</li>
</ol>

<div class="table-responsive">
	<table class="table table-sm table-striped">
		<thead class="table-light">
			<tr>
				<th>DappsId</th>
				<th>Modified</th>
				<th>Hostname</th>
				<th>Ip</th>
				<th>Dapps hash</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($dapps as $dapp) {
                $hostname = null;
                $ip = null;
				$dappshash = null;
                if(isset($dapps_peers_map[$dapp['dapps_id']])) {
                    $dapp_peer = $dapps_peers_map[$dapp['dapps_id']];
	                $hostname = $dapp_peer['hostname'];
	                $ip = $dapp_peer['ip'];
	                $dappshash = $dapp_peer['dappshash'];
                }
                if ($dapp['local']) {
	                $hostname = $_config['hostname'];
	                $dappshash = $localDappsData['dappshash'];
                }

                ?>
				<tr>
					<td>
                        <a href="<?php echo Dapps::getLink($dapp['dapps_id']) ?>" target="_blank">
                            <?php echo $dapp['dapps_id'] ?>
                            <?php if ($dapp['local']) { ?>
                                <span class="badge rounded-pill badge-soft-secondary font-size-12">Local</span>
                            <?php } ?>
                        </a>
                    </td>
					<td><?php echo display_date($dapp['mtime']) ?></td>
					<td><?php echo $hostname ?></td>
					<td><?php echo $ip ?></td>
					<td><?php echo $dappshash ?></td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
</div>

<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
