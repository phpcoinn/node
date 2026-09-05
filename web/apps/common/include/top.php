<?php

if(!defined("PAGE")) exit;
const GATEWAY = "PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3";
ob_start();

$theme = "light";
if(isset($_COOKIE['theme'])) {
    $theme = $_COOKIE['theme'];
}

$theme = ($theme == "dark" ? "dark" : "light");

$menuPeers = Peer::findPeers(false, null);
usort($menuPeers, function($p1, $p2) {
    return strcmp($p2['hostname'], $p1['hostname']);
});


if(NETWORK == "mainnet") {
    $priceCtx = stream_context_create([
        'http' => [
            'timeout' => 2,
            'ignore_errors' => true,
            'header' => "User-Agent: PHPCoin Web Wallet\r\n",
        ],
    ]);
    $res = @file_get_contents("https://main1.phpcoin.net/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/api.php?q=coinInfo", false, $priceCtx);
    $res = json_decode($res, true);
    if (is_array($res)) {
        $btcPrice = num($res['btcPrice'] ?? 0, 8);
        $usdPrice = num($res['usdPrice'] ?? 0, 6);
    } else {
        $btcPrice = num(0, 8);
        $usdPrice = num(0, 6);
    }
}

CommonSessionHandler::setup();

if (isset($_GET['auth_data'])) {
    $auth_data = json_decode(base64_decode($_GET['auth_data']), true);
    if(is_array($auth_data) && isset($auth_data['request_code']) && isset($_SESSION['request_code'])
        && hash_equals((string)$_SESSION['request_code'], (string)$auth_data['request_code'])) {
        $_SESSION['account']=$auth_data['account'];
    }
    $redir = $auth_data['redirect'] ?? '/';
    if(!Security::isSafeRedirect($redir)) {
        $redir = '/';
    }
    header("location: " . $redir);
    exit;
}

$logged=false;
if(isset($_SESSION['account'])) {
    $logged=true;
    $session_address=$_SESSION['account']['address'];
    $session_balance = Account::getBalance($_SESSION['account']['address']);
}

$redirect=$_SERVER['REQUEST_URI'];
$explorerConnectToken = bin2hex(random_bytes(32));
$_SESSION['explorer_connect'] = [
    'token' => $explorerConnectToken,
    'return' => Security::isSafeRedirect($redirect) ? $redirect : '/apps/explorer/',
    'expires' => time() + 600,
];

?>
<!doctype html>
<html lang="en">
<head>
	<!-- Required meta tags -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0, shrink-to-fit=no">
	<script src="/apps/common/js/phpcoin-crypto.browser.min.js"></script>


    <!-- preloader css -->
    <link rel="stylesheet" href="/apps/common/css/preloader.min.css" type="text/css" />
    <!-- Bootstrap Css -->
    <link href="/apps/common/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css" />

    <link href="/apps/common/css/sweetalert2.min.css" rel="stylesheet" type="text/css" />
    <!-- Icons Css -->
    <link href="/apps/common/css/icons.min.css" rel="stylesheet" type="text/css" />

    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3955892313807781"
            crossorigin="anonymous"></script>

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
        }
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



<body class="phpcoin" data-layout="horizontal" data-layout-mode="<?php echo $theme ?>">

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
	                        <?php if(Dapps::isEnabled() && !empty($_config['dapps_public_key'])) {
	                        $dapps_id = Account::getAddress($_config['dapps_public_key']);
	                        ?>
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle arrow-none" href="/dapps.php?url=<?php echo $dapps_id ?>" id="topnav-dashboard" role="button">
                                        <i class="fas fa-cubes me-2"></i><span data-key="t-dashboards">Dapps</span>
                                    </a>
                                </li>
                            <?php } ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none" href="/dapps.php?url=<?php echo MAIN_DAPPS_ID ?>/miner" id="topnav-dashboard" role="button">
                                    <i class="fas fa-hammer me-2"></i><span data-key="t-dashboards">Miner</span>
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none" href="https://wallet.phpcoin.net/" target="_blank" rel="noopener noreferrer" id="topnav-dashboard" role="button">
                                    <i class="fas fa-wallet me-2"></i><span data-key="t-dashboards">Wallet</span>
                                </a>
                            </li>
                                <li class="nav-item d-flex align-items-center">
                                    <a href="https://buy.phpcoin.net/?utm_source=explorer&utm_medium=community&utm_campaign=direct_buy" target="_blank"
                                       class="btn btn-primary">Buy PHPCoin</a>
                                </li>
                        </ul>
                        <ul class="navbar-nav d-flex">
                            <?php if($logged) {
                                $address_trunc = substr($session_address, 0, 6) . "..." . substr($session_address, -6);
                                ?>
                                <li class="nav-item d-flex align-items-center" id="account-address">
                                    <div class="nav-link d-flex flex-column align-items-start py-1">
                                        <a title="<?php echo $session_address ?>"
                                           href="/apps/explorer/address.php?address=<?php echo $session_address ?>" target="_blank">
                                            <?php echo $address_trunc ?>
                                        </a>
                                        <a class="small" href="/apps/explorer/address.php?address=<?php echo $session_address ?>">
                                            <?php echo $session_balance ?>
                                        </a>
                                    </div>
                                </li>
                                <li class="nav-item d-flex align-items-center">
                                    <form method="post" action="/apps/explorer/auth.php" class="m-0">
                                        <input type="hidden" name="action" value="logout">
                                        <input type="hidden" name="token" value="<?php echo $explorerConnectToken ?>">
                                        <button type="submit" class="btn btn-outline-primary">Logout</button>
                                    </form>
                                </li>
                            <?php } else { ?>
                                <li class="nav-item d-flex align-items-center">
                                    <button type="button" class="btn btn-primary" id="explorer-wallet-login"
                                            data-token="<?php echo $explorerConnectToken ?>">Login</button>
                                </li>
                            <?php } ?>
	                        <?php if($_config['admin']) { ?>
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle arrow-none <?php if (APP_NAME == "Admin") { ?>active<?php } ?>" href="/apps/admin" id="topnav-dashboard" role="button">
                                        <i class="fas fa-cogs me-2"></i><span data-key="t-dashboards">Admin</span>
                                    </a>
                                </li>
	                        <?php } ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle arrow-none" id="mode-setting-btn" href="#" role="button">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-moon icon-lg layout-mode-dark"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-sun icon-lg layout-mode-light"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                                    <span data-key="t-theme">Theme</span>
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

                    <div style="width: 100%; max-height: 100px !important; margin-bottom: 10px">


                        <!-- node-top -->
                        <ins class="adsbygoogle"
                             style="display:block"
                             data-ad-client="ca-pub-3955892313807781"
                             data-ad-slot="5657775456"
                             data-ad-format="horizontal"
                             data-full-width-responsive="false"></ins>
                        <script>
                            (adsbygoogle = window.adsbygoogle || []).push({});
                        </script>

                    </div>
