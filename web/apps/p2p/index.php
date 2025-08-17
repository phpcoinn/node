<?php

global $_config;
ini_set('display_errors', 1);
error_reporting(E_ALL);

define("REMOTE", true);
require_once dirname(__DIR__)."/apps.inc.php";
require 'phar://'.__DIR__.'/vendor.phar/autoload.php';
require_once ROOT . "/include/class/Pajax.php";
require_once __DIR__ . "/inc/autoloader.php";
require_once __DIR__ . "/inc/functions.php";

$_config['log_file']='tmp/p2p.log';

session_start();

Pajax::handleAuth(AppView::BASE_URL, function($auth_data) {
    _log("Logged in account: ".$auth_data['account']['address']);
});
Pajax::processAjax();

_log("Open p2p page from ".$_SERVER['REMOTE_ADDR']);

//set_error_handler(function($severity, $message, $file, $line) {
//    $a=1;
//});
//
//register_shutdown_function(function () {
//    $error = error_get_last();
//    print_r($error);
//});

$loggedIn = isset($_SESSION['account']);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHPCoin P2P Trader</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <link href="/apps/common/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css" />


    <link href="/apps/common/css/sweetalert2.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="/apps/common/css/preloader.min.css" type="text/css" />
    <link href="/apps/common/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="/apps/common/css/app.min.css" id="app-style" rel="stylesheet" type="text/css" />
    <link href="/apps/p2p/css/style.css" rel="stylesheet" type="text/css" />
    <?php if (is_mobile()) { ?>
        <link href="/apps/p2p/css/mobile.css" rel="stylesheet" type="text/css" />
    <?php } ?>
    <style>
        .circle-progress {
            --size: 120px;
            --thickness: 12px;
            --track-color: #e9ecef;
            --progress: 0; /* percentage: 0–100 */

            width: var(--size);
            height: var(--size);
            border-radius: 50%;
            background: conic-gradient(
                    var(--bs-primary) calc(var(--progress) * 1%),
                    var(--track-color) 0
            );
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .circle-inner {
            width: calc(var(--size) - var(--thickness) * 2);
            height: calc(var(--size) - var(--thickness) * 2);
            background-color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .minutes {
            font-weight: bold;
            font-size: 1rem;
        }

        table td {
            vertical-align: middle;
        }

        .center-col {
            height:70vh;
            display: flex;
            flex-direction: column;
        }
        .right-col {
            height:70vh;
        }


        .mobile-hidden3 {
            display: none;
        }


        .tab-pane.active {
            display: flex;
            flex-direction: column;
        }
    </style>
    <style>
        body { margin: 0; padding: 1rem; font-family: Arial; }
        #chart { width: 100%; height: 100%; }
        #controls { margin-bottom: 1rem; }
    </style>
</head>
<body class="p-0">

<div class="container">
    <?php
        Pajax::app(AppView::class, ['id'=>'p2p-app']);
    ?>
</div>

<script src="/apps/common/js/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>



<script type="text/javascript" src="/apps/wallet2/js/pajax.js"></script>
<script src="/apps/common/js/sweetalert2.min.js"></script>
<script src="/apps/common/js/pace.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/ethers@6.8.1/dist/ethers.umd.min.js"></script>
<script src="/apps/p2p/app.js"></script>
<script type="text/javascript">
    window.isMobile = <?= is_mobile() ? 'true' : 'false' ?>;
    $(function(){
        <?php if(isset($_REQUEST['callback'])) { ?>
        paction(null, '<?= $_REQUEST['callback'] ?>', [<?= json_encode($_REQUEST) ?>], {view: '#p2p-app'});
        <?php } ?>
    });
    <?php if(isset($_SESSION['offer_id'])) {
        $offer_id=$_SESSION['offer_id'];
        unset($_SESSION['offer_id']);
        ?>
        $(function(){
            openOffer(<?=$offer_id?>);
        })
    <?php } ?>
</script>
<script src="/apps/common/js/pace.min.js"></script>
<script src="https://unpkg.com/lightweight-charts@4.2.3/dist/lightweight-charts.standalone.development.js"></script>
<script src="/apps/common/js/bootstrap.bundle.min.js"></script>

</body>
</html>
