<?php
require_once dirname(__DIR__)."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Explorer");
$id = $_GET['id'];
$tx = Transaction::get_transaction($id);
$tx_height = $tx['height'];
$fee_ratio = Blockchain::getFee($tx_height);
$mempool = false;
if(!$tx) {
	$tx = Transaction::getMempoolById($id);
	$mempool = true;
    if(!$tx) {
        header("location: /apps/explorer");
        exit;
    }
}
if(isset($_GET['action'])) {
    $action = $_GET['action'];
    if($action == "check") {
	    $tx = Transaction::getMempoolById($id);
	    if($tx) {
		    $tx = Transaction::getFromArray($tx);
		    $tx->mempool = true;
        } else {
		    $tx = Transaction::getById($id);
        }
	    $res = $tx->verify($tx_height, $err);
	    if($res) {
	        die("Transaction valid");
        } else {
	        die("Transaction not valid $err");
        }
    }
}

?>
<?php
require_once __DIR__. '/../common/include/top.php';
?>

<ol class="breadcrumb m-0 ps-0 h4">
    <li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
    <li class="breadcrumb-item">Transaction</li>
    <li class="breadcrumb-item active text-truncate"><?php echo $tx['id'] ?></li>
</ol>
<div class="table-responsive">
    <table class="table table-sm table-striped">
        <tr>
            <td>id</td>
            <td><?php echo $tx['id'] ?></td>
        </tr>
        <tr>
            <td>height</td>
            <td><?php echo $tx['height'] ?></td>
        </tr>
        <tr>
            <td>Block</td>
            <td><?php echo $tx['block'] ?></td>
        </tr>
        <tr>
            <td>Confirmations</td>
            <td><?php echo $tx['confirmations'] ?></td>
        </tr>
        <tr>
            <td>Date</td>
            <td><?php echo $tx['date'] ?></td>
        </tr>
        <?php
        if($tx['type']==TX_TYPE_REWARD || $tx['type']==TX_TYPE_FEE) {
            $src= null;
        } else {
            $src = Account::getAddress($tx['public_key']);
        }
        ?>
        <tr>
            <td>Source</td>
            <td>
                <?php if($src) { ?>
                    <?php echo Account::getAddress($tx['public_key']) ?>
                <?php } ?>
            </td>
        </tr>
        <tr>
            <td>Destination</td>
            <td><?php echo $tx['dst'] ?></td>
        </tr>
        <tr>
            <td>Type</td>
            <td>
                <?php echo TransactionTypeLabel($tx['type']) ?> (<?php echo $tx['type'] ?>)
                <?php
                if($tx['type']==TX_TYPE_SC_CREATE) {
                    echo '<a href="/apps/explorer/smart_contract.php?id='.$tx['dst'].'">More info</a>';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td>Value</td>
            <td><?php echo $tx['val'] ?></td>
        </tr>
        <tr>
            <td>Fee</td>
            <td>
                <?php echo $tx['fee'] ?>
                <?php if($mempool) { ?>
                    (<?php echo number_format($fee_ratio,5) ?>)
                <?php } ?>
            </td>
        </tr>
        <tr>
            <td>Message</td>
            <td><?php echo $tx['message'] ?></td>
        </tr>
        <tr>
            <td>Public key</td>
            <td><?php echo $tx['public_key'] ?></td>
        </tr>
        <tr>
            <td>Signature</td>
            <td><?php echo $tx['signature'] ?></td>
        </tr>
        <tr>
            <td>Data</td>
            <td style="word-break: break-all"><?php echo $tx['data'] ?></td>
        </tr>
    </table>

    <?php if ($tx['type']==TX_TYPE_SC_EXEC) {
        $sc_data = json_decode(base64_decode($tx['message']), true);
        if(!is_array($sc_data['params'])) {
	        $sc_data['params'] = [$sc_data['params']];
        }
        ?>
        <h3>Smart Contract</h3>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <tr>
                    <td>Contract</td>
                    <td>
                        <a href="/apps/explorer/smart_contract.php?id=<?php echo $tx['dst'] ?>">
                            <?php echo $tx['dst'] ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <td>Method</td>
                    <td><?php echo $sc_data['method'] ?></td>
                </tr>
                <tr>
                    <td>Params</td>
                    <td><?php echo implode("<br/>", $sc_data['params']) ?></td>
                </tr>
            </table>
        </div>
    <?php } ?>

</div>
    <a href="<?php echo $_SERVER['PHP_SELF'] ?>?id=<?php echo $tx['id'] ?>&action=check" class="btn btn-info">Check</a>
<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>

