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

global $db, $_config;

if(isset($_POST['action'])) {

    if(!$_config['testnet']) {
        $captcha = $_POST['captcha'];
        if (empty($captcha)) {
            $_SESSION['msg'] = [['icon' => 'error', 'text' => 'Image text not entered']];
            header("location: /apps/faucet/index.php");
            exit;
        }

        if (!isset($_SESSION['captcha_text']) || $_SESSION['captcha_text'] != $captcha) {
            $_SESSION['msg'] = [['icon' => 'error', 'text' => 'Image text is not correct']];
            header("location: /apps/faucet/index.php");
            exit;
        }
    }

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

    //check remote addr
    $ip = $_SERVER['REMOTE_ADDR'];
    $faucetAddress = Account::getAddress($_config['faucet_public_key']);
	$options['salt']=substr($faucetAddress, 0, 16);
    $ipArgon = password_hash($ip, HASHING_ALGO, $options);


	$sql="select count(*) as cnt from mempool t where t.message =:msg";
	$row = $db->row($sql, [":msg"=> $ipArgon]);
	$cnt = $row['cnt'];
	if($cnt > 0) {
		_log("Faucet: attempt to use faucet for address $address from IP $ip. tx in mempool", 3);
		$_SESSION['msg']=[['icon'=>'error', 'text'=>'Not allowed use of faucet from same IP address for 60 blocks']];
		header("location: /apps/faucet/index.php");
		exit;
	}

    if(!$_config['testnet']) {
	    $height = Block::getHeight();
	    $check_height = $height - 60;
	    $sql = "select count(*) as cnt from transactions t where t.message =:msg and t.height > :height";
	    $row = $db->row($sql, [":msg" => $ipArgon, ":height" => $check_height]);
	    $cnt = $row['cnt'];

	    if ($cnt > 0) {
		    _log("Faucet: attempt to use faucet for address $address from IP $ip. tx in blockchain", 3);
		    $_SESSION['msg'] = [['icon' => 'error', 'text' => 'Not allowed use of faucet from same IP address for 60 blocks']];
		    header("location: /apps/faucet/index.php");
		    exit;
	    }
    }

    $val = $_config['testnet'] ? 50 : 0.01;
	$fee = 0;
	$msg = $ipArgon;
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
                    <?php if (!$_config['testnet']) { ?>
                        <div class="mb-1">
                            <label>Enter text from image</label>
                            <div class="row">
                                <div class="col-4">
                                    <img src="captcha.php" id="captcha_img"/>
                                </div>
                                <div class="col-1">
                                    <button type="button" class="btn btn-soft-dark waves-effect waves-light"
                                        onclick="refreshCaptcha()" title="Refresh image" data-bs-toggle="tooltip">
                                        <i class="fas fa-redo font-size-16 align-middle"></i>
                                    </button>
                                </div>
                                <div class="col-4">
                                    <input type="text" id="captcha" name="captcha" class="form-control" value=""/>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
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

<script type="text/javascript">
    function refreshCaptcha() {
        document.getElementById('captcha_img').src = 'captcha.php?t=' + Date.now();
    }
</script>

<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>

