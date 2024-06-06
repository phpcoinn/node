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

require_once __DIR__. '/../common/include/top.php';

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
    if($action == "private-key-login") {
        $nonce = $_POST['nonce'];
        $signature = $_POST['signature'];
        $public_key = $_POST['public_key'];
        $res = ec_verify($nonce, $signature, $public_key);
        if(!$res) {
            $msg=[['type'=>'danger', 'msg'=>'Invalid private key']];
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
    if($action == 'set_hostname') {
        $db->setConfig('hostname', $_POST['hostname']);
	    header("location: ".APP_URL."/?view=config");
	    exit;
    }
}

if(isset($_GET['action'])) {
    $action = $_GET['action'];
    if($action == "logout") {
	    $_SESSION['login']=false;
	    header("location: ".APP_URL);
	    exit;
    }
    if($action=="logging") {
        $value = $_GET['value'];
        $db->setConfig("enable_logging", $value);
	    header("location: ".APP_URL."/?view=config");
	    exit;
    }
    if($action=="offline") {
        $value = $_GET['value'];
        $db->setConfig("offline", $value);
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
	if($action == "repeer") {
		$id = $_GET['id'];
		$peer = $_GET['peer'];
		if(!empty($id)) {
			Peer::delete($id);
			$cmd = "php ".ROOT."/cli/util.php peer " . $peer . " > /dev/null 2>&1 &";
			$res = shell_exec($cmd);
		}
		header("location: ".APP_URL."/?view=peers");
		exit;
	}
    if($action == "update") {
        $res = peer_post(APPS_REPO_SERVER."/peer.php?q=getApps");
        if($res === false) {
            die("Error updating apps");
        } else {
            _log("Updating apps", 3);
            $hash = $res['hash'];
            $signature = $res['signature'];
            $verify = Account::checkSignature($hash, $signature, APPS_REPO_SERVER_PUBLIC_KEY);
            _log("Chacking repo signature hash=$hash signature=$signature verify=$verify",3);
            if(!$verify) {
	            die("Error verifying apps");
            }
            $link = APPS_REPO_SERVER."/apps.php";
            _log("Downloading from link $link",3);
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
	        _log("Calculating new hash calHash=$calHash",3);
	        if($hash != $calHash) {
	            die("Error extracting apps transfered = $hash - calc = $calHash");
            }
	        global $appsHashFile;
	        unlink($appsHashFile);
	        header("location: ".APP_URL."/?view=update");
        }
    }
    if($action == "propagate_update") {
	    _log("build archive",3);
//	    $cmd="find ".ROOT."/apps -type d -exec chmod 755 {} \;";
//	    shell_exec($cmd);
//	    $cmd="find ".ROOT."/apps -type f -exec chmod 644 {} \;";
//	    shell_exec($cmd);
	    $appsHashCalc = calcAppsHash();
	    file_put_contents($appsHashFile, $appsHashCalc);
	    buildAppsArchive();
	    _log("Propagating apps",4);
        Propagate::appsToAll($appsHashCalc);
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
	if($action == "mn_lock_clear") {
		@rmdir(ROOT.'/tmp/mn-lock');
		header("location: ".APP_URL."/?view=server");
		exit;
    }
}

$setAdminPass = !empty($_config['admin_password']);
$login = $_SESSION['login'];

if(isset($_GET['view'])) {
	$view = $_GET['view'];
} else {
	$view = "server";
}
if($view == "db") {
    $dbData = Nodeutil::getDbData();
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
	//TODO: replace @1.0.6.85
    if(method_exists(Nodeutil::class, "getLogData")) {
	    $logData = Nodeutil::getLogData();
    } else {
        $log_file = $_config['log_file'];
        if(substr($log_file, 0, 1)!= "/") {
            $log_file = ROOT . "/" .$log_file;
        }
        $cmd = "tail -n 100 $log_file";
        $logData = shell_exec($cmd);
    }
}
if($view == "peers") {
	$dm = get_data_model(-1, "/apps/admin/?view=peers");
	$sorting = '';
	if(isset($dm['sort'])) {
		$sorting = ' order by '.$dm['sort'];
		if(isset($dm['order'])){
			$sorting.= ' ' . $dm['order'];
		}
	}
    $peers = Peer::getAll($sorting);
}

$minepool_enabled = Minepool::enabled();

$pubKeyLogin = isset($_config['admin_public_key']) && strlen($_config['admin_public_key']) > 0;
$nonce = uniqid();
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

<?php if ($pubKeyLogin) { ?>
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
                                    <input type="hidden" value="" name="public_key">
                                    <input type="hidden" value="<?php echo $nonce ?>" name="nonce">
                                    <input type="hidden" value="" name="signature">
                                    <input type="hidden" value="private-key-login" name="action">
                                    <div class="mb-3">
                                        <label class="form-label" for="password">Private key</label>
                                        <input type="password" class="form-control" id="private_key" name="private_key" value="" required/>
                                    </div>
                                    <div class="row mb-4">
                                        <div class="col">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="rememberPrivateKey">
                                                <label class="form-check-label" for="rememberPrivateKey">
                                                    Remember private key
                                                </label>
                                            </div>
                                            <div class="help-block text-muted text-info">
                                                Private key will be stored only locally in browser
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <button type="button" class="btn btn-primary w-md" onclick="login()">Login</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4"></div>
        </div>

        <script src="/apps/common/js/jquery.min.js"></script>
        <script src="/apps/common/js/phpcoin-crypto.js" type="text/javascript"></script>
        <script type="text/javascript">

            $(function(){
                let privateKey = localStorage.getItem("adminPrivateKey")
                if(privateKey) {
                    $("form [name=private_key]").val(privateKey)
                    $("#rememberPrivateKey").attr("checked", true)
                }
            })

            function login() {
                try {
                    let chainId = "<?php echo CHAIN_ID ?>"
                    let privateKey = $("form [name=private_key]").val().trim()
                    if(!privateKey) {
                        throw new Error("Empty private key");
                    }
                    let nonce = $("form [name=nonce]").val().trim()
                    let sig = sign(chainId+nonce, privateKey)
                    let publicKey = get_public_key(privateKey)
                    $("form [name=signature]").val(sig)
                    $("form [name=public_key]").val(publicKey)
                    $("form [name=private_key]").val("")
                    if($("#rememberPrivateKey").is(":checked")) {
                        localStorage.setItem("adminPrivateKey", privateKey)
                    } else {
                        localStorage.removeItem("adminPrivateKey")
                    }
                    $("form").submit();
                } catch (e) {
                    console.log(e)
                    Swal.fire(
                        {
                            title: 'Login failed',
                            text: e.message,
                            icon: 'error'
                        }
                    )
                    event.preventDefault()
                }
            }
        </script>
    <?php } ?>
<?php } else { ?>

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
                                            <input type="text" class="d-none" id="username" value="<?php echo $_config['hostname'] ?>">
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

<?php }  ?>

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
                <span>Utils</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($view == "peers") { ?>active<?php } ?>" href="<?php echo APP_URL ?>/?view=peers" role="tab" aria-selected="false">
                <span>Peers</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if ($view == "mempool") { ?>active<?php } ?>" href="<?php echo APP_URL ?>/?view=mempool" role="tab" aria-selected="false">
                <span>Mempool</span>
            </a>
        </li>
	    <?php if (Nodeutil::miningEnabled() && $minepool_enabled) { ?>
            <li class="nav-item">
                <a class="nav-link <?php if ($view == "minepool") { ?>active<?php } ?>" href="<?php echo APP_URL ?>/?view=minepool" role="tab" aria-selected="false">
                    <span>Minepool</span>
                </a>
            </li>
        <?php } ?>
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
        <?php if (FEATURE_APPS) { ?>
            <li class="nav-item">
                <a class="nav-link <?php if ($view == "update") { ?>active<?php } ?>" href="<?php echo APP_URL ?>/?view=update" role="tab" aria-selected="false">
                    <span>Update</span>
                </a>
            </li>
        <?php } ?>
    </ul>

    <?php if(!empty($view)) { ?>
	    <?php if($view == "php") {
	        phpinfo();
        } ?>
		<?php if($view == "db") {

			$sql="select count(id) from blocks";
			$count = $db->single($sql);
			$sql="select max(height) from blocks";
			$max = $db->single($sql);

            $blockchain_valid = ($count == $max);
            if(!$blockchain_valid) {
                $sql="select min(height) from (
                select b.height, b.id, lead(b.height) over (),
                       lead(b.height) over () - b.height as diff
                from blocks b
                order by b.height asc) as b
                where diff <> 1";
                $invalid_height = $db->single($sql);
            }

            ?>


            <div class="row">
                <div class="col-lg-4 col-sm-6">
                    <?php if (!$blockchain_valid) { ?>
                        <div class="alert alert-danger">
                            Blockchain is invalid.
                            <br/>
                            Blocks: <?php echo $count ?> - Max height: <?php echo $max ?> - Invalid height: <?php echo $invalid_height ?>
                        </div>
                    <?php } ?>
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
                        <div><?php echo $dbData['dbversion'] ?></div>
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

	    <?php if($view == "peers") {

            $total = count(Peer::findPeers(null, null));
            $blacklisted = count(Peer::findPeers(true, null));
            $live = count(Peer::findPeers(null, true));

            ?>

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

            Total: <?php echo $total ?>

            Blacklisted: <?php echo $blacklisted ?>

            Live: <?php echo $live ?>


            <h4>Peers</h4>
            <div class="table-responsive">
                <table class="table table-sm table-striped dataTable">
                    <thead class="table-light">
                    <tr>
                        <th>Action</th>
                        <?php echo sort_column("/apps/admin/index.php?view=peers", $dm, 'id', 'ID' ,'') ?>
                        <?php echo sort_column("/apps/admin/index.php?view=peers", $dm, 'hostname', 'Hostname' ,'') ?>
	                    <?php echo sort_column("/apps/admin/index.php?view=peers", $dm, 'blacklisted', 'Blacklisted' ,'') ?>
	                    <?php echo sort_column("/apps/admin/index.php?view=peers", $dm, 'ping', 'Ping' ,'') ?>
	                    <?php echo sort_column("/apps/admin/index.php?view=peers", $dm, 'height', 'Height' ,'') ?>
                        <th>Block</th>
                        <th>Ip</th>
	                    <?php echo sort_column("/apps/admin/index.php?view=peers", $dm, 'version', 'Version' ,'') ?>
	                    <?php echo sort_column("/apps/admin/index.php?view=peers", $dm, 'fails', 'Fails' ,'') ?>
                        <th>Stuckfail</th>
                        <th>Reason</th>
                        <th>Miner</th>
                        <th>Generator</th>
                        <th>Masternode</th>
	                    <?php echo sort_column("/apps/admin/index.php?view=peers", $dm, 'response_time', 'Response time' ,'') ?>
	                    <?php echo sort_column("/apps/admin/index.php?view=peers", $dm, 'response_cnt', 'Response count' ,'') ?>
	                    <?php echo sort_column("/apps/admin/index.php?view=peers", $dm, 'response_time/response_cnt', 'Response avg' ,'') ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($peers as $peer) {
                        $live = time() - $peer['ping'] < Peer::PEER_PING_MAX_MINUTES*60;
                        $table_class = $peer['blacklisted'] > time() ? "table-danger" : ($live ? "table-success" : "");
                        ?>
                        <tr  class="<?php echo $table_class ?>">
                            <td class="text-nowrap">
                                <a class="btn btn-danger btn-xs" href="<?php echo APP_URL ?>/?action=delete_peer&id=<?php echo $peer['id']  ?>" onclick="if(!confirm('Delete peer?')) return false;">Delete</a>
                                <a class="btn btn-warning btn-xs" href="<?php echo APP_URL ?>/?action=repeer&id=<?php echo $peer['id']  ?>&peer=<?php echo $peer['hostname'] ?>">Re-peer</a>
                            </td>
                            <td><?php echo $peer['id'] ?></td>
                            <td>
                                <a href="<?php echo $peer['hostname'] ?>" target="_blank">
                                    <?php echo $peer['hostname'] ?>
                                </a>
                            </td>
                            <td nowrap="nowrap">
                                <?php echo display_date($peer['blacklisted']) ?>
                                <?php if($peer['blacklisted'] > time()) {
                                    echo " | " .durationFormat($peer['blacklisted'] - time());

                                }?>
                            </td>
                            <td nowrap="nowrap"><?php echo display_date($peer['ping']) . " | " . durationFormat(time() - $peer['ping']) ?></td>
                            <td><?php echo $peer['height'] ?></td>
                            <td><?php echo $peer['block_id'] ?></td>
                            <td><?php echo $peer['ip'] ?></td>
                            <td><?php echo $peer['version'] ?></td>
                            <td><?php echo $peer['fails'] ?></td>
                            <td><?php echo $peer['stuckfail'] ?></td>
                            <td><?php echo $peer['blacklist_reason'] ?></td>
                            <td><?php echo $peer['miner'] ?></td>
                            <td><?php echo $peer['generator'] ?></td>
                            <td><?php echo $peer['masternode'] ?></td>
                            <td><?php echo $peer['response_time'] ?></td>
                            <td><?php echo $peer['response_cnt'] ?></td>
                            <td>
                                <?php
                                $total = $peer['response_time'];
                                $cnt = $peer['response_cnt'];
                                if($cnt > 0) {
                                    $avg = round($total / $cnt,3);
                                }
                                ?>
                                <span title="total=<?php echo $total ?> cnt=<?php echo $cnt ?>"><?php echo $avg ?></span>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <a class="btn btn-danger" href="<?php echo APP_URL ?>/?action=delete_peers" onclick="if(!confirm('Delete all?')) return false">Delete all</a>


		<?php } ?>

	    <?php if($view == "minepool") {

	        $rows = $db->run("select * from minepool order by height desc");

	        ?>

            <h4>Minepool</h4>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Address</th>
                            <th>Height</th>
                            <th>Miner</th>
                            <th>IP Hash</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) { ?>
                            <tr>
                                <td><?php echo explorer_address_link($row['address']) ?></td>
                                <td>
                                    <a href="/apps/explorer/block.php?height=<?php echo $row['height'] ?>"><?php echo $row['height'] ?></a>
                                </td>
                                <td><?php echo $row['miner'] ?></td>
                                <td><?php echo $row['iphash'] ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

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

                    <div class="form-check form-switch form-switch-lg mb-3" dir="ltr">
                        <input type="checkbox" class="form-check-input" id="customSwitchsizelg" <?php echo $_config['offline'] ? 'checked=""' : '' ?>
                               onchange="document.location.href='<?php echo APP_URL ?>/?action=offline&value=<?php echo $_config['offline'] ? 0 : 1 ?>'">
                        <label class="form-check-label" for="customSwitchsizelg">Offline</label>
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


            <?php
			$gitRev = trim(shell_exec("cd . ".ROOT." && git rev-parse HEAD"));
			$remoteRev = trim(shell_exec("cd . ".ROOT.' && git ls-remote https://github.com/phpcoinn/node | head -1 | sed "s/HEAD//"'));

            ?>
            <table class="table table-sm mt-5">
                <tr>
                    <td><label>Node version</label></td>
                    <td><?php echo $gitRev?></td>
                </tr>
                <tr>
                    <td><label>Latest git version</label></td>
                    <td><?php echo $remoteRev?></td>
                </tr>
            </table>

            <?php if ($gitRev != $remoteRev) {?>
                Update node:
                <pre>
                    git reset --hard HEAD
                    git pull
                    php cli/util.php download-apps
                </pre>
            <?php } ?>


        <?php } ?>
	    <?php if($view == "log") { ?>
            <h3>Log</h3>
            <hr/>
            <pre style="white-space: pre-line"><?php echo $logData ?></pre>
		<?php } ?>

        <?php

		define("ADMIN_TAB", true);
	    if($view == "mempool") {
            require_once __DIR__ ."/tabs/mempool.php";
	    }
		if($view == "server") {
			require_once __DIR__ ."/tabs/server.php";
		}
		if($view == "utils") {
			require_once __DIR__ ."/tabs/utils.php";
		}

        ?>

    <?php } ?>
<?php } ?>

<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>



