<?php

if (!defined("ADMIN_TAB")) {
	exit;
}

global $action, $db;

if($action == "daemon_unlock") {
	$daemon = $_GET['daemon'];
	$cmd = "php ".ROOT."/cli/$daemon.php --daemon stop &";
	$res = shell_exec($cmd);
	header("location: ".APP_URL."/?view=server");
	exit;
}
if($action == "daemon_restart") {
	$daemon = $_GET['daemon'];
	$cmd = "php ".ROOT."/cli/$daemon.php --daemon kill &";
	_log("ADMIN SERVER action=$action cmd=$cmd");
	$res = shell_exec($cmd);
	header("location: ".APP_URL."/?view=server");
	exit;
}

if($action == "daemon_enable") {
	$daemon = $_GET['daemon'];
	$cmd = "php ".ROOT."/cli/$daemon.php --daemon enable &";
	_log("Daemon: call cmd $cmd");
	$res = shell_exec($cmd);
	header("location: ".APP_URL."/?view=server");
	exit;
}

if($action == "daemon_disable") {
	$daemon = $_GET['daemon'];
	$cmd = "php ".ROOT."/cli/$daemon.php --daemon disable &";
	$res = shell_exec($cmd);
	header("location: ".APP_URL."/?view=server");
	exit;
}

if($action == "daemon_stop") {
	$daemon = $_GET['daemon'];
	$cmd = "php ".ROOT."/cli/$daemon.php --daemon stop &";
	$res = shell_exec($cmd);
	header("location: ".APP_URL."/?view=server");
	exit;
}

//TODO: replace @1.0.6.85
if(method_exists(Nodeutil::class, 'getServerData')) {
    $serverData=Nodeutil::getServerData();
} else {
	$serverData = [];
	$serverData['hostname']=gethostname();
	$minerStatFile = NodeMiner::getStatFile();
	if(file_exists($minerStatFile)) {
		$minerStat = file_get_contents($minerStatFile);
		$minerStat = json_decode($minerStat, true);
	}
// Linux CPU
	$load = sys_getloadavg();
	$cpuload = $load[0];
// Linux MEM
	$free = shell_exec('free');
	$free = (string)trim($free);
	$free_arr = explode("\n", $free);
	$mem = explode(" ", $free_arr[1]);
	$mem = array_filter($mem, function($value) { return ($value !== null && $value !== false && $value !== ''); }); // removes nulls from array
	$mem = array_merge($mem); // puts arrays back to [0],[1],[2] after
	$memtotal = round($mem[1] / 1000000,2);
	$memused = round($mem[2] / 1000000,2);
	$memfree = round($mem[3] / 1000000,2);
	$memshared = round($mem[4] / 1000000,2);
	$memcached = round($mem[5] / 1000000,2);
	$memavailable = round($mem[6] / 1000000,2);
// Linux Connections
	$connections = `netstat -ntu | grep :80 | grep ESTABLISHED | grep -v LISTEN | awk '{print $5}' | cut -d: -f1 | sort | uniq -c | sort -rn | grep -v 127.0.0.1 | wc -l`;
	$totalconnections = `netstat -ntu | grep :80 | grep -v LISTEN | awk '{print $5}' | cut -d: -f1 | sort | uniq -c | sort -rn | grep -v 127.0.0.1 | wc -l`;

	$memusage = round(($memavailable/$memtotal)*100);
	$phpload = round(memory_get_usage() / 1000000,2);
	$diskfree = round(disk_free_space(".") / 1000000000);
	$disktotal = round(disk_total_space(".") / 1000000000);
	$diskused = round($disktotal - $diskfree);
	$diskusage = round($diskused/$disktotal*100);

	$serverData['stat']['memusage']=$memusage;
	$serverData['stat']['cpuload']=$cpuload;
	$serverData['stat']['diskusage']=$diskusage;
	$serverData['stat']['connections']=$connections;
	$serverData['stat']['totalconnections']=$totalconnections;
	$serverData['stat']['memtotal']=$memtotal;
	$serverData['stat']['memused']=$memused;
	$serverData['stat']['memavailable']=$memavailable;
	$serverData['stat']['diskfree']=$diskfree;
	$serverData['stat']['diskused']=$diskused;
	$serverData['stat']['disktotal']=$disktotal;
	$serverData['stat']['phpload']=$phpload;
}



?>
<div class="h4">Hostname: <?php echo $serverData['hostname'] ?></div>
<hr/>

<div class="row">
	<div class="col-sm-6">
		<dl class="row">
			<dt class="col-sm-6">CPU Usage:</dt>
			<dd class="col-sm-6 d-flex align-items-center">
				<div><?php echo $serverData['stat']['cpuload'] ?> %</div>
				<div class="progress flex-grow-1 mx-2">
					<div class="progress-bar bg-info" role="progressbar"
					     style="width: <?php echo $serverData['stat']['cpuload'] ?>%" aria-valuenow="<?php echo $serverData['stat']['cpuload'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
				</div>
			</dd>
			<dt class="col-sm-6">Hard Disk Usage:</dt>
			<dd class="col-sm-6 d-flex align-items-center">
				<div><?php echo $serverData['stat']['diskusage'] ?> %</div>
				<div class="progress flex-grow-1 mx-2">
					<div class="progress-bar bg-info" role="progressbar"
					     style="width: <?php echo $serverData['stat']['diskusage'] ?>%" aria-valuenow="<?php echo $serverData['stat']['diskusage'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
				</div>
			</dd>
			<dt class="col-sm-6">Hard Disk Total:</dt>
			<dd class="col-sm-6"><?php echo $serverData['stat']['disktotal'] ?> GB</dd>
			<dt class="col-sm-6">Hard Disk Used:</dt>
			<dd class="col-sm-6"><?php echo $serverData['stat']['diskused'] ?> GB</dd>
			<dt class="col-sm-6">Hard Disk Free:</dt>
			<dd class="col-sm-6"><?php echo $serverData['stat']['diskfree'] ?> GB</dd>
			<dt class="col-sm-6">Established Connections:</dt>
			<dd class="col-sm-6"><?php echo $serverData['stat']['connections'] ?></dd>
		</dl>
	</div>
	<div class="col-sm-6">
		<dl class="row">
			<dt class="col-sm-6">PHP Load:</dt>
			<dd class="col-sm-6 d-flex align-items-center">
				<div><?php echo $serverData['stat']['phpload'] ?> %</div>
				<div class="progress flex-grow-1 mx-2">
					<div class="progress-bar bg-info" role="progressbar"
					     style="width: <?php echo $serverData['stat']['phpload'] ?>%" aria-valuenow="<?php echo $serverData['stat']['phpload'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
				</div>
			</dd>
			<dt class="col-sm-6">RAM Usage:</dt>
			<dd class="col-sm-6 d-flex align-items-center">
				<div><?php echo $serverData['stat']['memusage'] ?> %</div>
				<div class="progress flex-grow-1 mx-2">
					<div class="progress-bar bg-info" role="progressbar"
					     style="width: <?php echo $serverData['stat']['memusage'] ?>%" aria-valuenow="<?php echo $serverData['stat']['memusage'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
				</div>
			</dd>
			<dt class="col-sm-6">RAM Total:</dt>
			<dd class="col-sm-6"><?php echo $serverData['stat']['memtotal'] ?> GB</dd>
			<dt class="col-sm-6">RAM Used:</dt>
			<dd class="col-sm-6"><?php echo $serverData['stat']['memused'] ?> GB</dd>
			<dt class="col-sm-6">RAM Available:</dt>
			<dd class="col-sm-6"><?php echo $serverData['stat']['memavailable'] ?> GB</dd>
			<dt class="col-sm-6">Total Connections:</dt>
			<dd class="col-sm-6"><?php echo $serverData['stat']['totalconnections'] ?></dd>
		</dl>
	</div>
</div>

<hr/>

<?php
//TODO: replace @1.0.6.85
if(method_exists(Daemon::class, "availableDaemons")) {
	$daemons = Daemon::availableDaemons();
} else {
    $daemons = ["dapps", "miner", "sync","masternode"];
}
?>

<div class="row">
	<?php foreach($daemons as $daemon) {
		$status = daemon_get_status($daemon);
		$running = $status['running'];
		$locked = $status['locked'];
		$enabled = $status['enabled'];
		$error = $status['error'];
		?>
		<div class="col-md-6 col-lg-4">
			<div class="card">
				<div class="card-header flex-row d-flex justify-content-between flex-wrap align-content-center">
					<h4 class="card-title"><?php echo $status['name'] ?></h4>
					<div>
						<?php if ($enabled) { ?>
							<span class="badge bg-success">Enabled</span>
						<?php } else { ?>
							<span class="badge bg-danger">Disabled</span>
						<?php } ?>
						<?php if ($running) { ?>
							<span class="badge bg-success">Running</span>
						<?php } else { ?>
							<span class="badge bg-danger">Stopped</span>
						<?php } ?>
						<?php if ($locked) { ?>
							<span class="badge bg-success">Locked</span>
						<?php } else { ?>
							<span class="badge bg-danger">No lock</span>
						<?php } ?>
						<a href="#" class="ml-2" role="button" data-bs-toggle="collapse" data-bs-target="#details_<?php echo $daemon ?>">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle-fill" viewBox="0 0 16 16">
								<path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
							</svg>
						</a>
						<?php if ($error) { ?>
							<span class="text-warning" title="<?php echo $error ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-triangle-fill" viewBox="0 0 16 16">
                                            <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                                        </svg>
                                    </span>
						<?php } ?>
					</div>
				</div>
				<div class="card-body p-0 border-bottom collapse" id="details_<?php echo $daemon ?>">
					<div class="row p-4">
						<div class="col-sm-12">
							<div class="flex-row d-flex justify-content-between flex-wrap">
								<div>Started:</div>
								<div><?php echo display_date($status['started']) ?></div>
							</div>
							<div class="flex-row d-flex justify-content-between flex-wrap">
								<div>Running:</div>
								<div><?php if (!empty($status['started'])) echo date("H:i:s", time() - $status['started']) ?></div>
							</div>
							<div class="flex-row d-flex justify-content-between flex-wrap">
								<div>PID:</div>
								<div><?php echo $status['pid'] ?></div>
							</div>
							<div class="flex-row d-flex justify-content-between flex-wrap">
								<div>CPU:</div>
								<div><?php echo $status['cpu'] ?></div>
							</div>
							<div class="flex-row d-flex justify-content-between flex-wrap">
								<div>Memory:</div>
								<div><?php echo $status['memory'] ?></div>
							</div>
							<div class="flex-row d-flex justify-content-between flex-wrap">
								<div>Owner:</div>
								<div><?php echo $status['owner'] ?></div>
							</div>
							<div class="flex-row d-flex justify-content-between flex-wrap">
								<div>Locked time:</div>
								<div><?php echo display_date($status['locked_time']) ?></div>
							</div>
							<div class="flex-row d-flex justify-content-between flex-wrap">
								<div>Lock file:</div>
								<div><?php echo $status['lock_file'] ?></div>
							</div>
							<div class="flex-row d-flex justify-content-between flex-wrap">
								<div>Lock owner:</div>
								<div><?php echo $status['lock_owner'] ?></div>
							</div>
						</div>
					</div>
				</div>
				<?php if ($daemon == "miner") { ?>
					<div class="card-body">
						<p class="card-text">Address: <?php echo explorer_address_link(Account::getAddress($_config['miner_public_key'])) ?></p>
						<div class="row">
							<div class="col-sm-12">
								<?php if ($minerStat) { ?>
									<div><strong>Miner stat:</strong></div>
									<div class="flex-row d-flex justify-content-between flex-wrap">
										<div>Started:</div>
										<div><?php echo display_date($minerStat['started']) ?></div>
									</div>
									<div class="flex-row d-flex justify-content-between flex-wrap">
										<div>Hashes:</div>
										<div><?php echo $minerStat['hashes'] ?></div>
									</div>
									<div class="flex-row d-flex justify-content-between flex-wrap">
										<div>Submits:</div>
										<div><?php echo $minerStat['submits'] ?></div>
									</div>
									<div class="flex-row d-flex justify-content-between flex-wrap">
										<div>Accepted:</div>
										<div><?php echo $minerStat['accepted'] ?></div>
									</div>
									<div class="flex-row d-flex justify-content-between flex-wrap">
										<div>Rejected:</div>
										<div><?php echo $minerStat['rejected'] ?></div>
									</div>
								<?php } ?>

							</div>
						</div>
					</div>
				<?php } ?>
				<?php if ($daemon == "masternode") { ?>
					<div class="card-body">
						<?php if($running) { ?>
							<p class="card-text">
							<?php if (Masternode::isLocalMasternode()) {

								$mn = Masternode::get($_config['masternode_public_key']);
								if(!$mn) {
									$valid = false;
									$err = "Masternode not found in list";
								} else {
									$mn = Masternode::fromDB($mn);
									$height = Block::getHeight();
									$valid = $mn->check($height, $err);

									global $db;
									$sql="select count(t.id) as cnt, sum(t.val) as value, 
                                                    (max(t.date) - min(t.date))/60/60/24 as running,
                                                   sum(t.val)  / ((max(t.date) - min(t.date))/60/60/24) as daily
                                            from
                                            transactions t
                                            where t.dst = :id
                                            and t.type = 0 and t.message = 'masternode'";
									$mnStat = $db->row($sql, [':id'=>$mn->id]);
								}

								?>

								<?php if (!$valid) { ?>
									<div class="alert alert-danger">
										<strong>Masternode is invalid!</strong>
										<br/>
										<?php echo $err ?>
									</div>
								<?php } ?>

								Address:  <?php echo explorer_address_link(Account::getAddress($_config['masternode_public_key'])) ?>

								<?php if ($mnStat) { ?>
									<br/>
									Total rewards: <?php echo $mnStat['cnt'] ?><br/>
									Total value: <?php echo $mnStat['value'] ?><br/>
									Running days: <?php echo $mnStat['running'] ?><br/>
									Daily income: <?php echo $mnStat['daily'] ?><br/>
								<?php } ?>

							<?php } ?>
							<br/>
							Started: <?php echo display_date($status['started']) ?>
							<br/>
							Running: <?php echo round((time() - $status['started']) / 60) ?> min
							<br/>
							</p>
						<?php } ?>
					</div>
				<?php } ?>
				<?php if ($daemon == "dapps") {
					$dapps_public_key = $_config['dapps_public_key'];
					$dapps_id = Account::getAddress($dapps_public_key);
					$dapps_folder = Dapps::getDappsDir() . "/$dapps_id";
					$folder_exists = file_exists($dapps_folder);
					?>
					<div class="card-body">
						<div class="flex-row d-flex justify-content-between flex-wrap">
							<div>Address:</div>
							<div><?php echo explorer_address_link($dapps_id) ?></div>
						</div>
						<div class="flex-row d-flex justify-content-between flex-wrap">
							<div>Folder:</div>
							<div class="text-break"><?php echo $dapps_folder ?></div>
						</div>
						<div class="flex-row d-flex justify-content-between flex-wrap">
							<div>Folder exists:</div>
							<div><?php echo $folder_exists ? "Yes" : "No" ?></div>
						</div>
						<div class="flex-row d-flex justify-content-between flex-wrap">
							<div>Dapps URL:</div>
							<div>
								<a href="/dapps/<?php echo $dapps_id ?>">/dapps/<?php echo $dapps_id ?></a>
							</div>
						</div>
					</div>
				<?php } ?>
				<div class="card-footer bg-transparent border-top text-muted d-flex justify-content-between">
					<div>
						<?php if ($enabled) { ?>
							<a href="/apps/admin/?view=server&daemon=<?php echo $daemon ?>&action=daemon_disable" class="btn btn-sm btn-danger">Disable</a>
						<?php } else { ?>
							<a href="/apps/admin/?view=server&daemon=<?php echo $daemon ?>&action=daemon_enable" class="btn btn-sm btn-success">Enable</a>
						<?php } ?>
					</div>
					<div>
						<?php if ($enabled) { ?>
							<a href="/apps/admin/?view=server&daemon=<?php echo $daemon ?>&action=daemon_restart" class="btn btn-sm btn-danger">Kill</a>
						<?php } ?>
						<?php if ($locked && !$running) { ?>
							<a href="/apps/admin/?view=server&daemon=<?php echo $daemon ?>&action=daemon_unlock" class="btn btn-sm btn-danger">Unlock</a>
						<?php } ?>
						<?php if ($running) { ?>
							<a href="/apps/admin/?view=server&daemon=<?php echo $daemon ?>&action=daemon_stop" class="btn btn-sm btn-warning">Stop</a>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	<?php } ?>
</div>
