<?php
require_once dirname(__DIR__)."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Explorer");
require_once ROOT. '/web/apps/explorer/include/functions.php';

function getConditions($search) {
	$condition = "";
	$params = [];
	if(isset($search['type']) && is_array($search['type']) && count($search['type'])>0) {
		$list = implode(",", $search['type']);
		$condition .= " and type in ($list)";
	}
    if(isset($search['date']['from']) && !empty($search['date']['from'])) {
	    $condition.= " and date >= :dateFrom ";
	    $params[":dateFrom"]=strtotime($search['date']['from']);
    }
    if(isset($search['date']['to']) && !empty($search['date']['to'])) {
	    $condition.= " and date <= :dateTo ";
	    $params[":dateTo"]=strtotime($search['date']['to']);
    }
    if(isset($search['src']) && !empty($search['src'])) {
        $condition.= " and src like :src ";
	    $params[':src']=$search['src'];
    }
    if(isset($search['dst']) && !empty($search['dst'])) {
        $condition.= " and dst like :dst ";
	    $params[':dst']=$search['dst'];
    }
    return [$condition, $params];
}

function TransactiongetCount() {
	global $db;
    list($condition, $params) = getConditions($_GET['search']);

	$sql="select count(*) as cnt from transactions where type >= 0 $condition";
	$row = $db->row($sql, $params);
	return $row['cnt'];
}

function TransactiongetAll($dm) {
	global $db;

	list($condition, $params) = getConditions($dm['search']);

	$limit = $dm['limit'];
	$start = $dm['start'];
	$sorting = $dm['sorting'];
	$sql="select * from transactions where 1 $condition $sorting limit $start, $limit";
	return $db->run($sql, $params);
}

$link = '/apps/explorer/txs.php?';
$dm = get_data_model(TransactiongetCount(), $link, "order by height desc");
$txs = TransactiongetAll($dm);

define("HEAD_CSS", ["/apps/common/css/flatpickr.min.css","/apps/common/css/choices.min.css"]);

require_once __DIR__. '/../common/include/top.php';
?>

<ol class="breadcrumb m-0 ps-0 h4">
	<li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
	<li class="breadcrumb-item">Transactions</li>
</ol>

<form class="row mb-3" method="get" action="">
    <div class="col-lg-2">
        <input type="text" class="form-control flatpickr-input p-1 datepicker" placeholder="Date from" name="search[date][from]"
               value="<?php echo $dm['search']['date']['from']?>">
    </div>
    <div class="col-lg-2">
        <input type="text" class="form-control flatpickr-input p-1 datepicker" placeholder="Date to" name="search[date][to]"
               value="<?php echo $dm['search']['date']['to']?>">
    </div>
    <div class="col-lg-2">
        <input type="text" class="form-control p-1" placeholder="Source" value="<?php echo $dm['search']['src']?>" name="search[src]">
    </div>
    <div class="col-lg-2">
        <input type="text" class="form-control p-1" placeholder="Destination" value="<?php echo $dm['search']['dst']?>" name="search[dst]">
    </div>
    <div class="col-lg-2">
        <select class="form-control"
                id="choices-type" name="search[type][]"
                multiple>
            <option value="">Type</option>
            <option value="<?php echo TX_TYPE_REWARD ?>" <?php if(isset($dm['search']['type']) && in_array(TX_TYPE_REWARD,$dm['search']['type'])) { ?> selected<?php } ?>>Reward</option>
            <option value="<?php echo TX_TYPE_SEND ?>" <?php if(isset($dm['search']['type']) && in_array(TX_TYPE_SEND,$dm['search']['type'])) { ?> selected<?php } ?>>Transfer</option>
            <option value="<?php echo TX_TYPE_BURN ?>" <?php if(isset($dm['search']['type']) && in_array(TX_TYPE_BURN,$dm['search']['type'])) { ?> selected<?php } ?>>Burn</option>
            <option value="<?php echo TX_TYPE_MN_CREATE ?>" <?php if(isset($dm['search']['type']) && in_array(TX_TYPE_MN_CREATE,$dm['search']['type'])) { ?> selected<?php } ?>>Create masternode</option>
            <option value="<?php echo TX_TYPE_MN_REMOVE ?>" <?php if(isset($dm['search']['type']) && in_array(TX_TYPE_MN_REMOVE,$dm['search']['type'])) { ?> selected<?php } ?>>Remove masternode</option>
            <option value="<?php echo TX_TYPE_FEE ?>" <?php if(isset($dm['search']['type']) && in_array(TX_TYPE_FEE,$dm['search']['type'])) { ?> selected<?php } ?>>Fee</option>
            <option value="<?php echo TX_TYPE_SC_CREATE ?>" <?php if(isset($dm['search']['type']) && in_array(TX_TYPE_SC_CREATE,$dm['search']['type'])) { ?> selected<?php } ?>>Create Smart Contract</option>
            <option value="<?php echo TX_TYPE_SC_EXEC ?>" <?php if(isset($dm['search']['type']) && in_array(TX_TYPE_SC_EXEC,$dm['search']['type'])) { ?> selected<?php } ?>>Execute Smart Contract</option>
        </select>
    </div>
    <div class="col-lg-2 text-end">
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <a href="<?php echo $link ?>" class="btn btn-outline-primary btn-sm">Clear</a>
    </div>
</form>

<div class="table-responsive">
	<table class="table table-sm table-striped dataTable">
		<thead class="table-light">
		<tr>
			<?php echo sort_column($link, $dm, 'id', 'ID', 'text-start') ?>
			<?php echo sort_column($link, $dm, 'date', 'Date', 'text-start') ?>
			<?php echo sort_column($link, $dm, 'height', 'Height', 'text-start') ?>
			<?php echo sort_column($link, $dm, 'block', 'Block', 'text-start') ?>
			<th>Source</th>
			<?php echo sort_column($link, $dm, 'dst', 'Destination', 'text-start') ?>
			<?php echo sort_column($link, $dm, 'type', 'Type', 'text-start') ?>
			<?php echo sort_column($link, $dm, 'val', 'Value') ?>
			<?php echo sort_column($link, $dm, 'fee', 'Fee') ?>
		</tr>
		</thead>
		<tbody>
		<?php foreach($txs as $tx) { ?>
			<tr>
				<td><?php echo explorer_tx_link($tx['id'], true) ?></td>
				<td><?php echo display_date($tx['date']) ?></td>
				<td><?php echo $tx['height'] ?></td>
				<td><?php echo explorer_block_link($tx['block'], true) ?></td>
                <td><?php echo explorer_address_link($tx['src'], true) ?></td>
				<td><?php echo explorer_address_link($tx['dst'], true) ?></td>
				<td><?php echo TransactionTypeLabel($tx['type']) ?></td>
				<td class="text-end"><?php echo $tx['val'] ?></td>
				<td class="text-end"><?php echo !empty(floatval($tx['fee'])) ? $tx['fee'] : '' ?></td>
		<?php } ?>
		</tbody>
	</table>
</div>

<?php echo $dm['paginator'] ?>

<script src="/apps/common/js/choices.min.js"></script>
<script src="/apps/common/js/flatpickr.min.js"></script>
<script type="text/javascript">
    flatpickr('.datepicker', {
        enableTime: true
    });
    new Choices(
        '#choices-type',
        {
            removeItemButton: true,
            shouldSort: false
         }
    );


</script>

<style>
    .choices__inner {
        min-height: 31px;
        padding: 0 0.25rem;
     }
    .choices__list--multiple .choices__item {
        padding: 2px 5px;
    }
</style>

<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>


