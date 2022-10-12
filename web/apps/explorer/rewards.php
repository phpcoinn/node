<?php
require_once dirname(__DIR__)."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Explorer");

$rows = Blockchain::calculateRewardsScheme(false);
$real = Blockchain::calculateRewardsScheme(true);
$height = Block::getHeight();
?>

<?php
require_once __DIR__. '/../common/include/top.php';
?>

<ol class="breadcrumb m-0 ps-0 h4">
	<li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
	<li class="breadcrumb-item active">Rewards scheme</li>
</ol>

<h3>Current block: <?php echo $height ?></h3>
<div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead class="table-light">
        <tr>
            <th>Phase</th>
            <th>Segment</th>
            <th>Start block</th>
            <th>End block</th>
            <th>Blocks</th>
            <th>Total reward</th>
            <th>Miner</th>
            <th>Generator</th>
            <th>Masternode</th>
            <th>Days duration</th>
            <th>Projected time start</th>
            <th>Real/Calculated time start</th>
            <th>Total supply</th>
        </tr>
        </thead>
        <tbody>
            <?php foreach($rows as $key=> $row) {

                if($row['end_block']) {
                    $duration = ($row['end_block'] - $row['block'] + 1) * 60;
                    $days = $duration / 60 / 60 / 24;
                } else {
	                $days = null;
                }
                $key = $row['key'];
                $has_reward = $row['total']>0;
                ?>
                <tr class="<?php if($height >= $row['block'] && $height <= $row['end_block']) { ?>table-success<?php } ?>">
                    <td><?php echo $row['phase'] ?></td>
                    <td><?php echo $row['segment'] ?></td>
                    <td><?php echo $row['block'] ?></td>
                    <td><?php if($has_reward) echo $row['end_block'] ?></td>
                    <td><?php if($has_reward) echo $row['blocks'] ?></td>
                    <td><?php echo $row['total'] ?></td>
                    <td><?php echo $row['miner'] ?></td>
                    <td><?php echo $row['gen'] ?></td>
                    <td><?php echo $row['mn'] ?></td>
                    <td><?php if($has_reward) echo $days==null ? null : round($days, 2) ?></td>
                    <td><?php echo display_date($row['time']) ?></td>
                    <td><?php echo display_date($row['real_start_time'])  ?></td>
                    <td><?php if($has_reward) echo $row['supply'] ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>




<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>

