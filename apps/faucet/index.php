<?php
require_once dirname(__DIR__)."/apps.inc.php";
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

$tx=new Transaction();

if(isset($_POST['action'])) {
    $address = $_POST['address'];
    if(empty($address) || !Account::valid($address)) {
	    $_SESSION['msg']=[['icon'=>'error', 'text'=>'Invalid or empty address']];
	    header("location: /apps/faucet/index.php");
	    exit;
    }

    $accountPublicKey = Account::publicKey($address);

    if(!empty($accountPublicKey)) {
	    $_SESSION['msg']=[['icon'=>'error', 'text'=>'Address can not be used in faucet']];
	    header("location: /apps/faucet/index.php");
	    exit;
    }

    $val = num(0.01);
	$fee = num(0);
	$msg = "faucet";
	$date = time();
	$info = $tx->getSignatureBase([
		'val' => $val,
		'fee' => $fee,
		'dst' => $address,
		'message' => $msg,
		'type' => TX_TYPE_SEND,
		'public_key' => $_config['faucet_public_key'],
		'date' => $date
	]);

	$signature=ec_sign($info, $_config['faucet_private_key']);

	$transaction = [
		"val"        => $val,
		"fee"        => $fee,
		"dst"        => $address,
		"public_key" => $_config['faucet_public_key'],
		"date"       => $date,
		"type"    => TX_TYPE_SEND,
		"message"    => $msg,
		"signature"  => $signature,
	];
	$hash = Transaction::addToMemPool($transaction, $_config['faucet_public_key'], $error);
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

Use this faucet to receive some amunt of COIN in order to start using it.

Faucet address: <?php echo $faucetAddress ?>
<br/>
Faucet balance: <?php echo num($faucetBalance) ?>

<div>
	<form method="post">

		Address:
		<input type="text" id="address" name="address" value=""/>
		<input type="hidden" name="action" value="faucet"/>
		<button type="submit">Receive</button>

	</form>
</div>


<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>

