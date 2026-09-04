<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once ROOT. '/web/apps/explorer/include/functions.php';
define("PAGE", true);
define("APP_NAME", "Dex");

if (!Dex::isEnabled()) {
    header("location: /apps/explorer");
    exit;
}

$nodes = Dex::getPeerNodes();
$info = Dex::getInfo();

require_once dirname(__DIR__). '/common/include/top.php';
?>

<ol class="breadcrumb m-0 ps-0 h4">
    <li class="breadcrumb-item"><a href="/apps/dex">DEX</a></li>
    <li class="breadcrumb-item active">Nodes</li>
</ol>

<h3>DEX nodes</h3>
<p class="text-muted">
    These nodes all show the same market
    (<?php echo $info['address'] ? explorer_address_link($info['address']) : '<span class="text-muted">not deployed</span>' ?>).
    If one node is offline, open the DEX on another.
</p>

<div class="table-responsive">
    <table class="table table-sm table-striped dataTable">
        <thead class="table-light">
        <tr>
            <th>Host</th>
            <th>Height</th>
            <th>Version</th>
            <th>Contract</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if (count($nodes) === 0) { ?>
            <tr><td colspan="5" class="text-muted">No DEX nodes are listed yet.</td></tr>
        <?php } ?>
        <?php foreach ($nodes as $node) { ?>
            <tr>
                <td>
                    <?php echo h($node['hostname']) ?>
                    <?php if (!empty($node['local'])) { ?>
                        <span class="badge rounded-pill badge-soft-secondary">This node</span>
                    <?php } ?>
                </td>
                <td><?php echo (int)$node['height'] ?></td>
                <td><?php echo h($node['version']) ?></td>
                <td>
                    <?php if (!empty($node['dex_contract'])) { ?>
                        <?php echo explorer_address_link($node['dex_contract'], true) ?>
                    <?php } else { ?>
                        <span class="text-muted">—</span>
                    <?php } ?>
                </td>
                <td class="text-end">
                    <a class="btn btn-primary btn-sm" href="<?php echo h($node['url']) ?>" <?php echo empty($node['local']) ? 'target="_blank"' : '' ?>>Open DEX</a>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<?php
require_once dirname(__DIR__). '/common/include/bottom.php';
