<?php
/*
The MIT License (MIT)
Copyright (c) 2018 AroDev
Copyright (c) 2021 PHPCoin

phpcoin.net

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE
OR OTHER DEALINGS IN THE SOFTWARE.
*/

require_once dirname(__DIR__).'/include/init.inc.php';
$current = Block::current();
global $_config;
?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8" />
    <title><?php echo COIN_NAME ?> Node</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="PHPCOin - Pure PHP blockchain built for Web" name="description" />
    <!-- App favicon -->
    <link rel="shortcut icon" href="/favicon.ico">

    <!-- preloader css -->
    <link rel="stylesheet" href="/apps/common/css/preloader.min.css" type="text/css" />

    <!-- Bootstrap Css -->
    <link href="/apps/common/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css" />
    <!-- Icons Css -->
    <link href="/apps/common/css/icons.min.css" rel="stylesheet" type="text/css" />
    <!-- App Css-->
    <link href="/apps/common/css/app.min.css" id="app-style" rel="stylesheet" type="text/css" />

    <style>
        .coming-content {
            align-items: normal;
        }
        .bottom-links {
            position: absolute;
            bottom: 14px;
            z-index: 9;
            left: 50%;
            -webkit-transform: translateX(-50%);
            transform: translateX(-50%);
            text-align: center;
        }
    </style>

</head>

<body>

<div class="preview-img">
    <div class="swiper-container preview-thumb">
        <div class="swiper-wrapper">
            <div class="swiper-slide">
                <div class="slide-bg" style="background-image: url(/apps/common/img/bg.jpg);"></div>
            </div>
        </div>
    </div>
</div>
<!-- preview bg -->
<div class="bg-overlay bg-primary"></div>
<!--<ul class="bg-bubbles">-->
<!--    <li></li>-->
<!--    <li></li>-->
<!--    <li></li>-->
<!--    <li></li>-->
<!--    <li></li>-->
<!--    <li></li>-->
<!--    <li></li>-->
<!--    <li></li>-->
<!--    <li></li>-->
<!--    <li></li>-->
<!--</ul>-->
<div class="coming-content min-vh-100 p-0">

    <div class="container d-flex flex-column justify-content-between">

        <!--        <div class="row justify-content-center">-->
        <!--            <div class="col-lg-8">-->
        <!--                <div class="text-center py-4 py-sm-5">-->

        <div></div>


        <div class="container">
            <div class="mb-5 text-center">
                <a href="/">
                    <img src="/apps/common/img/logo.png" alt="" height="60" class="me-1">
                    <span class="logo-txt text-white font-size-24"><?php echo COIN_NAME ?> Node
                            </span>
                </a>
            </div>
            <div class="row justify-content-center">
                <div class="col-sm-6 col-md-4 col-lg-3 col-12 col-xl-2 my-3">
                    <div class="d-grid gap-2">
                        <a class="btn btn-lg btn-primary btn-block text-white-50 waves-effect waves-light" href="/apps/explorer">
                            <h5 class="mb-3 text-white">Explorer</h5>
                            <i class="fas fa-binoculars fa-2x"></i>
                        </a>
                    </div>
                </div>
                <?php if (Nodeutil::miningEnabled()) { ?>
                    <div class="col-sm-6 col-md-4 col-lg-3 col-12 col-xl-2 my-3">
                        <div class="d-grid gap-2">
                            <a class="btn btn-lg btn-primary btn-block text-white-50 waves-effect waves-light" href="/apps/miner">
                                <h5 class="mb-3 text-white">Miner</h5>
                                <i class="fas fa-hammer fa-2x"></i>
                            </a>
                        </div>
                    </div>
                <?php } ?>
                <?php if(Dapps::isEnabled() && !empty($_config['dapps_public_key'])) {
                    $dapps_id = Account::getAddress($_config['dapps_public_key']);
                    ?>
                    <div class="col-sm-6 col-md-4 col-lg-3 col-12 col-xl-2 my-3">
                        <div class="d-grid gap-2" data-bs-toggle="tooltip" title="Decentralized apps">
                            <a class="btn btn-lg btn-primary btn-block text-white-50 waves-effect waves-light" href="/dapps.php?url=<?php echo $dapps_id ?>">
                                <h5 class="mb-3 text-white">Dapps</h5>
                                <i class="fas fa-cubes fa-2x"></i>
                            </a>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="text-center text-white-50 font-size-14 pb-2">
            <a href="https://phpcoin.net" target="_blank" class="text-white">phpcoin.net</a>
            <br/>
            <?php echo NETWORK ?> ◦
            Block <?php echo $current['height'] ?> ◦
            Version <?php echo VERSION ?>.<?php echo BUILD_VERSION ?>
        </div>
        <!--                </div>-->
        <!--            </div>-->
        <!--        </div>-->
    </div>


    <!-- end container -->
</div>
<!-- coming-content -->

<!-- JAVASCRIPT -->
<script src="/apps/common/js/jquery.min.js"></script>
<script src="/apps/common/js/bootstrap.bundle.min.js"></script>
<script>
    $(function(){
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    })
</script>
</body>
</html>

