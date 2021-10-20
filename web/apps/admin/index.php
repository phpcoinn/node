<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once ROOT. '/web/apps/explorer/include/functions.php';

define("PAGE", true);
define("APP_NAME", "Admin");
global $db, $_config;
if(!$_config['admin']) {
    die("Admin mode not enabled!");
}

const APP_URL = "/apps/admin";
session_start();

if(isset($_POST['action'])) {
    $action = $_POST['action'];
    if($action == "generate") {
        $password = $_POST['password'];
        $password2 = $_POST['password2'];

        if($password != $password2) {
	        $msg=[['type'=>'danger', 'msg'=>'Password do not match']];
        } else {
	        $uppercase = preg_match('@[A-Z]@', $password);
	        $lowercase = preg_match('@[a-z]@', $password);
	        $number    = preg_match('@[0-9]@', $password);
	        $specialChars = preg_match('@[^\w]@', $password);

	        if(!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
		        $m = 'Password should be at least 8 characters in length and should include at least one upper case letter, one number, and one special character.';
		        $msg=[['type'=>'danger', 'msg'=>$m]];
	        } else {
		        $passwordHash = password_hash($password, HASHING_ALGO, HASHING_OPTIONS);
                $m = 'Your password has is generated. Write this hash to config file: '.$passwordHash;
		        $msg=[['type'=>'success', 'msg'=>$m]];
            }
        }
    }
    if($action == "login") {
	    $password = $_POST['password'];
	    $passwordHash = $_config['admin_password'];
	    $verify = password_verify($password, $passwordHash);
	    if(!$verify) {
		    $msg=[['type'=>'danger', 'msg'=>'Invalid password']];
        } else {
	        $_SESSION['login']=true;
	        header("location: ".APP_URL);
	        exit;
        }
    }
    if($action == "add_peer") {
        $peer = $_POST['peer'];
	    $cmd = "php ".ROOT."/cli/util.php peer " . $peer . " > /dev/null 2>&1 &";
        $res = shell_exec($cmd);
	    header("location: ".APP_URL."/?view=peers");
	    exit;
    }
    if($action == "check_blocks") {
	    $peer = $_POST['peer'];
	    $invalid_block = Nodeutil::checkBlocksWithPeer($peer);
	    $checkBlocksResponse = true;
    }
    if($action == 'clear_blocks') {
	    $height = $_POST['height'];
        Nodeutil::deleteFromHeight($height);
    }
    if($action == 'set_hostname') {
        $db->setConfig('hostname', $_POST['hostname']);
	    header("location: ".APP_URL."/?view=config");
	    exit;
    }
	if($action == "blocks-hash") {
		$height = $_POST['height'];
		$blocksHash = Nodeutil::calculateBlocksHash($height);
	}
}

if(isset($_GET['action'])) {
    $action = $_GET['action'];
    if($action == "logout") {
	    $_SESSION['login']=false;
	    header("location: ".APP_URL);
	    exit;
    }
    if($action == "clean") {
        Nodeutil::clean();
	    header("location: ".APP_URL."/?view=utils");
	    exit;
    }
    if($action=="sync") {
	    $dir = ROOT."/cli";
	    system("php $dir/sync.php force  > /dev/null 2>&1  &");
	    header("location: ".APP_URL."/?view=utils");
	    exit;
    }
    if($action=="logging") {
        $value = $_GET['value'];
        $db->setConfig("enable_logging", $value);
	    header("location: ".APP_URL."/?view=config");
	    exit;
    }
    if($action == "delete_peer") {
        $id = $_GET['id'];
        if(!empty($id)) {
            Peer::delete($id);
        }
	    header("location: ".APP_URL."/?view=peers");
	    exit;
    }
    if($action == "update") {
        $res = peer_post(APPS_REPO_SERVER."/peer.php?q=getApps");
        if($res === false) {
            die("Error updating apps");
        } else {
            _log("Updating apps");
            $hash = $res['hash'];
            $signature = $res['signature'];
            $verify = Account::checkSignature($hash, $signature, APPS_REPO_SERVER_PUBLIC_KEY);
            _log("Chacking repo signature hash=$hash signature=$signature verify=$verify");
            if(!$verify) {
	            die("Error verifying apps");
            }
            $link = APPS_REPO_SERVER."/apps.php";
            _log("Downloading from link $link");
	        $arrContextOptions=array(
		        "ssl"=>array(
			        "verify_peer"=>true,
			        "verify_peer_name"=>true,
		        ),
	        );
            $res = file_put_contents(ROOT . "/tmp/apps.tar.gz", fopen($link, "r", false, stream_context_create($arrContextOptions)));
            if($res === false) {
                die("Error downloading apps");
            }

	        if(file_exists(Nodeutil::getAppsLockFile())) {
		        _log("Apps lock file exists - can not update");
		        return;
	        }

            Nodeutil::extractAppsArchive();
	        $calHash = calcAppsHash();
	        _log("Calculating new hash calHash=$calHash");
	        if($hash != $calHash) {
	            die("Error extracting apps transfered = $hash - calc = $calHash");
            }
	        global $appsHashFile;
	        unlink($appsHashFile);
	        header("location: ".APP_URL."/?view=update");
        }
    }
    if($action == "propagate_update") {
	    _log("build archive");
//	    $cmd="find ".ROOT."/apps -type d -exec chmod 755 {} \;";
//	    shell_exec($cmd);
//	    $cmd="find ".ROOT."/apps -type f -exec chmod 644 {} \;";
//	    shell_exec($cmd);
	    $appsHashCalc = calcAppsHash();
	    file_put_contents($appsHashFile, $appsHashCalc);
	    buildAppsArchive();
	    $dir = ROOT . "/cli";
	    _log("Propagating apps");
	    system("php $dir/propagate.php apps $appsHashCalc > /dev/null 2>&1  &");
	    header("location: ".APP_URL."/?view=update");
    }
    if($action == "miner_enable") {
	    $db->setConfig("miner", true);
	    @unlink(ROOT. "/tmp/miner-lock");
	    header("location: ".APP_URL."/?view=server");
    }
	if($action == "miner_disable") {
		$db->setConfig("miner", false);
		@unlink(ROOT. "/tmp/miner-lock");
		header("location: ".APP_URL."/?view=server");
	}
	if($action == "miner_restart") {
		@unlink(ROOT. "/tmp/miner-lock");
		header("location: ".APP_URL."/?view=server");
	}
	if($action == "delete_peers") {
	    Peer::deleteAll();
		header("location: ".APP_URL."/?view=peers");
		exit;
    }
	if($action == "accounts-hash") {
        $accountsHash = Nodeutil::calculateAccountsHash();
    }
}

$setAdminPass = !empty($_config['admin_password']);
$login = $_SESSION['login'];

if(isset($_GET['view'])) {
    $view = $_GET['view'];
    if($view == "server") {
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

	    $res = shell_exec("ps aux | grep sync.php | grep -v grep");
	    $sync_running = !empty(trim($res));
	    $sync_lock = file_exists(Nodeutil::getSyncFile());

	    $res = shell_exec("ps aux | grep miner.php | grep -v grep");
	    $miner_running = !empty(trim($res));
	    $miner_lock = file_exists( ROOT.'/tmp/miner-lock');

    }
    if($view == "db") {
        $dbData['connection']=$_config['db_connect'];
	    $dbData['driver'] = substr($_config['db_connect'], 0, strpos($_config['db_connect'], ":"));
	    $db_name=substr($_config['db_connect'], strrpos($_config['db_connect'], "dbname=")+7);
	    $dbData['db_name']=$db_name;
	    if($dbData['driver'] === "mysql") {
            $dbData['server'] = shell_exec("mysql --version");
        } else if ($dbData['driver'] === "sqlite") {
            $version = $db->single("select sqlite_version();");
		    $dbData['server'] = $version;
        }
	    foreach (['blocks','transactions', 'peers', 'mempool', 'accounts'] as $table) {
		    $cnt = $db->single("select count(*) from $table");
		    $dbData['tables'][$table]=$cnt;
	    }
    }
    if($view == "utils") {

    }
    if($view == "update") {
        global $appsHashFile;
	    $appsHash = file_get_contents($appsHashFile);
        $updateData['appsHash']['calculated']=calcAppsHash();
        $updateData['appsHash']['stored']=$appsHash;

	    $repoServer = isRepoServer();
        if($repoServer) {
	        $validHash = $appsHash;
        } else {
	        $res = peer_post(APPS_REPO_SERVER . "/peer.php?q=getApps");
	        if ($res === false) {
		        $validHash = "No data from repository";
	        } else {
		        $hash = $res['hash'];
		        $signature = $res['signature'];
		        $verify = Account::checkSignature($hash, $signature, APPS_REPO_SERVER_PUBLIC_KEY);
		        if (!$verify) {
			        $validHash = "Invalid repository signature";
		        } else {
			        $validHash = $hash;
		        }
	        }
        }

        $updateData['appsHash']['valid']=$validHash;
    }
    if($view == "log") {
	    $log_file = $_config['log_file'];
	    if(substr($log_file, 0, 1)!= "/") {
	        $log_file = ROOT . "/" .$log_file;
        }
	    $cmd = "tail -n 100 $log_file";
	    $logData = shell_exec($cmd);
    }
    if($view == "peers") {
	    $peers = Peer::getAll();
    }
} else {
    $view = "server";
}

?>

<?php
require_once __DIR__. '/../common/include/top.php';
?>

<?php if ($login) { ?>
    <div class="row">
        <div class="col-6 h3">Node Admin</div>
        <div class="col-6 text-end">
            <a href="<?php echo APP_URL ?>/?action=logout" class="btn btn-outline-primary">Logout</a>
        </div>
    </div>
    <hr/>
<?php } ?>



<?php if($msg) { ?>
	<?php foreach ($msg as $m) { ?>
        <div class="alert alert-<?php echo $m['type'] ?>">
			<?php echo $m['msg'] ?>
        </div>
	<?php }
}
?>


<?php if (!$setAdminPass) { ?>
    <?php if(empty($passwordHash)) { ?>

        <div class="row">
            <div class="col-sm-4"></div>
            <div class="col-sm-4">
                <div class="card mt-5">
                    <div class="card-header">
                        <h4 class="card-title">Login</h4>
                        <p class="card-title-desc">Please generate and save admin password</p>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <form method="post" action="">
                                    <div class="mb-3">
                                        <label class="form-label" for="password">Enter password:</label>
                                        <input type="password" class="form-control" id="password" name="password" value="" required/>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="password2">Repeat password:</label>
                                        <input type="password" class="form-control" id="password2" name="password2" value="" required/>
                                    </div>
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary w-md">Generate</button>
                                    </div>
                                    <input type="hidden" name="action" value="generate">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4"></div>
        </div>

    <?php } ?>
<?php } else { ?>
    <?php if (!$login) { ?>

        <div class="row">
            <div class="col-sm-4"></div>
            <div class="col-sm-4">
                <div class="card mt-5">
                    <div class="card-header">
                        <h4 class="card-title">Login</h4>
                        <p class="card-title-desc">Login and administer your node server</p>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <form method="post" action="">
                                    <div class="mb-3">
                                        <label class="form-label" for="password">Node password</label>
                                        <input type="password" class="form-control" id="password" name="password" value="" required/>
                                    </div>
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary w-md">Login</button>
                                    </div>
                                    <input type="hidden" name="action" value="login">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4"></div>
        </div>

    <?php } ?>
<?php } ?>

<?php if ($login) { ?>


    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?php if ($view == "server") { ?>active<?php } ?>" href="<?php echo APP_URL ?>/?view=server" role="tab" aria-selected="false">
                <span>Server info</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($view == "php") { ?>active<?php } ?>" href="<?php echo APP_URL ?>/?view=php" role="tab" aria-selected="false">
                <span>PHP info</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($view == "db") { ?>active<?php } ?>" href="<?php echo APP_URL ?>/?view=db" role="tab" aria-selected="false">
                <span>DB info</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($view == "utils") { ?>active<?php } ?>" href="<?php echo APP_URL ?>/?view=utils" role="tab" aria-selected="false">
                <span class="d-none d-sm-block">Utils</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($view == "peers") { ?>active<?php } ?>" href="<?php echo APP_URL ?>/?view=peers" role="tab" aria-selected="false">
                <span>Peers</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($view == "config") { ?>active<?php } ?>" href="<?php echo APP_URL ?>/?view=config" role="tab" aria-selected="false">
                <span>Config</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($view == "log") { ?>active<?php } ?>" href="<?php echo APP_URL ?>/?view=log" role="tab" aria-selected="false">
                <span>Log</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($view == "update") { ?>active<?php } ?>" href="<?php echo APP_URL ?>/?view=update" role="tab" aria-selected="false">
                <span>Update</span>
            </a>
        </li>
    </ul>

    <?php if(!empty($view)) { ?>
        <?php if($view == "server") { ?>
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

            <div class="row">
                <div class="col-md-6 col-lg-4">
                    <div class="card">
                        <div class="card-header h3">
                            <h4 class="card-title">Miner</h4>
                        </div>
                        <div class="card-body">
                            <p class="card-text">Address: <?php echo explorer_address_link(Account::getAddress($_config['miner_public_key'])) ?></p>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="row">
                                        <div class="col-6">
                                            Enabled
                                        </div>
                                        <div class="col-6 text-end">
                                            <span class="badge bg-<?php echo $_config['miner'] ? 'success' : 'danger' ?>">
                                                <?php echo $_config['miner'] ? 'On' : 'Off' ?>
                                            </span>
                                        </div>
                                        <div class="col-6">
                                            Running
                                        </div>
                                        <div class="col-6 text-end">
                                            <span class="badge bg-<?php echo $miner_running ? 'success' : 'danger' ?>">
                                                <?php echo $miner_running ? 'Yes' : 'No' ?>
                                            </span>
                                        </div>
                                        <div class="col-6">
                                            Lock
                                        </div>
                                        <div class="col-6 text-end">
                                            <span class="badge bg-<?php echo $miner_lock ? 'success' : 'danger' ?>">
                                                <?php echo $miner_lock ? 'Yes' : 'No' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
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
                        <div class="card-footer bg-transparent border-top text-muted">
                            <a class="btn btn-success me-2" href="<?php echo APP_URL ?>/?action=miner_enable" onclick="if(!confirm('Enable miner?')) return false">Enable</a>
                            <a class="btn btn-danger me-2" href="<?php echo APP_URL ?>/?action=miner_disable" onclick="if(!confirm('Disable miner?')) return false">Disable</a>
                            <a class="btn btn-warning" href="<?php echo APP_URL ?>/?action=miner_restart" onclick="if(!confirm('Restart miner?')) return false">Restart</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card">
                        <div class="card-header h3">
                            <h4 class="card-title">Sync</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    Running
                                </div>
                                <div class="col-6 text-end">
                                    <span class="badge bg-<?php echo $sync_running ? 'success' : 'danger' ?>">
                                        <?php echo $sync_running ? 'Yes' : 'No' ?>
                                    </span>
                                </div>
                                <div class="col-6">
                                    Lock
                                </div>
                                <div class="col-6 text-end">
                                    <span class="badge bg-<?php echo $sync_lock ? 'success' : 'danger' ?>">
                                        <?php echo $sync_lock ? 'Yes' : 'No' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php } ?>
	    <?php if($view == "php") {
	        phpinfo();
        } ?>
		<?php if($view == "db") { ?>

            <div class="row">
                <div class="col-lg-4 col-sm-6">
                    <div class="flex-row d-flex justify-content-between flex-wrap">
                        <label>Connection:</label>
                        <div><?php echo $dbData['connection'] ?></div>
                    </div>
                    <div class="flex-row d-flex justify-content-between flex-wrap">
                        <label>Server:</label>
                        <div><?php echo $dbData['server'] ?></div>
                    </div>
                    <div class="flex-row d-flex justify-content-between flex-wrap">
                        <label>DB Name:</label>
                        <div><?php echo $dbData['db_name'] ?></div>
                    </div>
                    <div class="flex-row d-flex justify-content-between flex-wrap">
                        <label>DB version:</label>
                        <div><?php echo $_config['dbversion'] ?></div>
                    </div>
                    <hr/>
                    <?php foreach ($dbData['tables'] as $table => $rows) { ?>
                        <div class="flex-row d-flex justify-content-between flex-wrap">
                            <label><?php echo $table ?>:</label>
                            <div><?php echo $rows ?></div>
                        </div>
                    <?php } ?>
                </div>
            </div>

		<?php } ?>
	    <?php if($view == "utils") { ?>

            <div class="flex-row d-flex flex-wrap align-items-center mb-2">
                <a class="btn btn-danger me-2" href="<?php echo APP_URL ?>/?view=utils&action=clean" onclick="if(!confirm('Clean?')) return false">Clean</a>
                <div class="text-danger">Deletes all block and transactions in database</div>
            </div>
            <div class="flex-row d-flex flex-wrap align-items-center mb-2">
                <a class="btn btn-info me-2" href="<?php echo APP_URL ?>/?view=utils&action=sync" onclick="if(!confirm('Run sync?')) return false">Run sync</a>
                <div>Synchronize blocks from peers</div>
            </div>

            <div class="mt-4">
                <h3 class="font-size-16 mb-2"><i class="mdi mdi-arrow-right text-primary me-1"></i> Check blocks</h3>

                <form class="row gx-3 gy-2 align-items-center" method="post" action="">

                    <input type="hidden" name="action" value="check_blocks"/>
                    <div class="col-sm-2">
                        <input type="text" class="form-control" id="peer" name="peer" placeholder="Peer" required="required">
                    </div>
                    <div class="col-sm-2">
                        <button type="submit" class="btn btn-primary">Check</button>
                    </div>
                    <div class="col-auto">
	                <?php if($checkBlocksResponse) { ?>
		                <?php if(empty($invalid_block)) { ?>
                            <div class="alert alert-success mb-0">
                                No invalid block
                            </div>
		                <?php } else { ?>
                            <div class="alert alert-danger mb-0">
                                Invalid block detected at height <?php echo $invalid_block ?>
                            </div>
		                <?php } ?>
	                <?php } ?>
                    </div>
                </form>
            </div>

            <hr/>

            <div class="mt-4">
                <h3 class="font-size-16 mb-2"><i class="mdi mdi-arrow-right text-primary me-1"></i> Clear blocks</h3>

                <form class="row gx-3 gy-2 align-items-center" method="post" action="">
                    <input type="hidden" name="action" value="clear_blocks"/>
                    <div class="col-sm-2">
                        <input type="text" class="form-control" id="height" name="height" placeholder="From height" required="required">
                    </div>
                    <div class="col-sm-2">
                        <button type="submit" class="btn btn-danger">Clear</button>
                    </div>
                </form>
            </div>

            <hr/>

            <div class="mt-4">
                <h3 class="font-size-16 mb-2"><i class="mdi mdi-arrow-right text-primary me-1"></i>Accounts hash</h3>
                <div class="row">
                    <div class="col-sm-2">
                        <a href="<?php echo APP_URL ?>/?view=utils&action=accounts-hash" class="btn btn-info">Calculate</a>
                    </div>
                    <div class="col-auto">
                        <?php if($accountsHash) { ?>
                            <div class="alert alert-success mb-0">
                                height: <?php echo $accountsHash['height'] ?><br/>
                                hash: <?php echo $accountsHash['hash'] ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <hr/>

            <div class="mt-4">
                <h3 class="font-size-16 mb-2"><i class="mdi mdi-arrow-right text-primary me-1"></i>Blocks hash</h3>
                <form class="row gx-3 gy-2 align-items-center" method="post" action="">
                    <input type="hidden" name="action" value="blocks-hash"/>
                    <div class="col-sm-2">
                        <input type="text" class="form-control" id="height" name="height" placeholder="Height" required="required">
                    </div>
                    <div class="col-sm-2">
                        <button type="submit" class="btn btn-info">Calculate</button>
                    </div>
                    <div class="col-auto">
                        <?php if($blocksHash) { ?>
                            <div class="alert alert-success mb-0">
                                height: <?php echo $blocksHash['height'] ?><br/>
                                hash: <?php echo $blocksHash['hash'] ?>
                            </div>
                        <?php } ?>
                    </div>
                </form>
            </div>

            <div class="mb-5"></div>

        <?php } ?>

	    <?php if($view == "peers") { ?>

            <div class="mt-4">
                <form class="row gx-3 gy-2 align-items-center" method="post" action="">
                    <input type="hidden" name="action" value="add_peer">
                    <div class="col-sm-2">
                        <input type="text" class="form-control" id="peer" name="peer" placeholder="Peer address" required="required">
                    </div>
                    <div class="col-sm-2">
                        <button type="submit" class="btn btn-success">Add Peer</button>
                    </div>
                </form>
            </div>

            <hr/>

            <h4>Peers</h4>
            <table class="table table-sm table-striped">
                <thead>
                <tr>
                    <th>Id</th>
                    <th>Hostname</th>
                    <th>Blacklisted</th>
                    <th>Ping</th>
                    <th>Reserve</th>
                    <th>Ip</th>
                    <th>Fails</th>
                    <th>Stuckfail</th>
                    <th>Reason</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
				<?php foreach ($peers as $peer) { ?>
                    <tr  class="<?php if ($peer['blacklisted'] > time()) { ?>table-danger<?php } ?>">
                        <td><?php echo $peer['id'] ?></td>
                        <td><?php echo $peer['hostname'] ?></td>
                        <td><?php echo display_date($peer['blacklisted']) ?></td>
                        <td><?php echo display_date($peer['ping']) ?></td>
                        <td><?php echo $peer['reserve'] ?></td>
                        <td><?php echo $peer['ip'] ?></td>
                        <td><?php echo $peer['fails'] ?></td>
                        <td><?php echo $peer['stuckfail'] ?></td>
                        <td><?php echo $peer['blacklist_reason'] ?></td>
                        <td>
                            <a class="btn btn-danger btn-xs" href="<?php echo APP_URL ?>/?action=delete_peer&id=<?php echo $peer['id']  ?>" onclick="if(!confirm('Delete peer?')) return false;">Delete</a>
                        </td>
                    </tr>
				<?php } ?>
                </tbody>
            </table>
            <a class="btn btn-danger" href="<?php echo APP_URL ?>/?action=delete_peers" onclick="if(!confirm('Delete all?')) return false">Delete all</a>


		<?php } ?>


	    <?php if($view == "config") {

	        $display_config = $_config;
			$display_config['db_pass']='****';
			$display_config['generator_private_key']='****';
			$display_config['miner_private_key']='****';
			$display_config['repository_private_key']='****';
			$display_config['faucet_private_key']='****';
			$display_config['wallet_private_key']='****';

	        ?>

            <h3>Config</h3>

            <div class="row">
                <div class="col-sm-7">
                    <table class="table table-sm">
		                <?php foreach($display_config as $key=>$val) { ?>
                            <tr>
                                <td><?php echo $key ?></td>
                                <td style="word-break: break-all">
                                    <?php
                                        if(is_array($val)) {
                                            echo implode('<br/>',$val);
                                        } else {
                                            echo $val;
                                        }
                                    ?>
                                </td>
                            </tr>
		                <?php } ?>
                    </table>
                </div>
                <div class="col-sm-5">

                    <div class="form-check form-switch form-switch-lg mb-3" dir="ltr">
                        <input type="checkbox" class="form-check-input" id="customSwitchsizelg" <?php echo $_config['enable_logging'] ? 'checked=""' : '' ?>
                               onchange="document.location.href='<?php echo APP_URL ?>/?action=logging&value=<?php echo $_config['enable_logging'] ? 0 : 1 ?>'">
                        <label class="form-check-label" for="customSwitchsizelg">Logging</label>
                    </div>

                    <div class="mt-4">
                        <h3 class="font-size-16 mb-2"><i class="mdi mdi-arrow-right text-primary me-1"></i>Hostname</h3>
                        <form class="row gx-3 gy-2 align-items-center" method="post" action="">
                            <input type="hidden" name="action" value="set_hostname"/>
                            <div class="col-sm-8">
                                <input type="text" value="<?php echo $_config['hostname'] ?>" class="form-control" id="hostname" name="hostname" placeholder="" required="required">
                            </div>
                            <div class="col-sm-auto">
                                <button type="submit" class="btn btn-info">Set</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>


		<?php } ?>
	    <?php if($view == "update") { ?>

            <table class="table table-sm">
                <tr>
                    <td>
                        <label>Apps version:</label>
                    </td>
                    <td>
                        <?php echo APPS_VERSION ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label>Apps hash (calculated):</label>
                    </td>
                    <td>
                        <?php echo $updateData['appsHash']['calculated'] ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label>Apps hash (stored):</label>
                    </td>
                    <td>
                        <?php echo $updateData['appsHash']['stored'] ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label>Apps hash (valid):</label>
                    </td>
                    <td>
                        <?php echo $updateData['appsHash']['valid'] ?>
                    </td>
                </tr>
            </table>

            <?php if ($updateData['appsHash']['calculated'] != $updateData['appsHash']['valid']) { ?>
                <a href="<?php echo APP_URL ?>/?action=update" class="btn btn-success">Update apps</a>
            <?php } ?>
            <?php if ($repoServer) {?>
                <a href="<?php echo APP_URL ?>/?action=propagate_update" class="btn btn-success">Propagate</a>
            <?php } ?>
        <?php } ?>
	    <?php if($view == "log") { ?>
            <h3>Log</h3>
            <hr/>
            <pre style="white-space: pre-line"><?php echo $logData ?></pre>
		<?php } ?>
    <?php } ?>
<?php } ?>

<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>



