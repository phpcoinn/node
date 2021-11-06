<?php
require_once dirname(__DIR__)."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Explorer");
$id = $_GET['id'];
$tx = Transaction::get_transaction($id);

if(!$tx) {
	$tx = Transaction::getMempoolById($id);
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
        } else {
		    $tx = Transaction::getById($id);
        }
	    $res = $tx->check();
	    if($res) {
	        die("Transaction valid");
        } else {
	        die("Transaction not valid");
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
        if($tx['type']==TX_TYPE_REWARD) {
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
            <td><?php echo $tx['type'] ?></td>
        </tr>
        <tr>
            <td>Value</td>
            <td><?php echo $tx['val'] ?></td>
        </tr>
        <tr>
            <td>Fee</td>
            <td><?php echo $tx['fee'] ?></td>
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
    </table>
</div>
    <a href="<?php echo $_SERVER['PHP_SELF'] ?>?id=<?php echo $tx['id'] ?>&action=check" class="btn btn-info">Check</a>
<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>

