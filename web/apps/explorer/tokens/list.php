<?php
require_once dirname(dirname(__DIR__))."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Explorer");
require_once ROOT. '/web/apps/explorer/include/functions.php';

if(!FEATURE_SMART_CONTRACTS) {
    header("location: /apps/explorer");
    exit;
}

function stringToHex($string) {
    // Initialize an empty string to hold the hexadecimal result
    $hex = '';

    // Iterate over each character in the input string
    for ($i = 0; $i < strlen($string); $i++) {
        // Convert each character to its ASCII value, then to hexadecimal
        $hex .= dechex(ord($string[$i]));
    }

    return $hex;
}

/**
 * Convert a hex color to its RGB components.
 *
 * @param string $hex The hex color code (e.g., "#ffcc00" or "ffcc00").
 * @return array An array with RGB components.
 */
function hexToRgb($hex) {
    // Remove the hash at the start if it's there
    $hex = ltrim($hex, '#');

    // If shorthand notation (e.g., #fc0), expand it
    if (strlen($hex) == 3) {
        $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
        $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
        $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }

    return [$r, $g, $b];
}

/**
 * Calculate the luminance of an RGB color.
 *
 * @param array $rgb An array containing RGB components.
 * @return float The calculated luminance.
 */
function calculateLuminance($rgb) {
    // Normalize RGB values to the range 0-1
    $r = $rgb[0] / 255;
    $g = $rgb[1] / 255;
    $b = $rgb[2] / 255;

    // Apply the sRGB luminance formula
    $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

    return $luminance;
}

/**
 * Determine the best text color (black or white) for a given background.
 *
 * @param string $backgroundColor The hex color code of the background.
 * @return string "black" or "white" depending on which contrasts better.
 */
function getContrastingTextColor($backgroundColor) {
    // Convert background color to RGB
    $rgb = hexToRgb($backgroundColor);

    // Calculate luminance
    $luminance = calculateLuminance($rgb);

    // Return white for dark backgrounds and black for light backgrounds
    return $luminance > 0.5 ? '#000000' : '#FFFFFF'; // Return in hex format
}

require_once __DIR__. '/../../common/include/top.php';

$loggedIn = false;
if(isset($_SESSION['account'])) {
    $balance = Account::getBalance($_SESSION['account']['address']);
    $loggedIn = true;
    $address = $_SESSION['account']['address'];
}

global $db;

$sql="select sc.address, sc.metadata
from smart_contracts sc
where json_extract(sc.metadata, '$.class') = 'ERC-20'
order by sc.height desc";
$allTokens = $db->query($sql);


if($loggedIn) {
    $sql="select * from (
    select sc.address, sc.metadata, scs.var_value as balance,
           row_number() over (partition by sc.address, scs.var_key order by scs.height desc) as rn
    from smart_contracts sc
    join smart_contract_state scs on (scs.sc_address = sc.address)
    where json_extract(sc.metadata, '$.class') = 'ERC-20'
    and scs.variable = 'balances' and scs.var_key = ?) as states
    where states.rn =1";

    $tokens = $db->run($sql,[$address], false);
}

$sql="select sc.address, scs.var_value as decimals from smart_contracts sc
         join smart_contract_state scs on (sc.height = scs.height)
where json_extract(sc.metadata, '$.class') = 'ERC-20' and scs.variable = 'decimals'";
$rowsDecimals = $db->run($sql);
$scDecimals = [];
foreach($rowsDecimals as $row) {
    $scDecimals[$row['address']] = $row['decimals'];
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
            <th>Description</th>
            <th>Address</th>
            <th>Initial supply</th>
            <?php if($loggedIn) { ?>
                <th>Balance</th>
            <?php } ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($allTokens as $token) {
            $metadata = json_decode($token['metadata'], true);
            $decimals = $scDecimals[$token['address']];
            $balance = bcdiv($token['balance'], bcpow(10, $decimals), $decimals);
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
                            <?php echo $symbol ?>
                        </div>
                    <?php } ?>
                </td>
                <td>
                    <a href="/apps/explorer/tokens/token.php?id=<?php echo $token['address'] ?>"><?php echo $metadata['name'] ?></a>
                </td>
                <td>
                    <a href="/apps/explorer/tokens/token.php?id=<?php echo $token['address'] ?>"><?php echo $symbol ?></a>
                </td>
                <td>
                    <?php echo $metadata['description'] ?>
                </td>
                <td>
                    <?php echo $token['address'] ?>
                </td>
                <td>
                    <?php if($metadata['initialSupply']) echo num($metadata['initialSupply'], $metadata['decimals']) ?>
                </td>
                <?php if($loggedIn) { ?>
                    <td>
                        <?php echo num($balance,$decimals) ?>
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
    }
</style>
<a href="/apps/explorer/tokens/create.php" class="btn btn-success">Create token</a>

<?php
require_once __DIR__ . '/../../common/include/bottom.php';
?>

