<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once ROOT. '/web/apps/explorer/include/functions.php';
define("PAGE", true);
define("APP_NAME", "Dex");
define("HEAD_CSS", ["/apps/dex/dex.css"]);

if (!Dex::isEnabled()) {
    header("location: /apps/explorer");
    exit;
}

$info = Dex::getInfo();
$multi = !empty($info['multi']);
$tickers = Dex::getTickers();

require_once dirname(__DIR__). '/common/include/top.php';

$loggedIn = isset($_SESSION['account']);
$address = $loggedIn ? $_SESSION['account']['address'] : null;
?>

<div class="dex-hero">
    <div>
        <h3 class="mb-1">PHPCoin DEX</h3>
        <div class="text-muted">Spot markets for every ERC-20 created on this chain, quoted in PHP. Live book, no listing fee.</div>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <?php if ($info['verified']) { ?>
            <span class="badge rounded-pill bg-success">Live</span>
        <?php } elseif ($info['deployed']) { ?>
            <span class="badge rounded-pill bg-danger">Unavailable</span>
        <?php } ?>
        <input class="form-control" id="dex-search" type="search" placeholder="Search pair, name, address" style="min-width: 220px">
        <a class="btn btn-outline-secondary btn-sm" href="/apps/explorer/tokens/create.php">Create token</a>
        <a class="btn btn-outline-secondary btn-sm" href="/apps/dex/nodes.php">Nodes</a>
    </div>
</div>

<?php if (!$info['deployed']) { ?>
    <div class="alert alert-warning">
        <?php if ($info['address']) { ?>
            This node has not imported the DEX contract yet (<?php echo explorer_address_link($info['address']) ?>).
        <?php } else { ?>
            The DEX is not live on this network yet.
        <?php } ?>
    </div>
<?php } elseif (!$info['verified']) { ?>
    <div class="alert alert-danger">This market is not available for trading on this DEX.</div>
<?php } ?>

<div class="dex-terminal dex-markets-wrap">
    <div class="dex-ticker">
        <div class="dex-ticker-pair">
            Markets
            <small><?php echo count($tickers) ?> pairs · updates every few seconds</small>
        </div>
        <div class="dex-live"><span class="dot"></span><span>LIVE</span></div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm dex-markets-table mb-0">
            <thead>
            <tr>
                <th>Pair</th>
                <th>Last</th>
                <th>Bid</th>
                <th>Ask</th>
                <th>Spread</th>
                <th>Book (PHP)</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody id="dex-markets-body">
            <?php if (count($tickers) === 0) { ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No ERC-20s on this network yet. Create a token and it shows up here.</td></tr>
            <?php } ?>
            <?php foreach ($tickers as $row) { ?>
                <tr onclick="location.href='/apps/dex/market.php?token=<?php echo urlencode($row['token']) ?>'">
                    <td>
                        <div class="dex-pair-name">
                            <?php echo h($row['pair'] ?? '') ?>
                            <span><?php echo h($row['name'] ?? '') ?></span>
                        </div>
                    </td>
                    <td><?php echo h($row['last_display'] ?? '—') ?></td>
                    <td class="dex-up"><?php echo h($row['best_bid_display'] ?? '—') ?></td>
                    <td class="dex-down"><?php echo h($row['best_ask_display'] ?? '—') ?></td>
                    <td><?php echo h(($row['spread_pct'] ?? null) ? ($row['spread_pct'] . '%') : '—') ?></td>
                    <td><?php echo h($row['book_php'] ?? '0') ?></td>
                    <td>
                        <?php if (intval($row['bid_count'] ?? 0) + intval($row['ask_count'] ?? 0) > 0) { ?>
                            <span class="badge rounded-pill bg-success">Book live</span>
                        <?php } else { ?>
                            <span class="badge rounded-pill bg-secondary">Empty</span>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<p class="text-muted font-size-13">
    Click a pair to open the spot terminal. Last price is the live mid of the on-chain book.
    <?php if ($loggedIn) { ?>Signed in as <?php echo explorer_address_link($address, true) ?>.<?php } ?>
</p>

<script src="/apps/dex/markets.js"></script>
<?php
require_once dirname(__DIR__). '/common/include/bottom.php';
