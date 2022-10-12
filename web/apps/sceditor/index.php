<?php
require_once dirname(__DIR__)."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "SC Editor");

ini_set("phar.readonly", 0);

session_start();

$virtual = false;

//$_SESSION['sc_address']='LWetKthPC5WDGSXx7piRCCZKqGh8CeKoZ6';
$_SESSION['editor']=file_get_contents('/home/marko/web/phpcoin/node/dev/smart_contracts/SimpleContract.php');

if(isset($_POST['deploy'])) {
    $sc_address = $_POST['sc_address'];
    if(empty($sc_address)) {
	    $_SESSION['msg']=[['icon'=>'error', 'text'=>'Contract address is required']];
        header("location: /apps/sceditor");
        exit;
    }
    $smartContract = SmartContract::getById($sc_address);
    if($smartContract) {
	    $_SESSION['msg']=[['icon'=>'error', 'text'=>'Smart contract with address already exists']];
	    header("location: /apps/sceditor");
	    exit;
    }

    $public_key = Account::publicKey($sc_address);
    if(!$public_key && !$virtual) {
	    $_SESSION['msg']=[['icon'=>'error', 'text'=>'Smart contract address is not verified']];
	    header("location: /apps/sceditor");
	    exit;
    }

    $_SESSION['sc_address']=$sc_address;
    $signature = $_POST['signature'];
    $msg = $_POST['msg'];
	$date = $_POST['date'];
	$compiled_code = $_POST['compiled_code'];

	$transaction = new Transaction($public_key,$sc_address,0,TX_TYPE_SC_CREATE,$date, $msg, TX_SC_CREATE_FEE);
	$transaction->signature = $signature;
	$transaction->data = $compiled_code;

    if($virtual) {
	    $hash = $transaction->hash();
	    $_SESSION['transactions'][]=$transaction;
	    $_SESSION['balance']=$_SESSION['balance'] - TX_SC_CREATE_FEE;
	    $_SESSION['contract']=[
            "address"=>$sc_address,
            "height"=>1,
            "code"=>$compiled_code,
            "signature"=>$signature
        ];

	    SmartContractEngine::$virtual = true;
	    SmartContractEngine::$smartContract = $_SESSION['contract'];
	    $res = SmartContractEngine::deploy($transaction, 0, $err);

    } else {
        $transaction = new Transaction($public_key,$sc_address,0,TX_TYPE_SC_CREATE,$date, $msg, TX_SC_CREATE_FEE);
        $transaction->signature = $signature;
        $transaction->data = $compiled_code;
        $hash = $transaction->addToMemPool($error);
        $_SESSION['deploy_tx']=$hash;
        if($hash === false) {
            $_SESSION['msg']=[['icon'=>'error', 'text'=>'Transaction can not be sent: '.$error]];
            header("location: /apps/sceditor");
            exit;
        } else {
            $_SESSION['msg']=[['icon'=>'success', 'text'=>'Transaction sent! Id of transaction: '.$hash]];
            header("location: /apps/sceditor");
            exit;
        }
    }

}

if(isset($_POST['compile'])) {
    $editor = $_POST['editor'];
	$_SESSION['editor']=$editor;
	$sc_address = $_POST['sc_address'];
    $file = ROOT . "/tmp/sc/$sc_address.php";
	$phar_file = ROOT . "/tmp/sc/$sc_address.phar";
    file_put_contents($file, $editor);
    $res = SmartContract::compile($file, $phar_file, $err);
    if(!$res) {
	    $_SESSION['msg']=[['icon'=>'error', 'text'=>'Error compiling smart contract: '.$err]];
	    header("location: /apps/sceditor");
	    exit;
    }
	$_SESSION['contract']['code']=base64_encode(file_get_contents($phar_file));
	$compiled_code=$_SESSION['contract']['code'];
	$res = SmartContractEngine::verifyCode($compiled_code, $error);
	if(!$res) {
		$_SESSION['msg']=[['icon'=>'error', 'text'=>'Error verify smart contract']];
		header("location: /apps/sceditor");
		exit;
	}
	header("location: /apps/sceditor");
	exit;
}

if(isset($_POST['save'])) {
    $_SESSION['account'] = $_POST['account'];
    $_SESSION['sc_address'] = $_POST['sc_address'];
	header("location: /apps/sceditor");
	exit;
}

if(isset($_POST['get_source'])) {
	$sc_address = $_POST['sc_address'];
    if($virtual) {
	    $smartContract = $_SESSION['contract'];
    } else {
	    $smartContract = SmartContract::getById($sc_address);
    }
	$code = $smartContract['code'];
    $phar_file = ROOT . "/tmp/$sc_address.phar";
    file_put_contents($phar_file, base64_decode($code));
	header("Content-Description: File Transfer");
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"". basename($phar_file) ."\"");

	readfile ($phar_file);
	exit();
}

if(isset($_POST['exec_method'])) {
    $account = $_POST['account'];
	$sc_address = $_POST['sc_address'];
	$compiled_code = $_POST['compiled_code'];
    $call_method = array_keys($_POST['exec_method'])[0];
	$public_key = Account::publicKey($account);
	$date = $_POST['date'];
	$msg = $_POST['msg'];
	$signature = $_POST['signature'];
	$transaction = new Transaction($public_key,$sc_address,0,TX_TYPE_SC_EXEC,$date, $msg, TX_SC_EXEC_FEE);
	$transaction->signature = $signature;

    if($virtual) {
	    $hash = $transaction->hash();
	    $_SESSION['transactions'][]=$transaction;
	    $_SESSION['balance']=$_SESSION['balance'] - TX_SC_EXEC_FEE;
	    $_SESSION['contract']=[
		    "address"=>$sc_address,
		    "height"=>1,
		    "code"=>$compiled_code,
		    "signature"=>$signature
	    ];

	    SmartContractEngine::$virtual = true;
	    SmartContractEngine::$smartContract = $_SESSION['contract'];
        $params = [];
        if(!empty($msg)) {
	        $msg = base64_decode($msg);
	        $msg = json_decode($msg,true);
            $params = $msg['params'];
        }
	    $res = SmartContractEngine::exec($transaction, $call_method, 0, $params, $err);
        if(!$res) {
	        $_SESSION['msg']=[['icon'=>'error', 'text'=>'Method can not be executed: '.$err]];
	        header("location: /apps/sceditor");
	        exit;
        }
    } else {
	    $hash = $transaction->addToMemPool($error);
	    if($hash === false) {
		    $_SESSION['msg']=[['icon'=>'error', 'text'=>'Transaction can not be sent: '.$error]];
		    header("location: /apps/sceditor");
		    exit;
	    } else {
		    $_SESSION['msg']=[['icon'=>'success', 'text'=>'Transaction sent! Id of transaction: '.$hash]];
		    header("location: /apps/sceditor");
		    exit;
	    }
    }



}

if(isset($_POST['call_method'])) {
    $account = $_POST['account'];
	$sc_address = $_POST['sc_address'];
	$compiled_code = $_POST['compiled_code'];
    $call_method = array_keys($_POST['call_method'])[0];
	$msg = $_POST['msg'];

    if($virtual) {
	    $_SESSION['balance']=$_SESSION['balance'] - TX_SC_EXEC_FEE;
	    $_SESSION['contract']=[
		    "address"=>$sc_address,
		    "height"=>1,
		    "code"=>$compiled_code,
		    "signature"=>$signature
	    ];

	    SmartContractEngine::$virtual = true;
	    SmartContractEngine::$smartContract = $_SESSION['contract'];
	    $params = [];
        if(isset($_POST['params'][$call_method])) {
	        $params = $_POST['params'][$call_method];
	        $params = explode(",", $params);
            foreach($params as &$item) {
	            $item = trim($item);
            }
        }
    }
    $res = SmartContractEngine::call($sc_address, $call_method, $params, $err);
	if(!$res) {
		$_SESSION['msg']=[['icon'=>'error', 'text'=>'View can not be executed: '.$err]];
		header("location: /apps/sceditor");
		exit;
	}
    $_SESSION['call_method_val'][$call_method]=$res;

}

if(isset($_POST['get_property_val'])) {
	$property = array_keys($_POST['get_property_val'])[0];
	$sc_address = $_POST['sc_address'];
	$mapkey = null;
    if(isset($_POST['property_key'][$property])) {
	    $mapkey = $_POST['property_key'][$property];
	    $_SESSION['property_key'][$property]=$mapkey;
    }
    if($virtual) {
	    SmartContractEngine::$virtual = true;
	    SmartContractEngine::$smartContract = $_SESSION['contract'];
    }
    $val = SmartContractEngine::SCGet($sc_address, $property, $mapkey);
    $_SESSION['get_property_val'][$property]=$val;
	header("location: /apps/sceditor");
	exit;
}

if(isset($_POST['clear_property_val'])) {
	$property = array_keys($_POST['clear_property_val'])[0];
    unset($_SESSION['get_property_val'][$property]);
    unset($_SESSION['property_key'][$property]);
	header("location: /apps/sceditor");
	exit;
}

if(isset($_POST['clear_call_method_val'])) {
	$property = array_keys($_POST['clear_call_method_val'])[0];
    unset($_SESSION['call_method_val'][$property]);
	header("location: /apps/sceditor");
	exit;
}

if(isset($_POST['reset'])) {
    session_destroy();
	header("location: /apps/sceditor");
	exit;
}
if(isset($_POST['create_account'])) {
	$_SESSION['account'] = Account::generateAcccount();
	$_SESSION['balance'] = 100;
	header("location: /apps/sceditor");
	exit;
}
if(isset($_POST['create_contract'])) {
	$contract = Account::generateAcccount();
	$_SESSION['sc_address'] = $contract['address'];
	header("location: /apps/sceditor");
	exit;
}

if(isset($_SESSION['account'])) {
    $address = $_SESSION['account']['address'];
	$balance = $virtual ? $_SESSION['balance'] : Account::getBalance($address);
	$public_key = Account::publicKey($address);
}

if(isset($_SESSION['sc_address'])) {
	$sc_address = $_SESSION['sc_address'];
    if($virtual) {
	    $smartContract = $_SESSION['contract'];
    } else {
	    $smartContract = SmartContract::getById($sc_address);
    }
    $code = $smartContract['code'];
	$compiled_code = $code;
}


if($virtual) {
	$private_key = $_SESSION['account']['private_key'];
    SmartContractEngine::$virtual = true;
    SmartContractEngine::$smartContract = $_SESSION['contract'];
} else {
//    $private_key = "Lzhp9LopCJ3WztxH3vKJfCMBraMUCKVX8AgPEt5ZURHYJw7bDVr9ko8pcMoDwhxDAK2XvFXd6o2xYiDexepWvsnNvmv5S6FXwELBWASfHxnrZCNiCNhngwQYdm6Bbse9QEA75cpefNwDPdooB55cY2YgciEXwjp2z";
}

if($smartContract) {
    $interface = SmartContractEngine::getInterface($sc_address);
	$interface = json_decode($interface, true);
	$compiled_code = $smartContract['code'];
}


?>
<?php
require_once __DIR__. '/../common/include/top.php';
?>

<ol class="breadcrumb m-0 ps-0 h4">
    <li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
    <li class="breadcrumb-item"><a href="/apps/sceditor">Smart Contract Editor</a></li>
</ol>

<hr/>

<form class="container-fluid row" method="post" action="" onsubmit="onSubmit(event)">
	<div class="col-3">
        Account:
        <br/>
        <input type="text" value="<?php echo $address ?>" name="account"/>
		<?php if ($virtual) { ?>
            <button type="submit" name="create_account">Create</button>
		<?php } ?>
        <br/>
        Private key:
        <input type="password" value="<?php echo $private_key ?>" name="private_key"/>
        <br/>
        Balance: <?php echo num($balance) ?>
        <br/>
        <?php if ((!$smartContract && !empty($compiled_code)) || $virtual) { ?>
            <button type="submit" name="deploy">Deploy</button>
        <?php } ?>
        <?php if ($smartContract) { ?>
            <div>
                <?php //print_r($interface); ?>
                <h4>Methods</h4>
                <?php foreach ($interface['methods'] as $method) { ?>
                    <div>
                        <button type="submit" data-call="<?php echo $method['name'] ?>" name="exec_method[<?php echo $method['name'] ?>]"><?php echo $method['name'] ?></button>
                        <?php if (count($method['params']) > 0) { ?>
                            <input type="text" value="" name="params[<?php echo $method['name'] ?>]" placeholder="<?php echo implode(",", $method['params']) ?>"/>
                        <?php } ?>
                    </div>
                <?php } ?>
                <h4>Views</h4>
                <?php foreach ($interface['views'] as $method) {
	                $name = $method['name'];
                    ?>
                    <div>
                        <button type="submit" name="call_method[<?php echo $name ?>]"><?php echo $name ?></button>
                        <?php if (count($method['params']) > 0) { ?>
                            <input type="text" value="" name="params[<?php echo $name ?>]" placeholder="<?php echo implode(",", $method['params']) ?>"/>
                        <?php } ?>
	                    <?php echo $_SESSION['call_method_val'][$name] ?>
	                    <?php if (isset($_SESSION['call_method_val'][$name])) { ?>
                            <button type="submit" name="clear_call_method_val[<?php echo $name ?>]">x</button>
	                    <?php } ?>
                    </div>
                <?php } ?>
                <h4>Properties</h4>
                <?php foreach ($interface['properties'] as $property) {
                    $name = $property['name'];
                    $type = $property['type'];
                    ?>
                    <div>
                        <button type="submit" name="get_property_val[<?php echo $name ?>]"><?php echo $name ?></button>
                        <?php if($type == "map") { ?>
                            <input type="text" name="property_key[<?php echo $name ?>]" value="<?php echo $_SESSION['property_key'][$name] ?>" placeholder="Key"/>
                        <?php } ?>
                        <?php echo $_SESSION['get_property_val'][$name] ?>
                        <?php if (isset($_SESSION['get_property_val'][$name])) { ?>
                            <button type="submit" name="clear_property_val[<?php echo $name ?>]">x</button>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
	<div class="col-9">
		<div class="container-fluid row h-100 flex-column">
            <div>
                Contract
                <br/>
                <input type="text" value="<?php echo $sc_address ?>"
                       name="sc_address" <?php if ($smartContract) { ?>readonly="readonly"<?php } ?> size="50">
	            <?php if ($virtual && !$sc_address) { ?>
                    <button type="submit" name="create_contract">Create</button>
	            <?php } ?>
            </div>
            <?php if (!$smartContract || $virtual) { ?>
                <div>
                    Editor
                    <br/>
                    <textarea name="editor" rows="10" cols="100"><?php echo $_SESSION['editor'] ?></textarea>
                    <button type="submit" name="compile">Compile</button>
                </div>
            <?php } ?>
            <div>
                Compiled code:
                <br/>
                <textarea name="compiled_code" rows="10" cols="100" readonly="readonly"><?php echo $compiled_code ?></textarea>
                <?php if(!empty($compiled_code)) { ?>
                    <button type="submit" name="get_source">Source</button>
                    <?php if (isset($_SESSION['verify_error'])) {
                        echo $_SESSION['verify_error'];
                    } ?>
                <?php } ?>
            </div>
			<div>
                Transactions
                <table class="table table-striped table-condensed">
                    <thead>
                        <tr>
                            <th>Height</th>
                            <th>Source</th>
                            <th>Destination</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Fee</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($_SESSION['transactions'] as $ix => $tx) { ?>
                            <tr>
                                <td><?php echo $ix ?></td>
                                <td><?php echo $tx->src ?></td>
                                <td><?php echo $tx->dst ?></td>
                                <td><?php echo $tx->type ?></td>
                                <td><?php echo $tx->val ?></td>
                                <td><?php echo $tx->fee ?></td>
                                <td><?php echo base64_decode($tx->msg) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
		</div>
	</div>
    <div class="col-3">
        <button name="save" type="submit">Save</button>
        <button name="reset" type="submit">Reset</button>
    </div>
    <input type="hidden" name="signature"/>
    <input type="hidden" name="msg"/>
    <input type="hidden" name="date"/>
</form>

<script src="/apps/common/js/web-miner.js" type="text/javascript"></script>
<script type="text/javascript">

    function onSubmit(event) {
        if ($(event.submitter).attr("name") === "deploy") {
            let compiled_code = $("form [name=compiled_code]").val().trim()
            let msg = sign(compiled_code, privateKey)
            signTx(<?php echo TX_SC_CREATE_FEE ?>, msg, <?php echo TX_TYPE_SC_CREATE ?>);
        }
        if($(event.submitter).data("call")) {
            let method = $(event.submitter).data("call")
            let params = []
            if($("form [name='params["+method+"]']").length === 1) {
                params =  $("form [name='params["+method+"]']").val().trim()
                params = params.split(",")
                params = params.map(function (item) { return item.trim() })
                console.log(params)
            }
            let msg = JSON.stringify({method, params})
            msg = btoa(msg)
            signTx(<?php echo TX_SC_EXEC_FEE ?>, msg, <?php echo TX_TYPE_SC_EXEC ?>);
        }
    }

    function signTx(fee, msg, type) {
        let privateKey = $("form [name=private_key]").val().trim()
        let amount = Number(0).toFixed(8)
        fee = Number(fee).toFixed(8)
        let dst = $("form [name=sc_address]").val().trim()
        let date = Math.round(new Date().getTime()/1000)
        let data = amount + '-' + fee + '-' + dst + '-' + msg + '-' + type + '-'
            + '<?php echo $public_key ?>' + '-' + date
        let sig = sign(data, privateKey)
        console.log(data, sig)
        $("form [name=signature]").val(sig)
        $("form [name=msg]").val(msg)
        $("form [name=date]").val(date)
    }
</script>



<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>

