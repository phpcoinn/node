</div>
</div>

<!--<div class="footer">
    <div class="container">
        <div class="row">
            <div class="col-sm-6">
				<?php /*echo COIN_NAME */?> - <?php /*echo $_config['testnet'] ? 'TESTNET' : '' */?> - <?php /*echo VERSION */?>
            </div>
            <div class="col-sm-6 d-flex justify-content-end align-items-center">
                <div class="progress pointer node-score me-1" title="Node score: <?php /*echo $nodeScore */?>%" data-bs-toggle="tooltip">
                    <div class="progress-bar bg-<?php /*echo ($nodeScore < 50 ? 'danger' : ($nodeScore < 100 ? 'warning' : 'success')) */?>" role="progressbar" style="width: <?php /*echo $nodeScore */?>%" aria-valuenow="<?php /*echo $nodeScore */?>>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <span class="badge bg-secondary pointer apps-version me-1" title="Apps version" data-bs-toggle="tooltip"><?php /*echo APPS_VERSION */?></span>
				<?php /*echo hashimg($appsHash, "Apps hash: ". $appsHash) */?>
            </div>
        </div>
    </div>
</div>-->

<footer class="footer">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-6 text-center text-md-start mb-2 mb-sm-0">
	            <?php echo COIN_NAME ?> - <?php echo $_config['testnet'] ? 'TESTNET' : '' ?> - <?php echo VERSION ?>
                <?php
                if(!empty($gitRev)) { ?>
                    - <a href="<?php echo GIT_URL ?>/tree/<?php echo $gitRev ?>" target="_blank"><?php echo substr($gitRev, 0, 8) ?></a>
                <?php } ?>
            </div>
            <div class="col-sm-6">
                <div class="text-center text-md-end d-flex justify-content-center justify-content-sm-end align-items-center mb-2 mb-sm-0">

                    <div class="progress progress-lg node-score me-1" title="Node score: <?php echo $nodeScore ?>%" data-bs-toggle="tooltip">
                        <div class="progress-bar bg-<?php echo ($nodeScore < 50 ? 'danger' : ($nodeScore < 100 ? 'warning' : 'success')) ?>" role="progressbar" style="width: <?php echo $nodeScore ?>%;" aria-valuenow="<?php echo $nodeScore ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>

                    <span class="badge bg-secondary pointer apps-version me-1" title="Apps version" data-bs-toggle="tooltip"><?php echo APPS_VERSION ?></span>
	                <?php echo hashimg($appsHash, "Apps hash: ". $appsHash) ?>
                </div>
            </div>
        </div>
    </div>
</footer>



</div>


</div>


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

<script>

    $(function(){

        <?php if(isset($_SESSION['msg'])) { ?>
        <?php foreach ($_SESSION['msg'] as $msg) { ?>
        Swal.fire(
            {
                text: '<?php echo $msg['text'] ?>',
                icon: '<?php echo $msg['icon'] ?>'
            }
        );
        <?php } ?>
        <?php unset($_SESSION['msg']); } ?>

    });

</script>


</body>
</html>
