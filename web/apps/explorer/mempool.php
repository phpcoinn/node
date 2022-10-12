<?php
require_once dirname(__DIR__)."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Explorer");

$transactions = Transaction::mempool(100, false, false);



?>
<?php
require_once __DIR__. '/../common/include/top.php';
?>

<ol class="breadcrumb m-0 ps-0 h4">
    <li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
    <li class="breadcrumb-item active">Mempool</li>
</ol>

<div class="table-responsive">
<table class="table table-sm table-striped">
	<thead class="table-light">
		<tr>
			<th>Id</th>
			<th>Date</th>
			<th>Height</th>
			<th>Src</th>
			<th>Dst</th>
			<th>Value</th>
			<th>Fee</th>
			<th>Type</th>
			<th>Message</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($transactions as $transaction) { ?>
		<tr>
			<td>
				<a href="/apps/explorer/tx.php?id=<?php echo $transaction['id'] ?>">
                    <?php echo truncate_hash($transaction['id']) ?>
                </a>
			</td>
			<td><?php echo date("Y-m-d H:i:s",$transaction['date']) ?></td>
			<td><?php echo $transaction['height'] ?></td>
			<td><?php echo explorer_address_link2($transaction['src'], true) ?></td>
			<td><?php echo explorer_address_link2($transaction['dst'], true) ?></td>
			<td><?php echo $transaction['val'] ?></td>
			<td><?php echo $transaction['fee'] ?></td>
			<td><?php echo TransactionTypeLabel($transaction['type']) ?></td>
			<td style="word-break: break-all"><?php echo $transaction['message'] ?></td>
		</tr>
		<?php } ?>
	</tbody>
</table>
</div>
<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
