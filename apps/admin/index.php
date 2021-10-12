<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once ROOT. '/apps/explorer/include/functions.php';

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
            $link = APPS_REPO_SERVER."/tmp/apps.tar.gz";
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
}

?>

<?php
require_once __DIR__. '/../common/include/top.php';
?>
<h3>Node Admin</h3>

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
        <h3>Please generate and save admin password</h3>
        <form method="post" action="">
                <label>Enter password:</label>
                <input type="password" id="password" name="password" value="" required/>
                <label>Repeat password:</label>
                <input type="password" id="password2" name="password2" value="" required/>
            <input type="hidden" name="action" value="generate">
            <button type="submit">Generate</button>
        </form>
    <?php } ?>
<?php } else { ?>
        <?php if (!$login) { ?>
        <h3>Login</h3>
        <form method="post" action="">
            Node Password:
            <input type="password" id="password" name="password" value="" required/>
            <input type="hidden" name="action" value="login">
            <button type="submit">Login</button>
        </form>
            <?php } ?>
<?php } ?>

<?php if ($login) { ?>
    <div>
        <a href="<?php echo APP_URL ?>/?action=logout">Logout</a>
        <br/>
        <a href="<?php echo APP_URL ?>/?view=server">Server info</a>
        |
        <a href="<?php echo APP_URL ?>/?view=php">PHP info</a>
        |
        <a href="<?php echo APP_URL ?>/?view=db">DB info</a>
        |
        <a href="<?php echo APP_URL ?>/?view=utils">Utils</a>
        |
        <a href="<?php echo APP_URL ?>/?view=peers">Peers</a>
        |
        <a href="<?php echo APP_URL ?>/?view=config">Config</a>
        |
        <a href="<?php echo APP_URL ?>/?view=log">Log</a>
        |
        <a href="<?php echo APP_URL ?>/?view=update">Update<a/>
    </div>
    <hr/>
    <?php if(!empty($view)) { ?>
        <?php if($view == "server") { ?>
            Hostname: <?php echo $serverData['hostname'] ?><br/>
            <dl class="row">
                <dt class="col-sm-3">CPU Usage:</dt>
                <dd class="col-sm-3"><?php echo $serverData['stat']['cpuload'] ?> %</dd>
                <dt class="col-sm-3">RAM Usage:</dt>
                <dd class="col-sm-3"><?php echo $serverData['stat']['memusage'] ?> %</dd>
                <dt class="col-sm-3">Hard Disk Usage:</dt>
                <dd class="col-sm-3"><?php echo $serverData['stat']['diskusage'] ?> %</dd>
                <dt class="col-sm-3">PHP Load:</dt>
                <dd class="col-sm-3"><?php echo $serverData['stat']['phpload'] ?> %</dd>
                <dt class="col-sm-3">Established Connections:</dt>
                <dd class="col-sm-3"><?php echo $serverData['stat']['connections'] ?></dd>
                <dt class="col-sm-3">Total Connections:</dt>
                <dd class="col-sm-3"><?php echo $serverData['stat']['totalconnections'] ?></dd>
                <dt class="col-sm-3">RAM Total:</dt>
                <dd class="col-sm-3"><?php echo $serverData['stat']['memtotal'] ?> GB</dd>
                <dt class="col-sm-3">RAM Used:</dt>
                <dd class="col-sm-3"><?php echo $serverData['stat']['memused'] ?> GB</dd>
                <dt class="col-sm-3">RAM Available:</dt>
                <dd class="col-sm-3"><?php echo $serverData['stat']['memavailable'] ?> GB</dd>
                <dt class="col-sm-3">Hard Disk Free:</dt>
                <dd class="col-sm-3"><?php echo $serverData['stat']['diskfree'] ?> GB</dd>
                <dt class="col-sm-3">Hard Disk Used:</dt>
                <dd class="col-sm-3"><?php echo $serverData['stat']['diskused'] ?> GB</dd>
                <dt class="col-sm-3">Hard Disk Total:</dt>
                <dd class="col-sm-3"><?php echo $serverData['stat']['disktotal'] ?> GB</dd>

            </dl>
            <br/>
            Miner Enabled: <span class="badge bg-<?php echo $_config['miner'] ? 'success' : 'danger' ?>">
                <?php echo $_config['miner'] ? 'On' : 'Off' ?>
            </span>
            <br/>
            Miner running: <span class="badge bg-<?php echo $miner_running ? 'success' : 'danger' ?>">
                <?php echo $miner_running ? 'Yes' : 'No' ?></span>
            <br/>
            Lock: <?php echo $miner_lock ? 'Yes' : 'No' ?>
            <br/>
            <a href="<?php echo APP_URL ?>/?action=miner_enable" onclick="if(!confirm('Enable miner?')) return false">Enable</a>
            |
            <a href="<?php echo APP_URL ?>/?action=miner_disable" onclick="if(!confirm('Disable miner?')) return false">Disable</a>
            |
            <a href="<?php echo APP_URL ?>/?action=miner_restart" onclick="if(!confirm('Restart miner?')) return false">Restart</a>
            <br/>
            Miner address: <?php echo explorer_address_link(Account::getAddress($_config['miner_public_key'])) ?>
            <br/>
            <?php if ($minerStat) { ?>
            Miner stat:<br/>
                Started: <?php echo display_date($minerStat['started']) ?><br/>
                <?php echo $minerStat['hashes'] ?> hashes<br/>
                <?php echo $minerStat['submits'] ?> submits<br/>
                <?php echo $minerStat['accepted'] ?> accepted<br/>
                <?php echo $minerStat['rejected'] ?> rejected<br/>
            <?php } ?>
            <br/>
            Sync: <span class="badge bg-<?php echo $sync_running ? 'success' : 'danger' ?>">
                <?php echo $sync_running ? 'Yes' : 'No' ?></span>
            <br/>
            Lock: <?php echo $sync_lock ? 'Yes' : 'No' ?>
        <?php } ?>
	    <?php if($view == "php") {
	        phpinfo();
        } ?>
		<?php if($view == "db") { ?>
            Connection: <?php echo $dbData['connection'] ?><br/>
            Server: <?php echo $dbData['server'] ?><br/>
            DB Name: <?php echo $dbData['db_name'] ?><br/>
            DB version: <?php echo $_config['dbversion'] ?><br/>
            <?php foreach ($dbData['tables'] as $table => $rows) { ?>
                <?php echo $table ?>: <?php echo $rows ?><br/>
            <?php } ?>
		<?php } ?>
	    <?php if($view == "utils") { ?>
            <a class="btn btn-info" href="<?php echo APP_URL ?>/?view=utils&action=clean" onclick="if(!confirm('Clean?')) return false">Clean</a>
            <a class="btn btn-info" href="<?php echo APP_URL ?>/?view=utils&action=sync" onclick="if(!confirm('Run sync?')) return false">Run sync</a>
          <hr/>
            <h2>Check blocks</h2>
            <form method="post" action="">
                Peer:
                <input type="text" value="" name="peer" required="required">
                <input type="hidden" name="action" value="check_blocks"/>
                <button type="submit">Check</button>
                <?php if($checkBlocksResponse) { ?>
                    <?php if(empty($invalid_block)) { ?>
                        <div class="alert alert-success">
                            No invalid block
                        </div>
                    <?php } else { ?>
                        <div class="alert alert-danger">
                            Invalid block detected at height <?php echo $invalid_block ?>
                        </div>
                    <?php } ?>
                <?php } ?>
            </form>
            <hr/>
            <h2>Clear blocks</h2>
            <form method="post" action="">
                From height:
                <input type="number" required name="height">
                <input type="hidden" name="action" value="clear_blocks"/>
                <button type="submit">Clear</button>
            </form>
            <hr/>
            <h2>Accounts hash</h2>
            <a href="<?php echo APP_URL ?>/?view=utils&action=accounts-hash" class="btn btn-info">Calculate</a>
            <?php if($accountsHash) { ?>
                <div class="alert alert-success">
	                height: <?php echo $accountsHash['height'] ?><br/>
	                hash: <?php echo $accountsHash['hash'] ?>
                </div>
            <?php } ?>
            <h2>Blocks hash</h2>
            <form action="" method="post">
                <input type="number" name="height" value=""/>
                <input type="hidden" name="action" value="blocks-hash"/>
                <button type="submit">Calculate</button>
            </form>
            <?php if($blocksHash) { ?>
                <div class="alert alert-success">
                    height: <?php echo $blocksHash['height'] ?><br/>
                    hash: <?php echo $blocksHash['hash'] ?>
                </div>
            <?php } ?>
        <?php } ?>

	    <?php if($view == "peers") { ?>

            <form method="post" action="">
                <input type="text" name="peer" required>
                <input type="hidden" name="action" value="add_peer">
                <button class="btn btn-success" type="submit">Add Peer</button>
            </form>
            <h4>Peers</h4>
            <table class="table table-sm table-striped">
                <thead>
                <tr>
                    <th>id</th>
                    <th>hostname</th>
                    <th>blacklisted</th>
                    <th>ping</th>
                    <th>reserve</th>
                    <th>ip</th>
                    <th>fails</th>
                    <th>stuckfail</th>
                    <th>reason</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
				<?php foreach ($peers as $peer) { ?>
                    <tr>
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


	    <?php if($view == "config") { ?>

            <h3>Config</h3>
            <table class="table table-sm">
                <?php foreach($_config as $key=>$val) { ?>
                <tr>
                    <td><?php echo $key ?></td>
                    <td><?php echo $val ?></td>
                </tr>
                <?php } ?>
            </table>
            <br/>
            Logging
                <a href="<?php echo APP_URL ?>/?action=logging&value=<?php echo $_config['enable_logging'] ? 0 : 1 ?>">
                    <span class="badge bg-<?php echo $_config['enable_logging'] ? 'success' : 'danger' ?>">
                        <?php echo $_config['enable_logging'] ? 'On' : 'Off' ?>
                    </span>
                </a>
            <br/>
            <h3>Hostname</h3>
            <form method="post" action="">
                <input type="text" value="<?php echo $_config['hostname'] ?>" name="hostname" required/>
                <input type="hidden" name="action" value="set_hostname"/>
                <button type="submit">Set</button>
            </form>
		<?php } ?>
	    <?php if($view == "update") { ?>
                Apps version: <?php echo APPS_VERSION ?><br/>
            Apps hash (calculated): <?php echo $updateData['appsHash']['calculated'] ?><br/>
            Apps hash (stored): <?php echo $updateData['appsHash']['stored'] ?><br/>
            Apps hash (valid): <?php echo $updateData['appsHash']['valid'] ?>
            <?php if ($updateData['appsHash']['calculated'] != $updateData['appsHash']['valid']) { ?>
                <a href="<?php echo APP_URL ?>/?action=update" class="btn btn-success">Update apps</a>
            <?php } ?>
            <?php if ($repoServer) {?>
                <a href="<?php echo APP_URL ?>/?action=propagate_update" class="btn btn-success">Propagate</a>
            <?php } ?>
        <?php } ?>
	    <?php if($view == "log") { ?>
            <pre><?php echo $logData ?></pre>
		<?php } ?>
    <?php } ?>
<?php } ?>

<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>



