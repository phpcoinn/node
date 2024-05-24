<?php
global $taskStatus,$_config;
?>
<div class="card-body">
        <p class="card-text">
        <?php if (Masternode::isLocalMasternode()) {

            $mn = Masternode::get($_config['masternode_public_key']);
            if(!$mn) {
                $valid = false;
                $err = "Masternode not found in list";
            } else {
                $mn = Masternode::fromDB($mn);
                $height = Block::getHeight();
                $valid = $mn->check($height, $err);

                global $db;
                $sql="select min(b.date) as min_date, max(b.date) as max_date, sum(t.val) as value, t.dst, count(t.id) as cnt,
                                           (max(t.date) - min(t.date))/60/60/24 as running,
                                           sum(t.val)  / ((max(t.date) - min(t.date))/60/60/24) as daily
                                    from blocks b
                                             join transactions t on b.id = t.block and b.height = t.height and t.type = 0 and t.message = 'masternode'
                                    where b.masternode = :id";
                $mnStat = $db->row($sql, [':id'=>$mn->id]);
            }

            ?>

            <?php if (!$valid) { ?>
                <div class="alert alert-danger">
                    <strong>Masternode is invalid!</strong>
                    <br/>
                    <?php echo $err ?>
                </div>
            <?php } ?>

            Address:  <?php echo explorer_address_link(Account::getAddress($_config['masternode_public_key'])) ?>

            <?php if ($mnStat['dst'] != $mn->id) { ?>
                <br/>
                Reward address: <?php echo explorer_address_link($mnStat['dst']) ?>

            <?php } ?>

            <?php if ($mnStat) { ?>
                <br/>
                Total rewards: <?php echo $mnStat['cnt'] ?><br/>
                Total value: <?php echo $mnStat['value'] ?><br/>
                Running days: <?php echo $mnStat['running'] ?><br/>
                Daily income: <?php echo $mnStat['daily'] ?><br/>
            <?php } ?>

        <?php } ?>
        </p>
</div>
