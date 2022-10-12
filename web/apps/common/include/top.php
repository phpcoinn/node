<?php

if(!defined("PAGE")) exit;

$menuPeers = Peer::getPeersForSync();
usort($menuPeers, function($p1, $p2) {
    return strcmp($p2['hostname'], $p1['hostname']);
})
?>
<!doctype html>
<html lang="en">
<head>
	<!-- Required meta tags -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0, shrink-to-fit=no">


    <!-- preloader css -->
    <link rel="stylesheet" href="/apps/common/css/preloader.min.css" type="text/css" />
    <!-- Bootstrap Css -->
    <link href="/apps/common/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css" />

    <link href="/apps/common/css/sweetalert2.min.css" rel="stylesheet" type="text/css" />
    <!-- Icons Css -->
    <link href="/apps/common/css/icons.min.css" rel="stylesheet" type="text/css" />

    <?php
    if (defined("HEAD_CSS")) {
        $headCss = HEAD_CSS;
        if(!is_array($headCss)) {
            $headCss = [$headCss];
        }
        foreach($headCss as $item) {
            echo '<link href="'.$item.'" rel="stylesheet" type="text/css" />';
        }
    }
    ?>

    <!-- App Css-->
    <link href="/apps/common/css/app.min.css" id="app-style" rel="stylesheet" type="text/css" />


	<title><?php echo COIN_NAME ?> Node - Explorer</title>

    <style>
        body.phpcoin #page-topbar {
            display: none; }
        body.phpcoin .topnav {
            margin-top: 0;
            background-color: #fbfaff; }
        body.phpcoin .page-content {
            margin-top: 0 !important; }
        body.phpcoin .navbar-brand img {
            height: 40px;
            width: auto; }
        body.phpcoin .hash {
            display: flex;
            width: 100px;
            height: 16px;
            cursor: pointer; }
        body.phpcoin .hash div {
            flex: 1; }
        body.phpcoin .node-score {
            width: 80px;
            height: 16px; }

        table.dataTable > thead > tr > th:not(.sorting_disabled),
        table.dataTable > thead > tr > td:not(.sorting_disabled) {
            padding-right: 30px; }

        table.dataTable > thead .sorting,
        table.dataTable > thead .sorting_asc,
        table.dataTable > thead .sorting_desc,
        table.dataTable > thead .sorting_asc_disabled,
        table.dataTable > thead .sorting_desc_disabled {
            cursor: pointer;
            position: relative; }

        table.dataTable > thead .sorting:before, table.dataTable > thead .sorting:after,
        table.dataTable > thead .sorting_asc:before,
        table.dataTable > thead .sorting_asc:after,
        table.dataTable > thead .sorting_desc:before,
        table.dataTable > thead .sorting_desc:after,
        table.dataTable > thead .sorting_asc_disabled:before,
        table.dataTable > thead .sorting_asc_disabled:after,
        table.dataTable > thead .sorting_desc_disabled:before,
        table.dataTable > thead .sorting_desc_disabled:after {
            position: absolute;
            bottom: 0.3em;
            display: block;
            opacity: 0.3; }

        table.dataTable > thead .sorting:before,
        table.dataTable > thead .sorting_asc:before,
        table.dataTable > thead .sorting_desc:before,
        table.dataTable > thead .sorting_asc_disabled:before,
        table.dataTable > thead .sorting_desc_disabled:before {
            right: 1em;
            content: "↑"; }

        table.dataTable > thead .sorting:after,
        table.dataTable > thead .sorting_asc:after,
        table.dataTable > thead .sorting_desc:after,
        table.dataTable > thead .sorting_asc_disabled:after,
        table.dataTable > thead .sorting_desc_disabled:after {
            right: 0.5em;
            content: "↓"; }

        table.dataTable > thead .sorting_asc:before,
        table.dataTable > thead .sorting_desc:after {
            opacity: 1; }

        table.dataTable > thead .sorting_asc_disabled:before,
        table.dataTable > thead .sorting_desc_disabled:after {
            opacity: 0; }

        #peers-menu {
            max-height: 80vh;
            overflow-y: auto;
        }
    </style>

    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-0PW26G05V9"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-0PW26G05V9');
    </script>


</head>



<body class="phpcoin" data-layout="horizontal">

<!--    layout-wrapper-->
    <div id="layout-wrapper">

        <div class="topnav">
            <div class="container-fluid">
                <nav class="navbar navbar-light navbar-expand-lg topnav-menu">

                    <button type="button" class="btn btn-sm px-3 font-size-16 d-lg-none waves-effect waves-light" data-bs-toggle="collapse" data-bs-target="#topnav-menu-content">
                        <i class="fa fa-fw fa-bars"></i>
                    </button>

                    <a class="navbar-brand" href="https://phpcoin.net" target="_blank">
                        <img src="/apps/common/img/logo.png" alt="" class="d-inline-block">
                        <span class="logo-txt text-primary">
                            <?php echo COIN_NAME ?>
                        </span>
                    </a>

                    <div class="collapse navbar-collapse d-lg-flex justify-content-between" id="topnav-menu-content">
                        <ul class="navbar-nav d-flex">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none" href="/" id="topnav-dashboard" role="button">
                                    <i class="fas fa-home me-2"></i><span data-key="t-dashboards">Home</span>
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none <?php if (APP_NAME == "Explorer") { ?>active<?php } ?>" href="/apps/explorer" id="topnav-dashboard" role="button">
                                    <i class="fas fa-binoculars me-2"></i><span data-key="t-dashboards">Explorer</span>
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none" href="/dapps.php?url=<?php echo MAIN_DAPPS_ID ?>/miner" id="topnav-dashboard" role="button">
                                    <i class="fas fa-hammer me-2"></i><span data-key="t-dashboards">Miner</span>
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none" href="/dapps.php?url=<?php echo MAIN_DAPPS_ID ?>/wallet" id="topnav-dashboard" role="button">
                                    <i class="fas fa-wallet me-2"></i><span data-key="t-dashboards">Wallet</span>
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none" href="/dapps.php?url=<?php echo MAIN_DAPPS_ID ?>/faucet" id="topnav-dashboard" role="button">
                                    <i class="fas fa-faucet me-2"></i><span data-key="t-dashboards">Faucet</span>
                                </a>
                            </li>

                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none" href="#" id="topnav-pages" role="button">
                                    <i class="fas fa-network-wired me-2"></i><span data-key="t-peers">Peers</span> <div class="arrow-down"></div>
                                </a>
                                <div class="dropdown-menu" aria-labelledby="topnav-pages" id="peers-menu">
                                    <?php foreach($menuPeers as $peer) { ?>
                                        <a href="<?php echo $peer['hostname'] . $_SERVER['REQUEST_URI'] ?>" class="dropdown-item <?php if ($peer['ip'] == $_SERVER['SERVER_ADDR']) { ?>active<?php } ?>" data-key="t-calendar"><?php echo $peer['hostname'] ?></a>
                                    <?php } ?>
                                </div>
                            </li>
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
