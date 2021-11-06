<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once ROOT. '/web/apps/explorer/include/functions.php';
define("PAGE", true);
define("APP_NAME", "Faucet");
session_start();

if(!(isset($_config['faucet']) && $_config['faucet'] && !empty($_config['faucet_private_key'])
    && !empty($_config['faucet_public_key']))){
    header("location: /");
    exit;
}

$faucetAddress = Account::getAddress($_config['faucet_public_key']);
$faucetBalance = Account::getBalance($faucetAddress);


if(isset($_POST['action'])) {
    $address = $_POST['address'];
    if(empty($address) || !Account::valid($address)) {
	    $_SESSION['msg']=[['icon'=>'error', 'text'=>'Invalid or empty address']];
	    header("location: /apps/faucet/index.php");
	    exit;
    }

    $txs = Account::getMempoolTransactions($address);
    if(count($txs)>0) {
	    $_SESSION['msg']=[['icon'=>'error', 'text'=>'Address was already used']];
	    header("location: /apps/faucet/index.php");
	    exit;
    }

    if(Account::exists($address)) {
	    $_SESSION['msg']=[['icon'=>'error', 'text'=>'Address was already used']];
	    header("location: /apps/faucet/index.php");
	    exit;
    }

    $accountPublicKey = Account::publicKey($address);

    if(!empty($accountPublicKey)) {
	    $_SESSION['msg']=[['icon'=>'error', 'text'=>'Address can not be used in faucet']];
	    header("location: /apps/faucet/index.php");
	    exit;
    }

    $val = 0.01;
	$fee = 0;
	$msg = "faucet";
	$date = time();

	$transaction = new Transaction($_config['faucet_public_key'],$address,$val,TX_TYPE_SEND,$date,$msg);
	$transaction->sign($_config['faucet_private_key']);
	$hash = $transaction->addToMemPool($error);
	if($hash === false) {
		$_SESSION['msg']=[['icon'=>'error', 'text'=>'Transaction can not be sent: '.$error]];
		header("location: /apps/faucet/index.php");
		exit;
	} else {
		$_SESSION['msg']=[['icon'=>'success', 'text'=>'Transaction sent! Id of transaction: '.$hash]];
		header("location: /apps/faucet/index.php");
		exit;
	}
}

?>
<?php
require_once __DIR__. '/../common/include/top.php';
?>

<div class="row">
    <div class="col-7">
        <form method="post">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">PHPCoin Faucet</h4>
                    <p class="card-title-desc">Use this faucet to receive some amount of PHP coin</p>
                </div>
                <div class="card-body p-4">
                    <div class="mb-1">
                        <label class="form-label" for="address">Address</label>
                        <input type="text" id="address" name="address" class="form-control" value=""/>
                        <input type="hidden" name="action" value="faucet"/>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top text-muted">
                    <button type="submit" class="btn btn-success">Receive</button>
                </div>
            </div>
        </div>
    </form>
    <div class="col-5">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title-desc mb-2">Faucet address</h4>
                <p class="h4"><?php echo explorer_address_link($faucetAddress) ?></p>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h4 class="card-title-desc mb-2">Faucet balance</h4>
                <p class="h4"><?php echo num($faucetBalance) ?></p>
            </div>
        </div>
    </div>
</div>


<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>

