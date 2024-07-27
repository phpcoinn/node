<?php
require_once dirname(__DIR__)."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Explorer");
require_once ROOT. '/web/apps/explorer/include/functions.php';

if(!FEATURE_SMART_CONTRACTS) {
    header("location: /apps/explorer");
    exit;
}

if(!isset($_GET['id'])) {
    header("location: /apps/explorer");
    exit;
}

$id = $_GET['id'];
$smartContract = SmartContract::getById($id);

if(!$smartContract) {
	header("location: /apps/explorer");
	exit;
}

$data = base64_decode($smartContract['code']);
$data = json_decode($data, true);
$code=$data['code'];
$code = htmlspecialchars(base64_decode($code), ENT_IGNORE);

$state = SmartContract::getState($id);

global $db;

$sql="select * from mempool t where t.type in (5,6,7)
and (t.src = '$id' or t.dst='$id')
order by t.height desc, t.id desc";
$mempool_txs = $db->run($sql);


$sql="select * from transactions t where
(t.dst='$id' and t.type in (5,6)) or (t.src = '$id' and t.type = 7)                               
order by t.height desc, t.id desc";
$txs = $db->run($sql);

$balance = Account::pendingBalance($smartContract['address']);
$interface = SmartContractEngine::getInterface($id);

$smartContract = SmartContract::getById($id);
$name = $smartContract['name'];
$description = $smartContract['description'];
$metadata = json_decode($smartContract['metadata'],true);

require_once __DIR__. '/../common/include/top.php';

$base_url="/apps/explorer/smart_contract.php?id=$id";
if(isset($_GET['sc_get_property_read'])) {
    $sc_get_property_name = $_GET['sc_get_property_read'];
    $sc_get_property_key = $_GET['sc_property_key'][$sc_get_property_name];
    $sc_get_property_result = SmartContractEngine::get($id, $sc_get_property_name, $sc_get_property_key, $sc_get_property_error);
} else if (isset($_GET['sc_exec'])) {
    $public_key = $_SESSION['account']['public_key'];
    $method = $_GET['sc_exec'];
    $amount = $_GET['sc_exec_amount'][$method] || 0;
    $params=[];
    foreach ($_GET['sc_exec_params'][$method] as $name => $val) {
        $params[]=$val;
    }
    $tx=Transaction::generateSmartContractExecTx($public_key, $id, $method, $amount, $params);
    $tx = base64_encode(json_encode($tx));
    $request_code = uniqid("signtx");
    $redirect = urlencode($base_url);
    $approve_link = "/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/gateway/approve.php?app=".APP_NAME."&request_code=$request_code&tx=$tx&redirect=$redirect";
    header("location: $approve_link");
    exit;
} else if (isset($_GET['sc_send'])) {
    $method = $_GET['sc_send'];
    $public_key = $_SESSION['account']['public_key'];
    $dst_address=$_GET['sc_send_dst'][$method];
    $amount = $_GET['sc_exec_amount'][$method];
    $params=[];
    foreach ($_GET['sc_exec_params'][$method] as $name => $val) {
        $params[]=$val;
    }
    $tx=Transaction::generateSmartContractSendTx($public_key, $dst_address, $method, $amount, $params);
    $tx = base64_encode(json_encode($tx));
    $request_code = uniqid("signtx");
    $redirect = urlencode($base_url);
    $approve_link = "/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/gateway/approve.php?app=".APP_NAME."&request_code=$request_code&tx=$tx&redirect=$redirect";
    header("location: $approve_link");
    exit;
} else if (isset($_GET['sc_view'])) {
    $view = $_GET['sc_view'];
    $params=[];
    foreach ($_GET['sc_view_params'][$view] as $name => $val) {
        $params[]=$val;
    }
    $sv_view_name=$view;
    $sv_view_res = SmartContractEngine::view($id, $view, $params, $sv_view_err);
} else if (isset($_GET['action']) && $_GET['action']=="download_code") {
    $code=$smartContract['code'];
    $decoded=json_decode(base64_decode($code), true);
    $code=base64_decode($decoded['code']);

    $phar_file = ROOT . "/tmp/".$id.".phar";
    file_put_contents($phar_file, $code);

    ob_end_clean();
    header("Content-Description: File Transfer");
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"". basename($phar_file) ."\"");

    readfile ($phar_file);
    exit;
}

if(isset($_GET['action'])) {
    $action = $_GET['action'];
    if($action == "logout") {
        session_destroy();
        header("location: ".$base_url);
        exit;
    }
}



$loggedIn = false;
if(isset($_SESSION['account'])) {
    $balance = Account::getBalance($_SESSION['account']['address']);
    $loggedIn = true;
}

$scBalance = Account::getBalance($smartContract['address']);

global $loggedIn;
?>

<ol class="breadcrumb m-0 ps-0 h4">
	<li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
    <li class="breadcrumb-item"><a href="/apps/explorer/smart_contracts.php">Smart contracts</a></li>
	<li class="breadcrumb-item"><?php echo $id ?></li>
</ol>

<div class="table-responsive">
    <table class="table table-sm table-striped">
        <tr>
            <td>Address</td>
            <td><?php echo explorer_address_link($smartContract['address']) ?></td>
        </tr>
        <tr>
            <td>Height</td>
            <td><?php echo explorer_height_link($smartContract['height']) ?></td>
        </tr>
        <tr>
            <td>Code</td>
            <td>
                <pre style="max-height: 100px; overflow: auto; background-color: #fff">
                    <?php
                    $p1=strpos($code, "__HALT_COMPILER");
                    $p2=strpos($code, "lt;?php", $p1);
                    $p3=strrpos($code, "}");
                    $c=substr($code, $p2+7, $p3-$p2-7+1);
                    echo $c . PHP_EOL ?>
                </pre>
                <a href="<?php echo $base_url ?>&action=download_code" class="btn btn-soft-primary btn-sm">Download code</a>
            </td>
        </tr>
        <tr>
            <td>Signature</td>
            <td><?php echo $smartContract['signature'] ?></td>
        </tr>
        <tr>
            <td>Balance</td>
            <td><?php echo $scBalance ?></td>
        </tr>
        <tr>
            <td>Name</td>
            <td><?php echo $name ?></td>
        </tr>
        <tr>
            <td>Description</td>
            <td><?php echo $description ?></td>
        </tr>
        <tr>
            <td>Metadata</td>
            <td>
                <table class="table table-sm table-striped">
                    <?php foreach($metadata as $key=>$value) { ?>
                        <tr>
                            <td><?php echo $key ?></td>
                            <td>
                                <?php if ($key == "image") { ?>
                                    <img src="<?php echo $value ?>"/>
                                <?php } else if ($key == "name" && $metadata['class'] == "ERC-20") { ?>
                                    <a href="/apps/explorer/tokens/token.php?id=<?php echo $smartContract['address'] ?>"><?php echo $metadata['name'] ?></a>
                                <?php } else { ?>
                                    <?php echo $value ?>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            </td>
        </tr>
    </table>
</div>

<h3>Smart Contract State</h3>
<div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead>
            <tr>
                <th>Variable</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($state as $name => $val) { ?>
                <tr>
                    <td><?php echo $name ?></td>
                    <td>
                        <?php if(is_array($val)) { ?>
                            <table class="table table-sm table-striped mb-0">
                                <?php foreach($val as $key=>$value) { ?>
                                    <tr>
                                        <td><?php echo $key ?></td>
                                        <td><?php echo $value ?></td>
                                    </tr>
                                <?php } ?>
                            </table>
                        <?php } else { ?>
                            <?php echo $val ?>
                        <?php } ?>

                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>


<div class="d-flex align-items-center">
    <h3>Interface</h3>
    <?php if (!$loggedIn) { ?>
        <div class="ms-auto">
            Login to interact with this contract
        </div>
    <?php } else { ?>
        <div class="ms-auto d-flex align-items-center gap-2">
            <?php echo explorer_address_link($_SESSION['account']['address']) ?>
            <div><?php echo $balance ?></div>
        </div>
    <?php } ?>
</div>



<form method="get" action="">
    <div class="table-responsive">
        <table class="table table-sm table-striped">
          <tr>
            <td class="fw-bold">Version</td>
            <td><?php echo $interface['version'] ?></td>
          </tr>
          <tr>
            <td class="fw-bold">Properties</td>
            <td>
                <input type="hidden" name="id" value="<?php echo $id ?>">
                <table class="table table-sm table-striped">
                    <?php foreach ($interface['properties'] as $property) { ?>
                        <tr>
                            <td>
                                <?php
                                    echo '$'.$property['name'];
                                    if($property['type']=="map") {
                                        echo "[]";
                                    }
                                ?>
                            </td>
                            <td class="text-end nowrap">
                                <?php if($property['type']=="map") { ?>
                                    <input class="form-control w-auto d-inline form-control-sm" type="text"
                                           name="sc_property_key[<?php echo $property['name'] ?>]" value="<?php echo $_GET['sc_property_key'][$property['name']] ?>" placeholder="Key">
                                <?php } ?>
                                <button type="submit" name="sc_get_property_read" value="<?php echo $property['name'] ?>" class="btn btn-sm btn-soft-primary">Read</button>
                            </td>
                            <td>
                                <?php if (isset($sc_get_property_result) && $sc_get_property_name == $property['name']) { ?>
                                    <?php if(!empty($sc_get_property_error)) { ?>
                                            <span class="fa fa-exclamation-triangle text-danger"></span>
                                            <?php echo $sc_get_property_error ?>
                                        <?php } else { ?>
                                            <span><?php echo $sc_get_property_result ?></span>
                                    <?php } ?>

                                <?php } ?>
                            </td>
                            <td class="text-end">
                                <?php if (isset($sc_get_property_result) && $sc_get_property_name == $property['name']) { ?>
                                    <a class="btn btn-sm btn-soft-primary" href="<?php echo $base_url ?>">Clear</a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            </td>
          </tr>
          <tr>
            <td class="fw-bold">Methods</td>
            <td>
                <table class="table table-sm table-striped">
                    <?php foreach ($interface['methods'] as $method) { ?>
                        <tr>
                            <td>
                                <?php
                                echo $method['name'];
                                echo "(";
                                if(!empty($method['params'])) {
                                    foreach ($method['params'] as $ix => $param) {
                                        if(version_compare($interface['version'], "2.0.0.")) {
                                            echo '$'.$param['name'];
                                            if(!empty($param['value'])) {
                                                echo "=".$param['value'];
                                            }
                                        } else {
                                        echo '$'.$param;
                                        }
                                        if($ix < count($method['params'])-1) echo ", ";
                                    }
                                }
                                echo ")";
                                ?>
                            </td>
                            <td>
                                <?php if($loggedIn) { ?>
                                    <input type="text" class="form-control form-control-sm w-auto" name="sc_exec_amount[<?php echo $method['name'] ?>]" value="" placeholder="PHPCoin amount">
                                <?php } ?>
                            </td>

                            <td class="text-end">
                                <?php if($loggedIn) { ?>
                                    <?php foreach ($method['params'] as $ix => $param) {
                                        if(is_array($param)) {
                                            $name = $param['name'];
                                        } else {
                                            $name = $param;
                                        }
                                        ?>
                                        <input type="text" class="form-control form-control-sm d-inline w-auto"
                                               name="sc_exec_params[<?php echo $method['name'] ?>][<?php echo $name ?>]" value="" placeholder="<?php echo $name ?>">
                                    <?php } ?>
                                    <button type="submit" class="btn btn-sm btn-soft-primary" name="sc_exec" value="<?php echo $method['name'] ?>">Execute</button>
                                <?php } ?>
                            </td>
                            <td class="text-end">
                                <?php if($loggedIn && $id == $_SESSION['account']['address']) { ?>
                                    <input type="text" class="form-control form-control-sm w-auto d-inline" name="sc_send_dst[<?php echo $method['name'] ?>]" placeholder="Dst address">
                                    <button type="submit" class="btn btn-sm btn-soft-primary" name="sc_send" value="<?php echo $method['name'] ?>">Send</button>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            </td>
          </tr>
          <tr>
            <td class="fw-bold">Views</td>
            <td>
                <table class="table table-sm table-striped">
                    <?php foreach ($interface['views'] as $view) { ?>
                        <tr>
                            <td>
                                <?php
                                echo $view['name'];
                                echo "(";
                                if(!empty($view['params'])) {
                                    foreach ($view['params'] as $ix => $param) {
                                        if(version_compare($interface['version'], "2.0.0.")) {
                                            echo '$'.$param['name'];
                                            if(!empty($param['value'])) {
                                                echo "=".$param['value'];
                                            }
                                        } else {
                                            echo '$'.$param;
                                        }
                                        if($ix < count($view['params'])-1) echo ", ";
                                    }
                                }
                                echo ")";
                                ?>
                            </td>
                            <td class="text-end">
                                <?php foreach ($view['params'] as $ix => $param) {
                                    if(is_array($param)) {
                                        $name=$param['name'];
                                    } else {
                                        $name = $param;
                                    }
                                    ?>
                                    <input type="text" class="form-control form-control-sm d-inline w-auto"
                                           name="sc_view_params[<?php echo $view['name'] ?>][<?php echo $name ?>]" value="" placeholder="<?php echo $name ?>">
                                <?php } ?>
                                <button type="submit" class="btn btn-sm btn-soft-primary" name="sc_view" value="<?php echo $view['name'] ?>">Call</button>
                            </td>
                            <td>
                                <?php if (isset($sv_view_res) && $sv_view_name == $view['name']) { ?>
                                    <?php if(!empty($sv_view_err)) { ?>
                                        <span class="fa fa-exclamation-triangle text-danger"></span>
                                        <?php echo $sv_view_err ?>
                                    <?php } else { ?>
                                        <span><?php echo $sv_view_res ?></span>
                                    <?php } ?>

                                <?php } ?>
                            </td>
                            <td class="text-end">
                                <?php if (isset($sv_view_res) && $sv_view_name == $view['name']) { ?>
                                    <a class="btn btn-sm btn-soft-primary" href="<?php echo $base_url ?>">Clear</a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            </td>
          </tr>
        </table>
    </div>
</form>
<!--<pre>-->
<!--    --><?php //print_r($interface) ?>
<!--</pre>-->

<?php if(!empty($mempool_txs)) { ?>
    <h3>Mempool transactions</h3>
    <div class="table-responsive">
        <table class="table table-sm table-striped">
            <thead>
            <tr>
                <th>Height</th>
                <th>ID</th>
                <th>From/To</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Params</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($mempool_txs as $tx) {
                if($tx['type']==TX_TYPE_SC_CREATE) {
                    $data = base64_decode($tx['data']);
                    $data = json_decode($data, true);
                    $method="deploy";
                    $params=$data["params"];
                } else {
                    $data = base64_decode($tx['message']);
                    $data = json_decode($data, true);
                    $method=$data['method'];
                    $params=$data["params"];
                }
                ?>
                <tr>
                    <td><?php echo explorer_height_link($tx['height']) ?></td>
                    <td><?php echo explorer_tx_link($tx['id']) ?></td>
                    <td><?php echo explorer_address_link($tx['src']==$id ? $tx['dst'] : $tx['src']) ?></td>
                    <td><?php echo Transaction::typeLabel($tx['type']) ?></td>
                    <td><?php echo $tx['val'] ?></td>
                    <td><?php echo $method ?></td>
                    <td><?php echo implode(", ", $params) ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<h3>Transactions</h3>
<div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead>
            <tr>
                <th>Height</th>
                <th>ID</th>
                <th>From/To</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Params</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($txs as $tx) {
                if($tx['type']==TX_TYPE_SC_CREATE) {
                    $data = base64_decode($tx['data']);
                    $data = json_decode($data, true);
                    $method="deploy";
                    $params=$data["params"];
                } else {
                    $data = base64_decode($tx['message']);
                    $data = json_decode($data, true);
                    $method=$data['method'];
                    $params=$data["params"];
                }
                ?>
            <tr>
                <td><?php echo explorer_height_link($tx['height']) ?></td>
                <td><?php echo explorer_tx_link($tx['id']) ?></td>
                <td><?php echo explorer_address_link($tx['src']==$id ? $tx['dst'] : $tx['src']) ?></td>
                <td><?php echo Transaction::typeLabel($tx['type']) ?></td>
                <td><?php echo $tx['val'] ?></td>
                <td><?php echo $method ?></td>
                <td><?php echo implode(", ", $params) ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
