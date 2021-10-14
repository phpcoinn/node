<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once ROOT. '/web/apps/explorer/include/functions.php';
define("PAGE", true);
define("APP_NAME", "Explorer");

require_once __DIR__. '/include/actions.php';

$blockCount = Block::getHeight();
$rowsPerPage = 10;
$pages = ceil($blockCount / $rowsPerPage);
$page = 1;

if(isset($_GET['page'])) {
    $page = $_GET['page'];
}
if($page<0) {
    $page = 1;
}
if($page > $pages) {
    $page = $pages;
}

$blocks = Block::getAll($page, $rowsPerPage);
$txCount = Transaction::getCount();
$mempoolCount = Transaction::getMempoolCount();
$addressAccount = Account::getCount();
$circulation = Account::getCirculation();
$peersCount = Peer::getCount();

$hashRate10 = round(Blockchain::getHashRate(10),2);
$hashRate100 = round(Blockchain::getHashRate(100),2);

$avgBlockTime10 = BlockChain::getAvgBlockTime(10);
$avgBlockTime100 = BlockChain::getAvgBlockTime(100);

$last = Block::getAtHeight($blockCount);
$elapsed = time() - $last['date'];
?>
<?php
    require_once __DIR__. '/../common/include/top.php';
    require_once __DIR__. '/include/search.php';
?>

<?php if(true) { ?>
<div class="row mt-3">

    <div class="col-xl-3 col-md-6">
        <div class="card card-h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-12">
                        <i class="fas fa-database me-1 h4"></i>
                        <span class="text-muted mb-3 lh-1 text-truncate h4">Blocks</span>
                        <h2 class="my-2">
                            <?php echo $blockCount  ?>
                        </h2>
                    </div>
                </div>
                <div class="text-nowrap">
                    <span class="ms-1 text-muted font-size-13">Last block before <strong><?php echo $elapsed ?></strong> seconds</span>
                </div>
            </div>
        </div>
    </div>


    <div class="col-xl-3 col-md-6">
        <div class="card card-h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-12">
                        <i class="fas fa-clock me-1 h4"></i>
                        <span class="text-muted mb-3 lh-1 text-truncate h4">
                            Average Block time
                        </span>
                    </div>
                    <div class="col-6">
                        <h2 class="my-2">
                            <span><?php echo $avgBlockTime10 ?></span>
                        </h2>
                        <div class="text-nowrap">
                            <span class="ms-1 text-muted font-size-13">last 10 blocks</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <h2 class="my-2">
                            <span><?php echo $avgBlockTime100 ?></span>
                        </h2>
                        <div class="text-nowrap">
                            <span class="ms-1 text-muted font-size-13">last 100 blocks</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card card-h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-12">
                        <i class="fas fa-hammer me-1 h4"></i>
                        <span class="text-muted mb-3 lh-1 text-truncate h4">
                            Hash rate
                        </span>
                    </div>
                    <div class="col-6">
                        <h2 class="my-2">
                            <span><?php echo $hashRate10 ?></span>
                        </h2>
                        <div class="text-nowrap">
                            <span class="ms-1 text-muted font-size-13">last 10 blocks</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <h2 class="my-2">
                            <span><?php echo $hashRate100 ?></span>
                        </h2>
                        <div class="text-nowrap">
                            <span class="ms-1 text-muted font-size-13">last 100 blocks</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card card-h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-12">
                        <i class="fas fa-coins me-1 h4"></i>
                        <span class="text-muted mb-3 lh-1 text-truncate h4">
                            Current supply
                        </span>
                        <h2 class="my-2">
                            <span><?php echo num($circulation) ?></span>
                        </h2>
                    </div>
                </div>
                <div class="text-nowrap">
                    <span class="ms-1 text-muted font-size-13">Total supply <strong><?php echo num(TOTAL_SUPPLY) ?></strong></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card card-h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-12">
                        <i class="fas fa-exchange-alt me-1 h4"></i>
                        <span class="text-muted mb-3 lh-1 text-truncate h4">Transactions</span>
                        <h2 class="my-2">
                            <?php echo $txCount  ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card card-h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-12">
                        <i class="fas fa-hourglass-start  me-1 h4"></i>
                        <span class="text-muted mb-3 lh-1 text-truncate h4">
                            <a href="/apps/explorer/mempool.php">Mempool</a>
                        </span>
                        <h2 class="my-2">
                            <?php echo $mempoolCount ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card card-h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-12">
                        <i class="fas fa-users  me-1 h4"></i>
                        <span class="text-muted mb-3 lh-1 text-truncate h4">
                            <a href="/apps/explorer/accounts.php">Accounts</a>
                        </span>
                        <h2 class="my-2">
                            <?php echo $addressAccount  ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card card-h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-12">
                        <i class="fas fa-network-wired me-1 h4"></i>
                        <span class="text-muted mb-3 lh-1 text-truncate h4">
                            <a href="/apps/explorer/peers.php">Peers</a>
                        </span>
                        <h2 class="my-2">
                            <?php echo $peersCount ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>
    </div>





</div>

<?php } ?>

    <h3>Blocks</h3>
    <div class="table-responsive">
    <table class="table table-striped table-sm table-hover">
        <thead class="table-light">
            <tr>
                <th>Height</th>
                <th>ID</th>
                <th>Generator</th>
                <th>Date</th>
                <th>Difficulty</th>
                <th>Transactions</th>
                <th>Version</th>
                <th>Block time</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($blocks as $i => $block) { ?>
                <tr>
                    <td>
                        <a href="/apps/explorer/block.php?height=<?php echo $block['height'] ?>">
                            <?php echo $block['height'] ?>
                        </a>
                    </td>
                    <td><?php echo $block['id'] ?></td>
                    <td><?php echo explorer_address_link($block['generator']) ?></td>
                    <td><?php echo display_date($block['date']) ?></td>
                    <td><?php echo $block['difficulty'] ?></td>
                    <td><?php echo $block['transactions'] ?></td>
                    <td><?php echo $block['version'] ?></td>
                    <td>
                        <?php
                        if($block['height']>1) {
	                        if ($i == count($blocks) - 1) {
		                        $pb = Block::get($block['height'] - 1);
		                        echo $block['date'] - $pb['date'];
	                        } else {
		                        echo $block['date'] - $blocks[$i + 1]['date'];
	                        }
                        }
                        ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>
    <?php
    $startPage = $page - 2;
    $endPage = $page + 2;
    if($startPage < 1)  $startPage = 1;
    if($endPage > $pages) $endPage = $pages;

    ?>
    <nav aria-label="Page navigation example">
        <ul class="pagination">
            <?php if ($page > 1) { ?>
                <li class="page-item">
                    <a class="page-link" href="/apps/explorer/?page=1" aria-label="Previous">
                        <span aria-hidden="true">First</span>
                    </a>
                <li>
                <li class="page-item">
                    <a class="page-link" href="/apps/explorer/?page=<?php echo $page - 1 ?>" aria-label="Previous">
                        <span aria-hidden="true">Previous</span>
                    </a>
                </li>
            <?php } ?>
            <?php for ($i=$startPage; $i<=$endPage; $i++) { ?>
                <li class="page-item <?php if ($i == $page) { ?>active<?php } ?>">
                    <a class="page-link" href="/apps/explorer/?page=<?php echo $i ?>"><?php echo $i ?></a>
                </li>
            <?php } ?>
            <?php if ($page < $pages) { ?>
                <li class="page-item">
                    <a class="page-link" href="/apps/explorer/?page=<?php echo $page + 1 ?>" aria-label="Next">
                        <span aria-hidden="true">Next</span>
                    </a>
                <li>
                <li class="page-item">
                    <a class="page-link" href="/apps/explorer/?page=<?php echo $pages?>" aria-label="Next">
                        <span aria-hidden="true">Last</span>
                    </a>
                </li>
            <?php } ?>
        </ul>
    </nav>

<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>


