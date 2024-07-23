<?php
require_once dirname(dirname(__DIR__))."/apps.inc.php";
define("PAGE", true);
define("APP_NAME", "Explorer");
require_once ROOT. '/web/apps/explorer/include/functions.php';

if(!FEATURE_SMART_CONTRACTS) {
    header("location: /apps/explorer");
    exit;
}

if(isset($_GET['action'])) {
    $action = $_GET['action'];
    $data = json_decode(file_get_contents("php://input"), true);
    if($action == "compileTokenSmartContract") {
        $compile_dir = ROOT."/tmp/compile";
        @mkdir($compile_dir);
        chmod($compile_dir, 0777);
        $template_file = ROOT . "/include/templates/tokens/erc_20_token.php";
        $compile_id = uniqid();
        $token_file = $compile_dir."/token_$compile_id.php";
        copy($template_file, $token_file);
        $phar_file = $compile_dir . "/token_$compile_id.phar";
        $res = SmartContract::compile($token_file, $phar_file, $err);
        if($res && file_exists($phar_file)) {
            api_echo($compile_id);
        } else {
            api_err($err);
        }
    }

    if($action == "getTokenSignatureBase") {
        $compileId = $data["compileId"];
        if(empty($compileId)) {
            api_err("Compiled file not found");
        }
        $compile_dir = ROOT."/tmp/compile";
        $phar_file = $compile_dir . "/token_$compileId.phar";
        if(!file_exists($phar_file)) {
            api_err("Compiled file not found");
        }
        $code=base64_encode(file_get_contents($phar_file));
        $interface = SmartContractEngine::verifyCode($code, $error);
        if(!$interface) {
            api_err("Error verifying token code");
        }
        $name = $data['name'];
        if(empty($name)) {
            api_err("Token name is required");
        }
        if(strlen($name) > 32) {
            api_err("Token name can not be longer than 50 characters");
        }
        if(!preg_match('/^[\w\s\-]{1,32}$/', $name)) {
            api_err("Invalid token name");
        }
        $description=$data['description'];
        if(strlen($description) > 255) {
            api_err("Token description can not be longer than 255 characters");
        }
        $imageB64Data = $data['image'];
        if(!empty($imageB64Data)) {
            if (strpos($imageB64Data, 'data:image/') === 0) {
                $image = substr($imageB64Data, strpos($imageB64Data, ',') + 1);
            }
            $imageData = base64_decode($image);
            if ($imageData === false) {
                api_err("Error reading token image");
            }
            $image = @imagecreatefromstring($imageData);
            if (!$image) {
                api_err("Error reading token image");
            }
            $width = imagesx($image);
            $height = imagesy($image);
            @imagedestroy($image);
            if($width != 128 && $height != 128) {
                api_err("Invalid token image size");
            }
        }
        $symbol=$data['symbol'];
        if(empty($symbol)) {
            api_err("Token symbol is required");
        }
        if(!preg_match('/^[A-Z0-9-_]{3,10}$/', $symbol)) {
            api_err("Invalid token symbol");
        }
        $decimals = $data['decimals'];
        if(strlen($decimals)==0) {
            api_err("Token decimals is required");
        }
        $decimals = intval($decimals);
        if($decimals < 0 && $decimals > 18) {
            api_err("Token decimals must be between 0 and 18");
        }
        $initialSupply = $data['initialSupply'];
        if(strlen($initialSupply)==0) {
            api_err("Initial supply is required");
        }
        $deploy_params = [$name, $symbol, $decimals, $initialSupply];
        $metadata["name"]=$name;
        $metadata["description"]=$description;
        $metadata['class']="ERC-20";
        $metadata['image']=$imageB64Data;
        $metadata['symbol']=$symbol;
        $metadata['decimals']=$decimals;
        $metadata['initialSupply']=$initialSupply;
        $scData = [
            "code"=>$code,
            "amount"=>num(0),
            "params"=>$deploy_params,
            "interface"=>$interface,
            "metadata"=>$metadata
        ];
        _log(json_encode($scData));
        $signatureBase = base64_encode(json_encode($scData));
        api_echo([
            "signatureBase"=>$signatureBase,
            "code"=>$code,
            "params"=>$deploy_params,
            "metadata"=>$metadata,
        ]);
    }
}


require_once __DIR__. '/../../common/include/top.php';

$loggedIn = false;
if(isset($_SESSION['account'])) {
    $balance = Account::getBalance($_SESSION['account']['address']);
    $loggedIn = true;
    $address = $_SESSION['account']['address'];
}

?>

<div class="container">

    <div class="card" id="app">
        <div class="card-header">
            <h4 class="card-title">Create token</h4>
            <div class="card-title-desc">Create your own ERC-20 token</div>
        </div>
        <div class="card-body">
            <?php if(!$loggedIn) { ?>
                <div class="alert alert-info d-flex align-items-center">
                    <div class="fw-bold">You must be logged in with account to create new token</div>
                    <a class="btn btn-info ms-auto" href="/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/wallet?redirect=%2Fapps%2Fexplorer%2Ftokens%2Fcreate.php">Login</a>
                </div>
            <?php } else {?>
                <div class="mb-3">
                    <label for="token-address" class="form-label">Smart Contract address:</label>
                    <input class="form-control" type="text" v-model="newToken.address" id="token-address"/>
                    <div class="form-text">
                        Token address is SmartContract address
                    </div>
                </div>

                <div class="mb-3">
                    <label for="token-name" class="form-label">Name:</label>
                    <input class="form-control" type="text" v-model="newToken.name" id="token-name"/>
                    <div class="form-text">
                        Token name must be less than 50 characters without special characters
                    </div>
                </div>

                <div class="mb-3">
                    <label for="token-description" class="form-label">Description (optional):</label>
                    <textarea class="form-control" type="text" v-model="newToken.description" id="token-description"></textarea>
                    <div class="form-text">
                        Token description must be less than 255 characters
                    </div>
                </div>

                <div class="mb-3">
                    <label for="token-symbol" class="form-label">Symbol:</label>
                    <input class="form-control" type="text" v-model="newToken.symbol" id="token-symbol"/>
                    <div class="form-text">
                        Token symbol must be less than 10 characters without special characters
                    </div>
                </div>

                <div class="mb-3">
                    <label for="token-decimals" class="form-label">Decimals:</label>
                    <input class="form-control" type="number" v-model="newToken.decimals" id="token-decimals"/>
                    <div class="form-text">
                        Number of digits in which amounts will be presented
                    </div>
                </div>

                <div class="mb-3">
                    <label for="token-init-supply" class="form-label">Initial supply:</label>
                    <input class="form-control" type="number" v-model="newToken.initialSupply" id="token-init-supply"/>
                    <div class="form-text">
                        Total number of tokens which will be on owner account after deploy
                    </div>
                </div>

                <div class="mb-3">
                    <label for="token-image" class="form-label">Image:</label>
                    <div class="row">
                        <div class="col-6">
                            <input type="file" @change="onFileChange"/>
                            <div class="form-text">
                                Image that will represent token. Must be size 128x128 px
                            </div>
                        </div>
                        <div class="col-6" v-if="newToken.image">
                            <div class="card">
                                <img :src="newToken.image" style="width: 128px; height: 128px;"/>
                            </div>
                            <button class="btn btn-danger" @click="removeImage">Remove</button>
                        </div>
                    </div>
                </div>

                <div class="alert alert-warning">
                    Creation of token will cost <strong>100 PHPCoin</strong>.
                    <br/>
                    Please check if you have enough funds on account
                </div>
            <?php } ?>
        </div>
        <?php if($loggedIn) { ?>
            <div class="card-footer">
                <button class="btn btn-success me-2" @click="createToken">Create</button>
                <a href="/apps/explorer/tokens/list.php" class="btn btn-secondary">Cancel</a>
            </div>
        <?php } ?>
    </div>

</div>

<script src="/apps/common/js/phpcoin-crypto.js" type="text/javascript"></script>
<script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<script src="/apps/explorer/tokens/tokens.js" type="text/javascript"></script>
<script type="text/javascript">

    const publicKey = '<?php echo $_SESSION['account']['public_key'] ?>';
    const chainId = '<?php echo CHAIN_ID ?>';

    const { createApp, ref } = Vue;
    createApp({
        data() {
            return {
                newToken: {
                    address: null,
                    name: null,
                    symbol: null,
                    description: null,
                    decimals: null,
                    initialSupply: null,
                    image: null,
                }
            };
        },
        methods: {
            onFileChange(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.newToken.image = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            },
            removeImage() {
                this.newToken.image = null
            },
            createToken() {
                confirmMsg("Confirm create token", "Are you sure to want to create new token?",()=>{
                    this.execCreateToken();
                })
            },
            execCreateToken() {
                axios.post('/apps/explorer/tokens/create.php?action=compileTokenSmartContract', {}).then(res=>{
                    if(res.data.status !== 'ok') {
                        Swal.fire(
                            {
                                title: 'Error compiling token',
                                text: 'Error from API server when compiling token: ' + res.data.data,
                                icon: 'error'
                            }
                        )
                        return;
                    }
                    let compileId = res.data.data;
                    if(!compileId) {
                        Swal.fire(
                            {
                                title: 'Error compiling token',
                                text: 'Error from API server when compiling token: Contract not compiled',
                                icon: 'error'
                            }
                        )
                        return;
                    }
                    axios.post('/apps/explorer/tokens/create.php?action=getTokenSignatureBase', {
                        compileId,
                        name: this.newToken.name,
                        description: this.newToken.description,
                        image: this.newToken.image,
                        symbol: this.newToken.symbol,
                        decimals: this.newToken.decimals,
                        initialSupply: this.newToken.initialSupply,
                    }).then(res=>{
                        if(res.data.status !== 'ok') {
                            Swal.fire(
                                {
                                    title: 'Error generating token signature',
                                    text: 'Error from API server when generating token signature: ' + res.data.data,
                                    icon: 'error'
                                }
                            )
                            return;
                        }
                        let signatureBase = res.data.data.signatureBase;
                        if(!signatureBase) {
                            Swal.fire(
                                {
                                    title: 'Error generating token signature',
                                    text: 'Error from API server when generating token signature',
                                    icon: 'error'
                                }
                            );
                            return;
                        }
                        enterPrivateKey((privateKey) => {
                            if (privateKey) {
                                let signature = sign(chainId+signatureBase, privateKey);
                                console.log(signatureBase,signature)
                                if(!signature) {
                                    Swal.fire(
                                        {
                                            title: 'Error signing token',
                                            text: 'Error signing token',
                                            icon: 'error'
                                        }
                                    );
                                    return;
                                }
                                let data ={
                                    public_key: publicKey,
                                    sc_address: this.newToken.address,
                                    amount: 0,
                                    sc_signature: signature,
                                    code: res.data.data.code,
                                    params: res.data.data.params,
                                    metadata: res.data.data.metadata
                                };
                                axios.post('/api.php?q=generateSmartContractDeployTx', data).then(res=>{
                                    if(res.data.status !== "ok") {
                                        Swal.fire(
                                            {
                                                title: 'Error generating deploy transaction',
                                                text: 'Error from API server when generating deploy transaction: ' + res.data.data,
                                                icon: 'error'
                                            }
                                        )
                                        return;
                                    }
                                    let tx = res.data.data.tx;
                                    let signature_base = res.data.data.signature_base;
                                    console.log(signature_base);
                                    enterPrivateKey(privateKey=>{
                                        sendTransaction(tx, signature_base, privateKey);
                                    })
                                }).catch(err=>{
                                    console.error(err);
                                    Swal.fire(
                                        {
                                            title: 'Error generating deploy transaction',
                                            text: 'Error contacting API server',
                                            icon: 'error'
                                        }
                                    )
                                });
                            }
                        })
                    }).catch(err=>{
                        console.error(err);
                        Swal.fire(
                            {
                                title: 'Error signing token',
                                text: 'Error contacting API server',
                                icon: 'error'
                            }
                        )
                    });

                }).catch(err=>{
                    console.error(err);
                    Swal.fire(
                        {
                            title: 'Error compiling token',
                            text: 'Error contacting API server',
                            icon: 'error'
                        }
                    )
                });
            },
        }
    }).mount("#app");
</script>

<?php
require_once __DIR__ . '/../../common/include/bottom.php';
?>

