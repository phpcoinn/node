<?php
require_once dirname(__DIR__)."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Explorer");
require_once ROOT. '/web/apps/explorer/include/functions.php';

$smartContracts = SmartContract::getAll();

require_once __DIR__. '/../common/include/top.php';
?>

<ol class="breadcrumb m-0 ps-0 h4">
	<li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
	<li class="breadcrumb-item">Smart contracts</li>
</ol>

<div class="table-responsive">
    <table class="table table-sm table-striped dataTable">
        <thead class="table-light">
            <tr>
                <th>Address</th>
                <th>Height</th>
                <th>Signature</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($smartContracts as $smartContract) { ?>
                <tr>
                    <td>
                        <a href="/apps/explorer/smart_contract.php?id=<?php echo $smartContract['address'] ?>">
                            <?php echo $smartContract['address'] ?>
                        </a>
                    </td>
                    <td><?php echo $smartContract['height'] ?></td>
                    <td style="word-break: break-all"><?php echo $smartContract['signature'] ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
