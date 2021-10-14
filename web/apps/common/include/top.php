<?php

if(!defined("PAGE")) exit;


?>
<!doctype html>
<html lang="en">
<head>
	<!-- Required meta tags -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0, shrink-to-fit=no">


    <!-- plugin css -->
<!--    <link href="http://localhost:3000/assets/libs/admin-resources/jquery.vectormap/jquery-jvectormap-1.2.2.css" rel="stylesheet" type="text/css" />-->
    <!-- preloader css -->
    <link rel="stylesheet" href="/apps/common/css/preloader.min.css" type="text/css" />
    <!-- Bootstrap Css -->
    <link href="/apps/common/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css" />

    <link href="/apps/common/css/sweetalert2.min.css" rel="stylesheet" type="text/css" />
    <!-- Icons Css -->
    <link href="/apps/common/css/icons.min.css" rel="stylesheet" type="text/css" />
    <!-- App Css-->
    <link href="/apps/common/css/app.min.css" id="app-style" rel="stylesheet" type="text/css" />


	<title><?php echo COIN_NAME ?> Node - Explorer</title>
</head>



<body class="phpcoin" data-layout="horizontal">




    <!--<nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="/apps/common/img/logo.png" alt="" class="d-inline-block align-text-top">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" aria-current="page" href="/">
                            <i class="bi bi-house-door"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php /*if (APP_NAME == "Miner") { */?>active<?php /*} */?>" href="/apps/miner">
                            <i class="bi bi-hammer"></i> Miner
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php /*if (APP_NAME == "Explorer") { */?>active<?php /*} */?>" href="/apps/explorer">
                            <i class="bi bi-binoculars"></i> Explorer
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php /*if (APP_NAME == "Wallet") { */?>active<?php /*} */?>" href="/apps/wallet">
                            <i class="bi bi-wallet"></i> Wallet
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <?php /*if($_config['admin']) { */?>
                            <li class="nav-item">
                                <a class="nav-link" href="/apps/admin">
                                    <i class="bi bi-gear"></i> Admin
                                </a>
                            </li>
                        <?php /*} */?>
                    </ul>
                </div>
            </div>
        </div>
    </nav>-->

<!--    layout-wrapper-->
    <div id="layout-wrapper">

        <div class="topnav">
            <div class="container-fluid">
                <nav class="navbar navbar-light navbar-expand-lg topnav-menu">

                    <button type="button" class="btn btn-sm px-3 font-size-16 d-lg-none waves-effect waves-light" data-bs-toggle="collapse" data-bs-target="#topnav-menu-content">
                        <i class="fa fa-fw fa-bars"></i>
                    </button>

                    <a class="navbar-brand" href="#">
                        <img src="/apps/common/img/logo.png" alt="" class="d-inline-block">
                        <span class="logo-txt text-primary">phpCoin</span>
                    </a>

                    <div class="collapse navbar-collapse d-lg-flex justify-content-between" id="topnav-menu-content">
                        <ul class="navbar-nav d-flex">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none" href="/" id="topnav-dashboard" role="button">
                                    <i class="fas fa-home me-2"></i><span data-key="t-dashboards">Home</span>
                                </a>
                            </li>
                            <?php if (Nodeutil::miningEnabled()) { ?>
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle arrow-none <?php if (APP_NAME == "Miner") { ?>active<?php } ?>" href="/apps/miner" id="topnav-dashboard" role="button">
                                        <i class="fas fa-hammer me-2"></i><span data-key="t-dashboards">Miner</span>
                                    </a>
                                </li>
                            <?php } ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none <?php if (APP_NAME == "Explorer") { ?>active<?php } ?>" href="/apps/explorer" id="topnav-dashboard" role="button">
                                    <i class="fas fa-binoculars me-2"></i><span data-key="t-dashboards">Explorer</span>
                                </a>
                            </li>
	                        <?php if (Nodeutil::walletEnabled()) { ?>
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle arrow-none <?php if (APP_NAME == "Wallet") { ?>active<?php } ?>" href="https://<?php echo APPS_WALLET_SERVER_NAME ?>/apps/wallet" id="topnav-dashboard" role="button">
                                        <i class="fas fa-wallet me-2"></i><span data-key="t-dashboards">Wallet</span>
                                    </a>
                                </li>
	                        <?php } ?>
                            <?php if ($_config['faucet']) { ?>
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle arrow-none <?php if (APP_NAME == "Faucet") { ?>active<?php } ?>" href="/apps/faucet" id="topnav-dashboard" role="button">
                                        <i class="fas fa-faucet me-2"></i><span data-key="t-dashboards">Faucet</span>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                        <ul class="navbar-nav d-flex">
	                        <?php if($_config['admin']) { ?>
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle arrow-none <?php if (APP_NAME == "Admin") { ?>active<?php } ?>" href="/apps/admin" id="topnav-dashboard" role="button">
                                        <i class="fas fa-cogs me-2"></i><span data-key="t-dashboards">Admin</span>
                                    </a>
                                </li>
	                        <?php } ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none" id="mode-setting-btn" href="#" role="button">
                                    <i data-feather="moon" class="icon-lg layout-mode-dark"></i>
                                    <i data-feather="sun" class="icon-lg layout-mode-light"></i>
                                    Theme
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </div>

<!--        main-content-->
        <div class="main-content">
<!--            page-content-->
            <div class="page-content">
<!--                container-->
                <div class="container-fluid">
