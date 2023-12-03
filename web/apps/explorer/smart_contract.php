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

$state = SmartContractEngine::loadState($id);

global $db;
$sql="select * from transactions t where t.type in (5,6,7)
and (t.src = '$id' or t.dst='$id')
order by t.height desc";
$txs = $db->run($sql);

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
            <td><?php echo $smartContract['address'] ?></td>
        </tr>
        <tr>
            <td>Height</td>
            <td><?php echo $smartContract['height'] ?></td>
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
                <td><?php echo $tx['height'] ?></td>
                <td><?php echo $tx['id'] ?></td>
                <td><?php echo $tx['src']==$id ? $tx['dst'] : $tx['src'] ?></td>
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
