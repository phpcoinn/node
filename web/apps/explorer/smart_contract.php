<?php
require_once dirname(__DIR__)."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Explorer");
require_once ROOT. '/web/apps/explorer/include/functions.php';

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
$sql="select * from transactions t where t.type in (5,6,7)
and (t.src = '$id' or t.dst='$id')
order by t.height desc, t.id desc";
$txs = $db->run($sql);

$balance = Account::pendingBalance($smartContract['address']);
$interface = SmartContractEngine::getInterface($id);

$smartContract = SmartContract::getById($id);
$name = $smartContract['name'];
$description = $smartContract['description'];

require_once __DIR__. '/../common/include/top.php';
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
<!--        <tr>-->
<!--            <td>Code</td>-->
<!--            <td>-->
<!--                <pre>--><?php //echo $code ?><!--</pre>-->
<!--            </td>-->
<!--        </tr>-->
        <tr>
            <td>Signature</td>
            <td><?php echo $smartContract['signature'] ?></td>
        </tr>
        <tr>
            <td>Balance</td>
            <td><?php echo $balance ?></td>
        </tr>
        <tr>
            <td>Name</td>
            <td><?php echo $name ?></td>
        </tr>
        <tr>
            <td>Description</td>
            <td><?php echo $description ?></td>
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
                    <td><?php echo is_array($val) ? print_r($val, 1) : $val ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<h3>Interface</h3>

<div class="table-responsive">
    <table class="table table-sm table-striped">
      <tr>
        <td class="fw-bold">Version</td>
        <td><?php echo $interface['version'] ?></td>
      </tr>
      <tr>
        <td class="fw-bold">Properties</td>
        <td>
            <table class="table table-sm table-striped">
                <?php foreach ($interface['properties'] as $property) { ?>
                    <tr>
                        <td>
                            <?php
                                echo '$'.$property['name'];
                                if($property['type'=="map"]) {
                                    echo "[]";
                                }
                            ?>
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
                                    echo '$'.$param;
                                    if($ix < count($method['params'])-1) echo ", ";
                                }
                            }
                            echo ")";
                            ?>
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
                                    echo '$'.$param;
                                    if($ix < count($view['params'])-1) echo ", ";
                                }
                            }
                            echo ")";
                            ?>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        </td>
      </tr>
    </table>
</div>

<!--<pre>-->
<!--    --><?php //print_r($interface) ?>
<!--</pre>-->


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
