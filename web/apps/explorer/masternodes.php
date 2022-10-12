<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once ROOT. '/web/apps/explorer/include/functions.php';
define("PAGE", true);
define("APP_NAME", "Explorer");


$dm = get_data_model(-1, "/apps/explorer/masternodes.php?", "order by win_height asc");

$sorting = $dm['sorting'];

$search = $dm['search'];

$condition = '';
$params = [];
if(!empty($search['masternode'])) {
	$condition .= " where m.ip = :ip or m.id = :id or m.public_key = :public_key or p.hostname=:hostname";
	$params['ip']=$search['masternode'];
	$params['id']=$search['masternode'];
	$params['public_key']=$search['masternode'];
	$params['hostname']=$search['masternode'];
}

global $db, $_config;
$sql = "select m.*, p.hostname
from masternode m
    left join peers p on (m.ip = p.ip)
    $condition
    $sorting ";
$masternodes = $db->run($sql, $params);

$block =  Block::current();
$height = $block['height'];
$elapsed = time() - $block['date'];

$winner = Masternode::getWinner($height+1);

if (Masternode::isLocalMasternode()) {
	$publicKey = $_config['masternode_public_key'];
	$local_id = Account::getAddress($publicKey);
}


$total = count($masternodes);
$valid = 0;
$invalid = 0;
$inactive = 0;
$filtered_mns = [];
foreach($masternodes as $masternode) {
	$dbMasternode = Masternode::fromDB($masternode);
	$verified = $dbMasternode->verify($height+1);
	$next_winner = $winner['public_key'] == $masternode['public_key'];
	$row_class="";
    $status = "";
	$status_class = "";
	if($verified) {
		$valid++;
		$status = "Valid";
		$status_class = "success";
	} else if (empty($masternode['ip'])) {
        $inactive++;
		$status = "Inactive";
		$status_class = "danger";
    } else  {
        $invalid++;
		$status = "Invalid";
		$status_class = "warning";
    }
    if($next_winner) {
	    $row_class = "primary fw-bold";
    }
	$masternode['row_class']=$row_class;
	$masternode['status']=$status;
	$masternode['status_class']=$status_class;
    $masternode['local']=$local_id == $dbMasternode->id;
    if(isset($search['show_inactive']) || $status != "Inactive") {
	    $filtered_mns[]=$masternode;
    }
}

$masternodes=$filtered_mns;

?>
<?php
require_once __DIR__. '/../common/include/top.php';
?>

<div class="d-flex">
    <ol class="breadcrumb m-0 ps-0 h4">
        <li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
        <li class="breadcrumb-item active">Masternodes</li>
    </ol>
    <span class="badge font-size-18 bg-primary m-auto me-0"><?php echo $total ?></span>
</div>

<div class="ms-2 mb-2">
    Last block: <?php echo $height ?> Elapsed: <?php echo $elapsed ?>
</div>

<div class="d-flex mb-2">
    <div class="flex-grow-1 d-flex">
        <div class="ms-2">
            <div class="badge rounded-pill badge-soft-danger font-size-16 fw-medium"><?php echo $inactive ?></div> Inactive
        </div>
        <div class="ms-2">
            <div class="badge rounded-pill badge-soft-success font-size-16 fw-medium"><?php echo $valid ?></div> Valid
        </div>
        <div class="ms-2">
            <div class="badge rounded-pill badge-soft-warning font-size-16 fw-medium"><?php echo $invalid ?></div> Invalid
        </div>
    </div>
    <div class="me-2 fw-bold">
        <div class="badge rounded-pill badge-soft-primary font-size-16 fw-medium">&nbsp;</div> Next winner
    </div>
</div>

<form class="row my-3" method="get" action="">
    <div class="col-lg-4">
        <input type="text" class="form-control p-1" placeholder="Address, IP, Public key" name="search[masternode]"
               value="<?php echo $dm['search']['masternode']?>">
    </div>
    <div class="col-lg-2">
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <a href="/apps/explorer/masternodes.php" class="btn btn-outline-primary btn-sm">Clear</a>
    </div>
    <div class="mt-2">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" name="search[show_inactive]" id="show_inactive" onclick="this.form.submit()"
                <?php if (isset($search['show_inactive'])) { ?>checked="checked"<?php } ?>>
            <label class="form-check-label" for="show_inactive">Show inactive</label>
        </div>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-sm table-striped dataTable">
        <thead class="table-light">
            <tr>
                <th>Public key</th>
	            <?php echo sort_column("/apps/explorer/masternodes.php?", $dm, 'id', 'Address' ,'') ?>
                <th>Status</th>
	            <?php echo sort_column("/apps/explorer/masternodes.php?", $dm, 'inet_aton(m.ip)', 'IP' ,'') ?>
                <th>Signature</th>
	            <?php echo sort_column("/apps/explorer/masternodes.php?", $dm, 'height', 'Height' ,'') ?>
                <?php echo sort_column("/apps/explorer/masternodes.php?", $dm, 'win_height', 'Win Height', '') ?>
            </tr>
        </thead>
        <tbody>
                <?php unset($masternode);
                foreach($masternodes as $masternode) { ?>
                <tr class="table-<?php echo $masternode['row_class'] ?>">
                    <td><?php echo explorer_address_pubkey($masternode['public_key']) ?></td>
                    <td><?php echo explorer_address_link($masternode['id']) ?></td>
                    <td>
                        <span class="badge rounded-pill badge-soft-<?php echo $masternode['status_class'] ?> font-size-12"><?php echo $masternode['status'] ?></span>
                        <?php if ($masternode['local']) { ?>
                            <span class="badge rounded-pill badge-soft-secondary font-size-12">Local</span>
                        <?php } ?>
                    </td>
                    <td>
                        <?php if(!empty($masternode['hostname'])) { ?>
                            <a href="<?php echo $masternode['hostname'] ?>" title="<?php echo $masternode['hostname'] ?>"><?php echo $masternode['ip'] ?></a>
                        <?php } else { ?>
                            <?php echo $masternode['ip'] ?>
                        <?php } ?>
                    </td>
                    <td><?php echo display_short($masternode['signature']) ?></td>
                    <td>
                        <a href="/apps/explorer/block.php?height=<?php echo $masternode['height'] ?>">
			                <?php echo $masternode['height'] ?>
                        </a>
                    </td>
                    <td>
                        <a href="/apps/explorer/block.php?height=<?php echo $masternode['win_height'] ?>">
		                    <?php echo $masternode['win_height'] ?>
                        </a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>


<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
