<?php

if (!defined("ADMIN_TAB")) {
	exit;
}

global $action, $db;

if($action=="delete_tx") {
	$id = $_GET['id'];
    Mempool::delete($id);
	header("location: ".APP_URL."/?view=mempool");
	exit;
}

if($action=="empty_mempool") {
    Util::emptyMempool();
	header("location: ".APP_URL."/?view=mempool");
	exit;
}

$transactions = Transaction::mempool(100, true, false);

$count=count($transactions);

?>
<h3>
    Mempool Transactions
    <span class="float-end badge bg-primary"><?php echo $count ?></span>
</h3>
<div class="table-responsive">
	<table class="table table-sm table-striped">
		<thead class="table-light">
		<tr>
			<th>Id</th>
			<th>Height</th>
			<th>Date</th>
			<th>Src</th>
			<th>Dst</th>
			<th>Value</th>
			<th>Fee</th>
			<th>Type</th>
			<th>Message</th>
			<th>Peer</th>
			<th>Error</th>
            <th></th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($transactions as $transaction) { ?>
			<tr class="<?php if (!empty($transaction['error'])) { ?>table-danger<?php } ?>">
				<td>
					<a href="/apps/explorer/tx.php?id=<?php echo $transaction['id'] ?>"><?php echo $transaction['id'] ?></a>
				</td>
                <td><?php echo $transaction['height'] ?></td>
				<td><?php echo date("Y-m-d H:i:s",$transaction['date']) ?></td>
				<td><a href="/apps/explorer/address.php?address=<?php echo $transaction['src'] ?>"><?php echo $transaction['src'] ?></a></td>
				<td><a href="/apps/explorer/address.php?address=<?php echo $transaction['dst'] ?>"><?php echo $transaction['dst'] ?></a></td>
				<td><?php echo $transaction['val'] ?></td>
				<td><?php echo $transaction['fee'] ?></td>
				<td><?php echo $transaction['type'] ?></td>
				<td style="word-break: break-all"><?php echo $transaction['message'] ?></td>
				<td><?php echo $transaction['peer'] ?></td>
				<td><?php echo $transaction['error'] ?></td>
                <td>
                    <a class="btn btn-danger btn-xs" href="<?php echo APP_URL ?>/?view=mempool&action=delete_tx&id=<?php echo $transaction['id']  ?>"
                       onclick="if(!confirm('Delete mempool transaction?')) return false;">Delete</a>
                </td>
			</tr>
		<?php } ?>
		</tbody>
	</table>
</div>
<a href="<?php echo APP_URL ?>/?view=mempool&action=empty_mempool" class="btn btn-danger"
    onclick="if(!confirm('Confirm?')) return false">Clear mempool</a>
