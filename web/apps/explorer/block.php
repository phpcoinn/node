<?php
require_once dirname(__DIR__)."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Explorer");

$blockCount = Block::getHeight();
$height = $blockCount;
if(isset($_GET['height'])) {
	$height = $_GET['height'];
    if($height < 1 || $height > $blockCount) {
        $height = $blockCount;
    }
    $block = Block::getAtHeight($height);
    if(!$block) {
        header("location: /apps/explorer");
        exit;
    }
} elseif (isset($_GET['id'])) {
	$id = $_GET['id'];
	$block = Block::getById($id);
	if(!$block) {
		header("location: /apps/explorer");
		exit;
	}
	$height = $block['height'];
}



if(isset($_GET['action'])) {
	$action = $_GET['action'];
	if($action == "check") {
		$id = $_GET['id'];
		if(empty($id)) {
			header("location: /apps/explorer");
			exit;
		}
		$block = Block::export($id);
		$res = Block::getFromArray($block)->verifyBlock();
		if (!$res) {
			die("Block not valid");
		}

		die("Block is valid");

	}
}


$transactions = Transaction::getForBlock($height);

?>
<?php
require_once __DIR__. '/../common/include/top.php';
?>

<div class="d-flex justify-content-between align-items-center">
    <ol class="breadcrumb m-0 ps-0 h4">
        <li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
        <li class="breadcrumb-item">Block</li>
        <li class="breadcrumb-item active text-truncate"><?php echo $block['id'] ?></li>
    </ol>
    <div class="">
        <ul class="pagination mb-0">
		    <?php if ($height < $blockCount) { ?>
            <li class="page-item">
                <a class="page-link" href="/apps/explorer/block.php?height=<?php echo $blockCount ?>" aria-label="Next">
                    <span aria-hidden="true">Last</span>
                </a>
            <li>
            <li class="page-item">
                <a class="page-link" href="/apps/explorer/block.php?height=<?php echo $block['height']+1 ?>" aria-label="Next">
                    <span aria-hidden="true">Next</span>
                </a>
            <li>
			    <?php } ?>
			    <?php if($height > 1) { ?>
            <li class="page-item">
                <a class="page-link" href="/apps/explorer/block.php?height=<?php echo $block['height']-1 ?>" aria-label="Previous">
                    <span aria-hidden="true">Previous</span>
                </a>
            <li>
            <li class="page-item">
                <a class="page-link" href="/apps/explorer/block.php?height=1" aria-label="Previous">
                    <span aria-hidden="true">Genesis</span>
                </a>
            <li>
			    <?php } ?>
        </ul>
    </div>
</div>




<div class="table-responsive">
    <table class="table table-condensed table-striped table-sm">
        <tbody>
            <tr>
                <td>Height</td>
                <td><?php echo $block['height'] ?></td>
            </tr>
            <tr>
                <td>Id</td>
                <td><?php echo $block['id'] ?></td>
            </tr>
            <tr>
                <td>Generator</td>
                <td>
                    <a href="/apps/explorer/address.php?address=<?php echo $block['generator'] ?>">
                        <?php echo $block['generator'] ?>
                    </a>
                </td>
            </tr>
            <tr>
                <td>Miner</td>
                <td>
                    <a href="/apps/explorer/address.php?address=<?php echo $block['miner'] ?>">
                        <?php echo $block['miner'] ?>
                    </a>
                </td>
            </tr>
            <tr>
                <td>Date</td>
                <td>
                    <?php echo date("Y-m-d H:i:s",$block['date']) . " (" . $block['date'].")" ?>
                </td>
            </tr>
            <tr>
                <td>Nonce</td>
                <td><?php echo $block['nonce'] ?></td>
            </tr>
            <tr>
                <td>Signature</td>
                <td><?php echo $block['signature'] ?></td>
            </tr>
            <tr>
                <td>Difficulty</td>
                <td><?php echo $block['difficulty'] ?></td>
            </tr>
            <tr>
                <td>Transactions</td>
                <td><?php echo $block['transactions'] ?></td>
            </tr>
            <tr>
                <td>Version</td>
                <td><?php echo $block['version'] ?></td>
            </tr>
            <tr>
                <td>Argon</td>
                <td><?php echo $block['argon'] ?></td>
            </tr>
            <tr>
                <td>Block time</td>
                <td>
                    <?php
                    if($height > 1) {
	                    $prevBlock = Block::getAtHeight($height - 1);
                        echo $block['date']-$prevBlock['date'];
                    }
                    ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>
<a href="<?php echo $_SERVER['PHP_SELF'] ?>?id=<?php echo $block['id'] ?>&action=check" class="btn btn-info">Check</a>
    <h4>Transactions</h4>
<div class="table-responsive">
    <table class="table table-striped table-hover table-sm">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Src</th>
                <th>Dst</th>
                <th class="text-end">Value</th>
<!--                <th>Fee</th>-->
                <th>Type</th>
                <th>Date</th>
            </tr>
        </thead>
        <?php foreach ($transactions as $tx) {
            if($tx->type == TX_TYPE_REWARD) {
	            $src = null;
            } else {
	            $src = Account::getAddress($tx['public_key']);
            }

            ?>
            <tbody>
                <tr>
                    <td><a href="/apps/explorer/tx.php?id=<?php echo $tx['id'] ?>"><?php echo $tx['id'] ?><a/></td>
                    <td>
                        <?php if($src) { ?>
                            <a href="/apps/explorer/address.php?address=<?php echo $src ?>"><?php echo $src ?></a>
                        <?php } ?>
                    </td>
                    <td><a href="/apps/explorer/address.php?address=<?php echo $tx['dst'] ?>"><?php echo $tx['dst'] ?></a></td>
                    <td class="text-end"><?php echo num($tx['val']) ?></td>
<!--                    <td>--><?php //echo num($tx['fee']) ?><!--</td>-->
                    <td><?php echo $tx['type_label'] ?></td>
                    <td><?php echo display_date($tx['date']) ?></td>
                </tr>
            </tbody>
        <?php } ?>
    </table>
</div>
<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
