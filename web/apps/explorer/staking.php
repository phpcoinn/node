<?php
require_once dirname(__DIR__)."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Explorer");
require_once ROOT. '/web/apps/explorer/include/functions.php';

global $db;

$link = '/apps/explorer/staking.php?';

function getConditions($search) {
	$condition = "";
	$params = [];
	if(isset($search['address']) && !empty($search['address'])) {
		$condition.= " and t.dst like :dst ";
		$params[':dst']=$search['address'];
	}
	return [$condition, $params];
}

list($condition, $params) = getConditions($_GET['search']);

$sql="select count(distinct t.dst) as cnt
from transactions t
         left join blocks b on (t.block = b.id)
         left join accounts a on (a.id = t.dst)
where t.type = 0 and t.message = 'stake' $condition
  and b.generator <> t.dst and t.height > (select max(height) from blocks b) - 1440";
$count = $db->single($sql, $params);

$dm = get_data_model($count, $link, "order by cnt desc");


$page = $dm['page'];
$limit = $dm['limit'];
$start = ($page-1)*$limit;
$sorting = 'order by cnt desc';
if(isset($dm['sort'])) {
	$sorting = ' order by '.$dm['sort'];
	if(isset($dm['order'])){
		$sorting.= ' ' . $dm['order'];
	}
}

$sql="select max(t.height) as height, t.dst, sum(t.val) as earned, a.balance, count(t.id) as cnt
from transactions t
         left join blocks b on (t.block = b.id)
         left join accounts a on (a.id = t.dst)
where t.type = 0 and t.message = 'stake'
  and b.generator <> t.dst and t.height > (select max(height) from blocks b) - 1440
    $condition
group by t.dst
$sorting limit $start, $limit";

$staking_stat = $db->run($sql, $params);

$height=Block::getHeight();
$maturity=Blockchain::getStakingMaturity($height);
$min_balance=Blockchain::getStakingMinBalance($height);

$sql="select a1.id, a1.balance, a1.max_height - a1.height as maturity,
       case when a1.max_height - a1.height >= $maturity then (a1.max_height - a1.height)*a1.balance else 0 end as weight
from (
  select a.id,
         a.balance,
         a.height,
         (select max(height) from blocks b) as max_height
  from accounts a
  where a.height is not null
    and a.balance >= $min_balance
) as a1
order by weight desc, maturity desc, a1.balance
 limit 10";

$staking_candidates = $db->run($sql);

$sql="select t.height, t.dst, t.val, a.balance
from transactions t
left join blocks b on (t.block = b.id)
left join accounts a on (a.id = t.dst)
where t.type = 0 and t.message = 'stake'
and b.generator <> t.dst
order by t.height desc
limit 10";

$last_stakes = $db->run($sql);

?>
<?php
require_once __DIR__. '/../common/include/top.php';
?>
<ol class="breadcrumb m-0 ps-0 h4">
	<li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
	<li class="breadcrumb-item active">Staking</li>
</ol>

<div class="row">
    <div class="col-12">
        <h4>Staking statistics <span class="text-muted">(Last 24h)</span></h4>
        <form class="row mb-3" method="get" action="">
            <div class="col-lg-10">
                <input type="text" class="form-control p-1" placeholder="Address" value="<?php echo $dm['search']['address']?>" name="search[address]">
            </div>
            <div class="col-lg-2 text-end">
                <button type="submit" class="btn btn-primary btn-sm">Search</button>
                <a href="<?php echo $link ?>" class="btn btn-outline-primary btn-sm">Clear</a>
            </div>
        </form>
        Staking maturity: <?php echo $maturity ?> |
        Staking minimum balance: <?php echo $min_balance ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped dataTable">
                <thead class="table-light">
                    <tr>
	                    <?php echo sort_column('/apps/explorer/staking.php?',$dm,'height','Height','') ?>
                        <th>Address</th>
	                    <?php echo sort_column('/apps/explorer/staking.php?',$dm,'cnt','Count','') ?>
	                    <?php echo sort_column('/apps/explorer/staking.php?',$dm,'earned','Earned','') ?>
	                    <?php echo sort_column('/apps/explorer/staking.php?',$dm,'balance','Balance','') ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($staking_stat as $row) { ?>
                        <tr>
                            <td><?php echo $row['height'] ?></td>
                            <td><?php echo explorer_address_link($row['dst']) ?></td>
                            <td><?php echo $row['cnt'] ?></td>
                            <td><?php echo $row['earned'] ?></td>
                            <td><?php echo $row['balance'] ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
	    <?php echo $dm['paginator'] ?>
    </div>
    <hr class="mt-5"/>
    <div class="col-xl-6">
        <h4>Next stakers</h4>
        <div class="table-responsive">
            <table class="table table-sm table-striped dataTable">
                <thead>
                    <tr>
                        <th>Address</th>
                        <th>Balance</th>
                        <th>Maturity</th>
                        <th>Weight</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($staking_candidates as $row) { ?>
                        <tr>
                            <td><?php echo explorer_address_link($row['id']) ?></td>
                            <td><?php echo $row['balance'] ?></td>
                            <td><?php echo $row['maturity'] ?></td>
                            <td><?php echo $row['weight'] ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-xl-6">
        <h4>Last stakers</h4>
        <div class="table-responsive">
            <table class="table table-sm table-striped dataTable">
                <thead>
                    <tr>
                        <th>Height</th>
                        <th>Address</th>
                        <th>Amount</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($last_stakes as $row) { ?>
                        <tr>
                            <td><?php echo $row['height'] ?></td>
                            <td><?php echo explorer_address_link($row['dst']) ?></td>
                            <td><?php echo $row['val'] ?></td>
                            <td><?php echo $row['balance'] ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
