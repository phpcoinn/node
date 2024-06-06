<?php

if (!defined("ADMIN_TAB")) {
	exit;
}

global $action, $db;

if($action == "task_enable") {
	$task = $_GET['task'];
    $task::enable();
	header("location: ".APP_URL."/?view=server");
	exit;
}

if($action == "task_disable") {
    $task = $_GET['task'];
    $task::disable();
	header("location: ".APP_URL."/?view=server");
	exit;
}

if($action == "task_stop") {
    $task = $_GET['task'];
    $name = $task::$name;
	$cmd = "php ".ROOT."/cli/$name.php --stop";
	$res = shell_exec($cmd);
	header("location: ".APP_URL."/?view=server");
	exit;
}

$serverData=Nodeutil::getServerData();



?>
<div class="h4">Hostname: <?php echo $serverData['hostname'] ?></div>
<div class="h5">Root folder: <?php echo ROOT ?></div>
<hr/>

<div class="row">
	<div class="col-sm-6">
		<dl class="row">
			<dt class="col-sm-6">CPU Usage:</dt>
			<dd class="col-sm-6 d-flex align-items-center">
				<div><?php echo round($serverData['stat']['cpuload'],2) ?> %</div>
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
//error_reporting(E_ALL);
//ini_set('display_errors', true);
$tasks = Task::availableTasks();

$res=Nodeutil::psAux("php ".ROOT ."/", 1, "ps -e -o user,pid,ppid,pcpu,pmem,lstart,cmd");

?>

<div class="h4">Processes</div>

<table class="table table-striped table-sm">
    <thead>
        <tr>
            <th>USER</th>
            <th>PID</th>
            <th>PPID</th>
            <th>CPU</th>
            <th>MEM</th>
            <th>START</th>
            <th>COMMAND</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($res as $line) {
            $line = trim($line);
            $arr = preg_split("/\s+/", $line);
            ?>
            <tr>
                <?php for ($i=0; $i<5; $i++) { ?>
                    <td><?php echo $arr[$i] ?></td>
                <?php } ?>
                <td><?php echo implode(" ", array_slice($arr, 5, 5)) ?></td>
                <td><?php echo implode(" ", array_slice($arr, 10, count($arr)-10)) ?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<div class="h4">Tasks</div>

<div class="row">
    <?php foreach($tasks as $task) {
        $name=$task::$name;
        $taskStatus = $task::getTaskStatus();
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card">
                <div class="card-header flex-row d-flex justify-content-between flex-wrap align-content-center">
                    <h4 class="card-title"><?php echo $taskStatus['title'] ?></h4>
                    <div>
                        <?php if ($taskStatus['enabled']) { ?>
                            <span class="badge bg-success">Enabled</span>
                        <?php } else { ?>
                            <span class="badge bg-danger">Disabled</span>
                        <?php } ?>
                        <?php if ($taskStatus['running']) { ?>
                            <span class="badge bg-success">Running</span>
                        <?php } else { ?>
                            <span class="badge bg-secondary">Idle</span>
                        <?php } ?>
                        <a href="#" class="ml-2" role="button" data-bs-toggle="collapse" data-bs-target="#details_<?php echo $name ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle-fill" viewBox="0 0 16 16">
                                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0 border-bottom collapse" id="details_<?php echo $name ?>">
                    <div class="row p-4">
                        <div class="col-sm-12">
                            <?php if($taskStatus['running']) { ?>
                                <div class="flex-row d-flex justify-content-between flex-wrap">
                                    <div>Started:</div>
                                    <div><?php echo display_date($taskStatus['process']['started']) ?></div>
                                </div>
                                <div class="flex-row d-flex justify-content-between flex-wrap">
                                    <div>Running:</div>
                                    <div><?php if (!empty($taskStatus['process']['started'])) echo date("H:i:s", time() - $taskStatus['process']['started']) ?></div>
                                </div>

                                <div class="flex-row d-flex justify-content-between flex-wrap">
                                    <div>PID:</div>
                                    <div><?php echo $taskStatus['process']['pid'] ?></div>
                                </div>
                                <div class="flex-row d-flex justify-content-between flex-wrap">
                                    <div>CPU:</div>
                                    <div><?php echo $taskStatus['process']['cpu'] ?></div>
                                </div>
                                <div class="flex-row d-flex justify-content-between flex-wrap">
                                    <div>Memory:</div>
                                    <div><?php echo $taskStatus['process']['memory'] ?></div>
                                </div>
                                <div class="flex-row d-flex justify-content-between flex-wrap">
                                    <div>Owner:</div>
                                    <div><?php echo $taskStatus['process']['owner'] ?></div>
                                </div>
                            <?php } else {
                                $elapsed=date("H:i:s", time() - $taskStatus['last_run_time']);
                                ?>
                                <div class="flex-row d-flex justify-content-between flex-wrap">
                                    <div>Last run time:</div>
                                    <div>
                                        <?php echo display_date($taskStatus['last_run_time']) ?>
                                    </div>
                                </div>
                                <div class="flex-row d-flex justify-content-between flex-wrap">
                                    <div>Since last run:</div>
                                    <div>
                                        <?php echo !empty($taskStatus['last_run_time']) ? $elapsed : '' ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php if (file_exists(__DIR__."/task_admin_$name.php")) require_once __DIR__."/task_admin_$name.php" ?>
                <div class="card-footer bg-transparent border-top text-muted d-flex justify-content-between">
                    <div>
                        <?php if ($task::canDisable()) { ?>
                            <?php if ($taskStatus['enabled']) { ?>
                                <a href="/apps/admin/?view=server&task=<?php echo $task ?>&action=task_disable" class="btn btn-sm btn-danger">Disable</a>
                            <?php } else { ?>
                                <a href="/apps/admin/?view=server&task=<?php echo $task ?>&action=task_enable" class="btn btn-sm btn-success">Enable</a>
                            <?php } ?>
                        <?php } ?>
                    </div>
                    <div>
                        <?php if ($taskStatus['running']) { ?>
                            <a href="/apps/admin/?view=server&task=<?php echo $task ?>&action=task_stop" class="btn btn-sm btn-warning">Stop</a>
                        <?php } ?>
                    </div>
                </div>
            </div>

        </div>
    <?php } ?>
</div>
