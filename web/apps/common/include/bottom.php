</div>
</div>
<?php
global $_config;
$maxPeerBuildNumber = Peer::getMaxBuildNumber();
$currentVersion = BUILD_VERSION;
$updateAvb = $maxPeerBuildNumber > $currentVersion;
?>
<footer class="footer">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-6 text-center text-md-start mb-2 mb-sm-0">
	            <?php echo COIN_NAME ?> - <?php if(defined("NETWORK")) echo strtoupper(NETWORK) ?> (<?php echo CHAIN_ID ?>) - <?php echo VERSION ?><?php if(defined("BUILD_VERSION")) echo "." . BUILD_VERSION ?>
                <?php
                if(!empty($gitRev)) { ?>
                    - <a href="<?php echo GIT_URL ?>/tree/<?php echo $gitRev ?>" target="_blank"><?php echo substr($gitRev, 0, 8) ?></a>
                <?php } ?>
                <?php if ($updateAvb) { ?>
                    <span class="badge rounded-pill bg-success">Update available!</span>
                <?php } ?>
                |
                Trade on <a href="https://klingex.io/trade/PHP-USDT?ref=3436CA42" target="_blank">KlingEx</a>
            </div>
            <div class="col-sm-6">
                <div class="text-center text-md-end d-flex justify-content-center justify-content-sm-end align-items-center mb-2 mb-sm-0">
                    <span class="pe-2">Node score: <?php echo $nodeScore ?>%</span>
                    <div class="progress progress-lg node-score me-1" title="Node score: <?php echo $nodeScore ?>%" data-bs-toggle="tooltip">
                        <div class="progress-bar bg-<?php echo ($nodeScore < MIN_NODE_SCORE / 2 ? 'danger' : ($nodeScore < MIN_NODE_SCORE ? 'warning' : 'success')) ?>" role="progressbar" style="width: <?php echo $nodeScore ?>%;" aria-valuenow="<?php echo $nodeScore ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>



</div>


</div>
<span id="layout-horizontal"></span>
<span id="layout-mode-dark"></span>
<span id="layout-mode-light"></span>
<span id="layout-width-fuild"></span>
<span id="layout-position-fixed"></span>
<span id="topbar-color-light"></span>
<span id="sidebar-size-default"></span>
<span id="sidebar-color-light"></span>
<span id="layout-direction-ltr"></span>
<span id="sidebar-color-dark"></span>
<span id="topbar-color-dark"></span>


<!-- JAVASCRIPT -->
<script src="/apps/common/js/jquery.min.js"></script>
<script src="/apps/common/js/bootstrap.bundle.min.js"></script>
<script src="/apps/common/js/metisMenu.min.js"></script>
<script src="/apps/common/js/simplebar.min.js"></script>
<script src="/apps/common/js/waves.min.js"></script>
<script src="/apps/common/js/feather.min.js"></script>
<!-- pace js -->
<script src="/apps/common/js/pace.min.js"></script>
<script src="/apps/common/js/sweetalert2.min.js"></script>

<script src="/apps/common/js/app.js"></script>
<?php if (false) { ?>
    <script src="/apps/common/js/web-node-miner.js"></script>
<?php } ?>
<script>

    function setCookie(name,value,days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days*24*60*60*1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "")  + expires + "; path=/";
    }

    $(function(){

        <?php if(isset($_SESSION['msg'])) { ?>
        <?php foreach ($_SESSION['msg'] as $msg) { ?>
        Swal.fire(
            {
                text: '<?php echo str_replace("\n", "<br/>", $msg['text']) ?>',
                icon: '<?php echo $msg['icon'] ?>'
            }
        );
        <?php } ?>
        <?php unset($_SESSION['msg']); } ?>

        $('#mode-setting-btn').on('click', function (e) {
            let theme = $('body').attr('data-layout-mode');
            setCookie('theme', theme, 3);
        });

    });

</script>


</body>
</html>
