<?php
require_once dirname(__DIR__)."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Wallet");
session_start();


function proccessLogin($public_key, $login_key, $login_code) {
	if (Account::valid($public_key)) {
		$address = $public_key;
		$public_key = Account::publicKey($address);
		if (empty($public_key)) {
			$_SESSION['msg'] = [['icon' => 'warning', 'text' => 'Invalid address or public key']];
			header("location: /apps/wallet/login.php");
			exit;
		}
	} else {
		$address = Account::getAddress($public_key);
		if (!Account::valid($address)) {
			$_SESSION['msg'] = [['icon' => 'warning', 'text' => 'Invalid address or public key']];
			header("location: /apps/wallet/login.php");
			exit;
		}
	}
	$verify = Account::checkSignature($login_code, $login_key, $public_key);
	if (!$verify) {
		$_SESSION['msg'] = [['icon' => 'warning', 'text' => 'Invalid login data']];
		header("location: /apps/wallet/login.php");
		exit;
	} else {
		$_SESSION['public_key'] = $public_key;
		header("location: /apps/wallet/index.php");
		exit;
	}
}

if(isset($_POST['login'])) {

        if($_SERVER['SERVER_NAME'] !== APPS_WALLET_SERVER_NAME) {
	        $_SESSION['msg'] = [['icon' => 'warning', 'text' => 'Invalid server access']];
	        header("location: /apps/wallet/login.php");
	        exit;
        }
	    if (!isset($_POST['public_key']) || !isset($_POST['signature']) || !isset($_POST['nonce'] ) || !isset($_POST['wallet_signature'] )) {
		    $_SESSION['msg'] = [['icon' => 'warning', 'text' => 'Not filled required data']];
		    header("location: /apps/wallet/login.php");
		    exit;
	    }
	    $nonce = $_POST['nonce'];
	    $signature = $_POST['signature'];
	    $public_key = $_POST['public_key'];
	    $wallet_signature = $_POST['wallet_signature'];
	    if (Account::valid($public_key)) {
		    $address = $public_key;
		    $public_key = Account::publicKey($address);
		    if (empty($public_key)) {
			    $_SESSION['msg'] = [['icon' => 'warning', 'text' => 'Invalid address or public key']];
			    header("location: /apps/wallet/login.php");
			    exit;
		    }
	    } else {
		    $address = Account::getAddress($public_key);
		    if (!Account::valid($address)) {
			    $_SESSION['msg'] = [['icon' => 'warning', 'text' => 'Invalid address or public key']];
			    header("location: /apps/wallet/login.php");
			    exit;
		    }
	    }
	    $verify = Account::checkSignature($nonce, $signature, $public_key);
	    $wallet_verify = Account::checkSignature($nonce, $wallet_signature, APPS_WALLET_SERVER_PUBLIC_KEY);
	    if (!$verify || !$wallet_verify) {
		    $_SESSION['msg'] = [['icon' => 'warning', 'text' => 'Invalid login data']];
		    header("location: /apps/wallet/login.php");
		    exit;
	    } else {
		    $_SESSION['public_key'] = $public_key;
		    header("location: /apps/wallet/index.php");
		    exit;
	    }
}

if(isset($_GET['action']) && $_GET['action']=="login-link") {
    $login_code = $_GET['login_code'];
    $public_key = $_GET['public_key'];
    $login_key = $_GET['login_key'];

	if($_SERVER['SERVER_NAME'] !== APPS_WALLET_SERVER_NAME) {
		header("location: https://".APPS_WALLET_SERVER_NAME."/".$_SERVER['REQUEST_URI']);
		exit;
	}

    if(empty($login_code) || empty($public_key) || empty($login_key)) {
	    $_SESSION['msg']=[['icon'=>'warning', 'text'=>'Invalid data received']];
	    header("location: /apps/wallet/login.php");
	    exit;
    }

	proccessLogin($public_key, $login_key, $login_code);

}

if(!Nodeutil::walletEnabled()) {
	header("location: /apps/explorer");
	exit;
}

if(isset($_GET['action']) && $_GET['action']=="signup") {
	$accountData = Account::generateAcccount();
}

if(isset($_GET['public_key'])) {
    $public_key = $_GET['public_key'];
}

$loginNonce = hash("sha256", uniqid("login"));
$walletSignature = ec_sign($loginNonce, $_config['wallet_private_key']);
?>
<?php
require_once __DIR__. '/../common/include/top.php';
?>

<div class="auth-page">
    <div class="container-fluid p-0">
        <div class="row g-0">
            <div class="col-xxl-3 col-lg-4 col-md-5" id="login-form-container">
                <div class="auth-full-page-content d-flex p-sm-5 p-4" >
                    <div class="w-100">
                        <div class="d-flex flex-column h-100">
                            <div class="auth-content my-auto">
                                <div class="text-center">
                                    <h5 class="mb-5">Sign in to access your coins !</h5>
                                </div>
                                <form class="custom-form mt-4 pt-2" method="post" action="/apps/wallet/login.php" autocomplete="off">
                                    <div class="mb-3">
                                        <label for="public_key" class="form-label">Public key / Address</label>
                                        <input type="text" class="form-control" id="public_key" name="public_key"
                                               placeholder="Enter public key or address" value="<?php echo $public_key ?>" required="required"
                                        />
                                        <div class="help-block text-muted text-info">
                                            In order to login with address you must have recorded transaction on blockchain
                                        </div>
                                    </div>
                                        <div class="mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="flex-grow-1">
                                                    <label class="form-label" for="private_key">Private key</label>
                                                </div>
                                            </div>

                                            <div class="input-group auth-pass-inputgroup">
                                                <input type="password" class="form-control" placeholder="Enter private key" aria-label="Password"
                                                       aria-describedby="password-addon" id="private_key" name="private_key" required="required"
                                                       />
                                                <button class="btn btn-light shadow-none ms-0" type="button" id="password-addon"><i class="mdi mdi-eye-outline"></i></button>
                                            </div>
                                        </div>
                                        <div class="row mb-4">
                                            <div class="col">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="rememberPrivateKey">
                                                    <label class="form-check-label" for="rememberPrivateKey">
                                                        Remember private key
                                                    </label>
                                                </div>
                                                <div class="help-block text-muted text-info">
                                                    Private key will be stored only locally in browser
                                                </div>
                                            </div>
                                        </div>
                                    <div class="mb-3">
                                        <button class="btn btn-primary w-100 waves-effect waves-light" type="button" onclick="processLogin()">Log In</button>
                                    </div>

                                    <input type="hidden" id="signature" name="signature" value="" required>
                                    <input type="hidden" id="login" name="login" value="login" required>
                                    <input type="hidden" id="nonce" name="nonce"  value="<?php echo $loginNonce ?>" required>
                                    <input type="hidden" id="wallet_signature" name="wallet_signature"  value="<?php echo $walletSignature ?>" required>

                                </form>

                                <div class="mt-5 text-center h5">
                                    <p class="text-muted mb-0">Don't have an address ?
                                        <br/>
                                        <a href="/apps/wallet/login.php?action=signup" class="text-primary fw-semibold"> Create new one now ! </a> </p>
                                </div>


                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-9 col-lg-8 col-md-7">
                <div class="auth-bg pt-md-5 p-4 d-flex">
                    <div class="bg-overlay bg-primary"></div>
                    <ul class="bg-bubbles">
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                    </ul>
                    <div class="row justify-content-center align-items-center w-100">
                        <div class="col-xl-8">
                            <div class="p-0 p-sm-4 px-xl-0 text-white-50">

                                <p class="font-weight-bold">
                                    Important ro know:
                                </p>
                                <ul>
                                    <li>Your private key is the “master key” to your wallet and funds.</li>
                                    <li>Save in a password manager</li>
                                    <li>Store in a bank vault.</li>
                                    <li>Store in a safe-deposit box.</li>
                                    <li>Write down and store in multiple secret places.</li>
                                    <li>Never, ever share your private key, even with us!</li>
                                    <li>If someone asks for your private key, they are most likely trying to scam you.</li>
                                    <li>Private key will never leave your browser or submitted via form.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php if (!empty($accountData)) { ?>
    <button id="offcanvas-trigger" class="btn btn-primary d-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvas" aria-controls="offcanvas">Toggle right offcanvas</button>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvas" aria-labelledby="offcanvasLabel"
         data-bs-backdrop="false" style="margin-top:55px;">
        <div class="offcanvas-header">
            <h5 id="offcanvasLabel">Here are your generated credentials</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <dl class="row">
                <dt class="">Address:</dt>
                <dd class=""><?php echo $accountData['address'] ?></dd>
                <dt class="">Public Key:</dt>
                <dd class="" style="word-break: break-all"><?php echo $accountData['public_key'] ?></dd>
                <dt class="text-danger">Private Key:</dt>
                <dd class="text-danger" style="word-break: break-all"><?php echo $accountData['private_key'] ?></dd>
            </dl>
            <div class="alert alert-warning">
                <ul>
                    <li>Please write down your private key and store it securely!</li>
                    <li>Never give it or display it to anyone</li>
                    <li>Use it only for login and to restore your account</li>
                </ul>
            </div>
            <a class="btn btn-primary" href="/apps/wallet/login.php"
                onclick="storeAccount();">
                Login with this account
            </a>
        </div>
    </div>
<?php } ?>

<style>
    body {
        min-height: auto !important;
    }
    .page-content {
        padding: 0 !important;
    }
    .container-fluid {
        max-width: 100% !important;
        width: 100% !important;
        padding: 0 !important;
    }
    footer {
        display: none;
    }
    .auth-page {

    }
    .auth-full-page-content {
        padding-top: 100px !important;
    }
    .auth-bg {
        background-image: url(/apps/common/img/bg.jpg);
    }
    .dev {
        position: fixed;
        bottom: 0;
        right: 0;
        background-color: #fff;
        padding: 5px;
    }
</style>






<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
<script src="/apps/miner/js/web-miner.js" type="text/javascript"></script>
<script type="text/javascript">

    function processLogin() {
            let publicKey = $("#public_key").val().trim()
            let privateKey = $("#private_key").val().trim()
            if(publicKey.length === 0 || privateKey.length === 0) {
                Swal.fire(
                    {
                        title: 'Please fill login data!',
                        icon: 'error'
                    }
                )
                return;
            }
            try {
                let sig = sign('<?php echo $loginNonce ?>', privateKey)
                $("#signature").val(sig)
                $('#private_key').val('');
                $("form").submit()
            } catch (e) {
                console.error(e)
                Swal.fire(
                    {
                        title: 'Can not sign login form!',
                        text: 'Please check you private key',
                        icon: 'error'
                    }
                )
                return;
            }

            if($("#rememberPrivateKey").is(":checked")) {
                localStorage.setItem("privateKey", privateKey)
            } else {
                localStorage.removeItem("privateKey")
            }

    }

    function storeAccount() {
        localStorage.setItem('privateKey', '<?php echo $accountData['private_key'] ?>');
        localStorage.setItem('publicKey', '<?php echo $accountData['public_key'] ?>');
    }

    $(function(){

        $("#password-addon").on('click', function () {
            if ($(this).siblings('input').length > 0) {
                $(this).siblings('input').attr('type') == "password" ? $(this).siblings('input').attr('type', 'input') : $(this).siblings('input').attr('type', 'password');
            }
        })

        if(localStorage.getItem('privateKey')) {
            $("#private_key").val(localStorage.getItem('privateKey'))
            $("#rememberPrivateKey").attr("checked", "checked")
        }

        if(localStorage.getItem('publicKey')) {
            $("#public_key").val(localStorage.getItem('publicKey'))
        }

	    <?php if (!empty($accountData)) { ?>
            $("#offcanvas").width($("#login-form-container").width())
            $("#offcanvas-trigger").click();
        <?php } ?>

    })

    window.addEventListener("beforeunload", function(event) {
        $('input[type=password]').val('');
    });
</script>
