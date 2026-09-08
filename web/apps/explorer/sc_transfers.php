<?php
require_once dirname(__DIR__)."/apps.inc.php";
define('PAGE', true);
define('APP_NAME', 'Explorer');
require_once ROOT . '/web/apps/explorer/include/functions.php';

if (!FEATURE_SMART_CONTRACTS) {
    header('location: /apps/explorer');
    exit;
}

$transfers = SmartContract::getNativeTransfers(200);
require_once __DIR__ . '/../common/include/top.php';
?>

<ol class="breadcrumb m-0 ps-0 h4">
    <li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
    <li class="breadcrumb-item active">Smart-contract transfers</li>
</ol>

<div class="table-responsive">
    <table class="table table-sm table-striped dataTable">
        <thead class="table-light">
        <tr>
            <th>Height</th>
            <th>Transaction</th>
            <th>Smart contract</th>
            <th>Recipient</th>
            <th>Sequence</th>
            <th class="text-end">Amount</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($transfers as $transfer) { ?>
            <tr>
                <td><?php echo explorer_height_link($transfer['height']) ?></td>
                <td>
                    <?php if (!empty($transfer['tx_id'])) { ?>
                        <?php echo explorer_tx_link($transfer['tx_id'], true) ?>
                    <?php } else { ?>
                        <span class="text-muted">legacy</span>
                    <?php } ?>
                </td>
                <td><?php echo explorer_address_link($transfer['from']) ?></td>
                <td><?php echo explorer_address_link($transfer['to']) ?></td>
                <td><?php echo intval($transfer['seq']) ?></td>
                <td class="text-end"><?php echo h(num($transfer['amount'])) ?> PHP</td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../common/include/bottom.php'; ?>
