<?php

if (!defined("ADMIN_TAB")) {
    exit;
}

global $action, $db, $_config;

if($action == "clean") {
    Nodeutil::clean();
    header("location: ".APP_URL."/?view=utils");
    exit;
}

$main_node = $_config['initial_peer_list'][0];

if($action=="sync") {
    $cmd= "php " . ROOT . "/cli/peersync.php $main_node";
    Nodeutil::runSingleProcess($cmd);
    header("location: ".APP_URL."/?view=utils");
    exit;
}

if($action == "check_blocks") {
    $peer = $_POST['peer'];
    $invalid_block = Nodeutil::checkBlocksWithPeer($peer);
    $checkBlocksResponse = true;
}

if($action == "accounts-hash") {
    $accountsHash = Nodeutil::calculateAccountsHash();
}

if($action == 'clear_blocks') {
    $height = $_POST['height'];
    Nodeutil::deleteFromHeight($height);
    header("location: ".APP_URL."/?view=utils");
    exit;
}

if($action == "blocks-hash") {
    $height = $_POST['height'];
    $blocksHash = Nodeutil::calculateBlocksHash($height);
}

if($action == "clear_tmp") {
    Util::cleanTmpFolder();
    header("location: ".APP_URL."/?view=utils");
    exit;
}

?>


<div class="flex-row d-flex flex-wrap align-items-center mb-2">
    <a class="btn btn-danger me-2" href="<?php echo APP_URL ?>/?view=utils&action=clean" onclick="if(!confirm('Clean?')) return false">Clean</a>
    <div class="text-danger">Deletes all block and transactions in database</div>
</div>
<div class="flex-row d-flex flex-wrap align-items-center mb-2">
    <a class="btn btn-info me-2" href="<?php echo APP_URL ?>/?view=utils&action=sync" onclick="if(!confirm('Run sync?')) return false">Run sync</a>
    <div>Manualy sync from main node <?php echo $main_node ?></div>
</div>
<div class="flex-row d-flex flex-wrap align-items-center mb-2">
    <a class="btn btn-info me-2" href="<?php echo APP_URL ?>/?view=utils&action=clear_tmp" onclick="if(!confirm('Run sync?')) return false">Clear temp folder</a>
    <div>Clear tmp folder if syncing of node is stuck</div>
</div>

<div class="mt-4">
    <h3 class="font-size-16 mb-2"><i class="mdi mdi-arrow-right text-primary me-1"></i> Check blocks</h3>

    <form class="row gx-3 gy-2 align-items-center" method="post" action="">

        <input type="hidden" name="action" value="check_blocks"/>
        <div class="col-sm-2">
            <input type="text" class="form-control" id="peer" name="peer" placeholder="Peer" required="required">
        </div>
        <div class="col-sm-2">
            <button type="submit" class="btn btn-primary">Check</button>
        </div>
        <div class="col-auto">
            <?php if($checkBlocksResponse) { ?>
                <?php if(empty($invalid_block)) { ?>
                    <div class="alert alert-success mb-0">
                        No invalid block
                    </div>
                <?php } else { ?>
                    <div class="alert alert-danger mb-0">
                        Invalid block detected at height <?php echo $invalid_block ?>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </form>
</div>

<hr/>

<div class="mt-4">
    <h3 class="font-size-16 mb-2"><i class="mdi mdi-arrow-right text-primary me-1"></i> Clear blocks</h3>

    <form class="row gx-3 gy-2 align-items-center" method="post" action="">
        <input type="hidden" name="action" value="clear_blocks"/>
        <div class="col-sm-2">
            <input type="text" class="form-control" id="height" name="height" placeholder="From height" required="required">
        </div>
        <div class="col-sm-2">
            <button type="submit" class="btn btn-danger">Clear</button>
        </div>
    </form>
</div>

<hr/>

<div class="mt-4">
    <h3 class="font-size-16 mb-2"><i class="mdi mdi-arrow-right text-primary me-1"></i>Accounts hash</h3>
    <div class="row">
        <div class="col-sm-2">
            <a href="<?php echo APP_URL ?>/?view=utils&action=accounts-hash" class="btn btn-info">Calculate</a>
        </div>
        <div class="col-auto">
            <?php if($accountsHash) { ?>
                <div class="alert alert-success mb-0">
                    height: <?php echo $accountsHash['height'] ?><br/>
                    hash: <?php echo $accountsHash['hash'] ?>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<hr/>

<div class="mt-4">
    <h3 class="font-size-16 mb-2"><i class="mdi mdi-arrow-right text-primary me-1"></i>Blocks hash</h3>
    <form class="row gx-3 gy-2 align-items-center" method="post" action="">
        <input type="hidden" name="action" value="blocks-hash"/>
        <div class="col-sm-2">
            <input type="text" class="form-control" id="height" name="height" placeholder="Height">
        </div>
        <div class="col-sm-2">
            <button type="submit" class="btn btn-info">Calculate</button>
        </div>
        <div class="col-auto">
            <?php if($blocksHash) { ?>
                <div class="alert alert-success mb-0">
                    height: <?php echo $blocksHash['height'] ?><br/>
                    hash: <?php echo $blocksHash['hash'] ?>
                </div>
            <?php } ?>
        </div>
    </form>
</div>

<div class="mb-5"></div>
