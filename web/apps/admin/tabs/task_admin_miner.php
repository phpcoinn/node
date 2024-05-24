<?php
global $_config;
$minerStatFile = NodeMiner::getStatFile();
if(file_exists($minerStatFile)) {
    $minerStat = file_get_contents($minerStatFile);
    $minerStat = json_decode($minerStat, true);
}
?>
<div class="card-body">
    <p class="card-text">Address: <?php echo explorer_address_link(Account::getAddress($_config['miner_public_key'])) ?></p>
    <div class="row">
        <div class="col-sm-12">
            <?php if ($minerStat) { ?>
                <div><strong>Miner stat:</strong></div>
                <div class="flex-row d-flex justify-content-between flex-wrap">
                    <div>Started:</div>
                    <div><?php echo display_date($minerStat['started']) ?></div>
                </div>
                <div class="flex-row d-flex justify-content-between flex-wrap">
                    <div>Hashes:</div>
                    <div><?php echo $minerStat['hashes'] ?></div>
                </div>
                <div class="flex-row d-flex justify-content-between flex-wrap">
                    <div>Submits:</div>
                    <div><?php echo $minerStat['submits'] ?></div>
                </div>
                <div class="flex-row d-flex justify-content-between flex-wrap">
                    <div>Accepted:</div>
                    <div><?php echo $minerStat['accepted'] ?></div>
                </div>
                <div class="flex-row d-flex justify-content-between flex-wrap">
                    <div>Rejected:</div>
                    <div><?php echo $minerStat['rejected'] ?></div>
                </div>
                <div class="flex-row d-flex justify-content-between flex-wrap">
                    <div>CPU:</div>
                    <div><?php echo $minerStat['cpu'] ?></div>
                </div>
                <div class="flex-row d-flex justify-content-between flex-wrap">
                    <div>Speed:</div>
                    <div><?php echo $minerStat['speed'] ?></div>
                </div>
            <?php } ?>

        </div>
    </div>
</div>
