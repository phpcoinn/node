<?php
require_once dirname(dirname(__DIR__))."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Explorer");
require_once ROOT. '/web/apps/explorer/include/functions.php';

if(!FEATURE_SMART_CONTRACTS) {
    header("location: /apps/explorer");
    exit;
}

require_once __DIR__. '/../../common/include/top.php';
require_once __DIR__. '/inc.php';

$loggedIn = false;
if(isset($_SESSION['account'])) {
    $balance = Account::getBalance($_SESSION['account']['address']);
    $loggedIn = true;
    $address = $_SESSION['account']['address'];
}

global $db;

$allTokens = $db->query("select t.*,
       (select ss.var_value from smart_contract_state ss
        where ss.variable = 'totalSupply' and ss.sc_address = t.address
        order by height desc limit 1)/pow(10,t.decimals) as totalSupply
from tokens t
order by t.height desc");


if($loggedIn) {

    $sql="select * from token_balances where address = ?";

    $tokenBalances = [];
    $list = $db->run($sql,[$address], false);
    foreach($list as $token) {
        $tokenBalances[$token['token']] = $token['balance'];
    }
}

?>

<div class="d-flex align-items-center">
    <h3>Tokens</h3>
    <?php if (!$loggedIn) { ?>
        <div class="ms-auto">
            Login to access your tokens
        </div>
    <?php } else { ?>
        <div class="ms-auto d-flex align-items-center gap-2">
            <?php echo explorer_address_link($_SESSION['account']['address']) ?>
            <div><?php echo $balance ?></div>
        </div>
    <?php } ?>
</div>

<div class="table-responsive">
    <table class="table table-sm table-striped dataTable">
        <thead class="table-light">
        <tr>
            <th>Image</th>
            <th>Name</th>
            <th>Symbol</th>
            <th>Address</th>
            <th>Initial supply</th>
            <th>Total supply</th>
            <?php if($loggedIn) { ?>
                <th>Your balance</th>
            <?php } ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($allTokens as $token) {
            $metadata = json_decode($token['metadata'], true);
            $decimals = $token['decimals'];
            if($loggedIn && isset($tokenBalances[$token['address']])) {
                $tokenBalance = @$tokenBalances[$token['address']];
            } else {
                $tokenBalance = 0;
            }
            $image = $metadata['image'];
            $name = $metadata['name'];
            $symbol = $metadata['symbol'];
            $color = stringToHex($token['address']);
            $indexes=[5,10,15,20,25,30];
            $c= "";
            foreach ($indexes as $index) {
                $c.=$color[$index];
            }
            ?>
            <tr>
                <td>
                    <?php if ($image) { ?>
                        <img src="<?php echo $image ?>" style="width:32px; height: 32px"/>
                    <?php } else { ?>
                        <div class="token-image-placeholder" style="background-color: #<?php echo $c ?>; color: <?php echo getContrastingTextColor("#$c") ?>">
                            <?php echo substr($symbol,0,3) ?>
                        </div>
                    <?php } ?>
                </td>
                <td>
                    <a href="/apps/explorer/tokens/token.php?id=<?php echo $token['address'] ?>">
                        <?php echo $metadata['name'] ?>
                    </a>
                    <?php if(strlen($metadata['description']) > 0) { ?>
                        <spna class="fa fa-info-circle" title="<?php echo $metadata['description'] ?>" data-bs-toggle="tooltip"></spna>
                    <?php } ?>
                </td>
                <td>
                    <a href="/apps/explorer/tokens/token.php?id=<?php echo $token['address'] ?>"><?php echo $symbol ?></a>
                </td>
                <td>
                    <?php echo explorer_address_link($token['address']) ?>
                </td>
                <td>
                    <?php if($metadata['initialSupply']) echo num($metadata['initialSupply'], $metadata['decimals']) ?>
                </td>
                <td>
                    <?php echo num($token['totalSupply'],$token['decimals']) ?>
                </td>
                <?php if($loggedIn) { ?>
                    <td>
                        <?php echo num(floatvalue($tokenBalance),$token['decimals']) ?>
                    </td>
                <?php } ?>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>
<style>
    .token-image-placeholder {
        width: 32px;
        height: 32px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: small;
        letter-spacing: -1px;
    }
</style>
<a href="/apps/explorer/tokens/create.php" class="btn btn-success">Create token</a>
<a href="/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/token_faucet" class="btn btn-info">Tokens faucet</a>

<?php
require_once __DIR__ . '/../../common/include/bottom.php';
?>

