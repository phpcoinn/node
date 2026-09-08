<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once ROOT . '/web/apps/explorer/include/functions.php';
define('PAGE', true);
define('APP_NAME', 'Explorer');

if (!FEATURE_SMART_CONTRACTS) {
    header('location: /apps/explorer');
    exit;
}

$loggedIn = isset($_SESSION['account']);
$error = null;
$deploy = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loggedIn) {
    $address = trim((string)($_POST['address'] ?? ''));
    if (!Account::valid($address)) {
        $error = 'Invalid smart-contract address';
    } elseif (SmartContract::getById($address)) {
        $error = 'That smart-contract address is already deployed';
    } elseif (empty($_FILES['phar']['tmp_name']) || $_FILES['phar']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a PHAR file';
    } elseif ($_FILES['phar']['size'] > 20 * 1024 * 1024) {
        $error = 'PHAR file is too large';
    } else {
        $codeBytes = file_get_contents($_FILES['phar']['tmp_name']);
        $code = base64_encode($codeBytes);
        $interface = SmartContractEngine::verifyCode($code, $verifyError, $address);
        if (!$interface) {
            $error = 'Contract validation failed: ' . ($verifyError ?: 'invalid contract');
        } else {
            $metadata = [
                'name' => trim((string)($_POST['name'] ?? '')),
                'description' => trim((string)($_POST['description'] ?? '')),
            ];
            $deployData = [
                'code' => $code,
                'amount' => num(0),
                'params' => [],
                'interface' => $interface,
                'metadata' => $metadata,
            ];
            $deploy = [
                'address' => $address,
                'code' => $code,
                'interface' => $interface,
                'metadata' => $metadata,
                'signatureBase' => base64_encode(json_encode($deployData)),
            ];
        }
    }
}

require_once __DIR__ . '/../common/include/top.php';
?>

<div class="container" id="deploy-app">
    <ol class="breadcrumb m-0 ps-0 h4">
        <li class="breadcrumb-item"><a href="/apps/explorer">Explorer</a></li>
        <li class="breadcrumb-item active">Deploy smart contract</li>
    </ol>

    <?php if (!$loggedIn) { ?>
        <div class="alert alert-warning">Log in to a wallet before deploying a contract.</div>
    <?php } else { ?>
        <?php if ($error) { ?><div class="alert alert-danger"><?php echo h($error) ?></div><?php } ?>
        <?php if (!$deploy) { ?>
            <form method="post" enctype="multipart/form-data" class="card">
                <div class="card-header"><h4 class="mb-0">Deploy smart contract</h4></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="address">Contract address</label>
                        <input class="form-control" id="address" name="address" required>
                        <div class="form-text">Compile the PHAR for this exact unused address.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="phar">Compiled PHAR</label>
                        <input class="form-control" id="phar" name="phar" type="file" accept=".phar" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="name">Name (optional)</label>
                        <input class="form-control" id="name" name="name" maxlength="64">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="description">Description (optional)</label>
                        <textarea class="form-control" id="description" name="description" maxlength="255"></textarea>
                    </div>
                </div>
                <div class="card-footer"><button class="btn btn-primary">Validate contract</button></div>
            </form>
        <?php } else { ?>
            <div class="alert alert-success">Contract validated for <code><?php echo h($deploy['address']) ?></code>. Review and sign the deployment transaction.</div>
            <dl class="row">
                <dt class="col-sm-3">Interface methods</dt>
                <dd class="col-sm-9"><?php echo count($deploy['interface']['methods'] ?? []) ?></dd>
                <dt class="col-sm-3">Deployment fee</dt>
                <dd class="col-sm-9"><?php echo h(Blockchain::getSmartContractCreateFee()) ?> PHP</dd>
            </dl>
            <button id="deploy-button" class="btn btn-success">Sign and deploy</button>
            <a href="/apps/explorer/smart_contract_deploy.php" class="btn btn-light">Cancel</a>
        <?php } ?>
    <?php } ?>
</div>

<?php if ($deploy) { ?>
<script src="/apps/common/js/phpcoin-crypto.browser.js"></script>
<script src="https://unpkg.com/sweetalert2@11"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<script src="/apps/explorer/tokens/tokens.js"></script>
<script>
const chainId = '<?php echo h(CHAIN_ID) ?>';
const publicKey = '<?php echo h($_SESSION['account']['public_key']) ?>';
const deployment = <?php echo json_encode($deploy, JSON_UNESCAPED_SLASHES) ?>;
document.getElementById('deploy-button').addEventListener('click', function () {
    enterPrivateKey(function (privateKey) {
        if (!privateKey) return;
        const scSignature = phpcoinCrypto.sign(chainId + deployment.signatureBase, privateKey);
        if (!scSignature) {
            Swal.fire('Error', 'Could not sign the contract payload', 'error');
            return;
        }
        axios.post('/api.php?q=generateSmartContractDeployTx', {
            public_key: publicKey,
            sc_address: deployment.address,
            amount: 0,
            sc_signature: scSignature,
            code: deployment.code,
            params: [],
            metadata: deployment.metadata
        }).then(function (response) {
            if (response.data.status !== 'ok') throw new Error(response.data.data);
            sendTransaction(response.data.data.tx, response.data.data.signature_base, privateKey);
        }).catch(function (error) {
            Swal.fire('Deployment failed', error.message || 'Could not create deployment transaction', 'error');
        });
    });
});
</script>
<?php } ?>

<?php require_once __DIR__ . '/../common/include/bottom.php'; ?>
