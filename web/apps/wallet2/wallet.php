<?php
global $db;
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__) . "/apps.inc.php";

session_start();

require_once 'inc/actions.php';
require_once 'inc/functions.php';

$address = $_SESSION['currentAddress'];
$balance = Account::pendingBalance($address);

$icon = new \Jdenticon\Identicon();
$icon->setValue($address);
$icon->setSize(256);
$addressIcon = $icon->getImageDataUri();

$addresses = $_SESSION['wallet']['addresses'] ?? [];

$height = Block::getHeight();

$balances=[];
$transactions=[];
$rewards=[];
for($days = 7; $days >= 0; $days--) {
    $historyData = getHistoryData($address, $height - 1440*$days);
    $balances[]=$historyData['balance'];
    $transactions[]=$historyData['transactions'];
    $rewards[]=$historyData['reward'];
}

$lastWeekBalance = $balances[0];
$lastWeekBalanceDiff = $balance - $lastWeekBalance;

$lastTransactions = array_pop($transactions);
$lastWeekTransactions = $transactions[0];
$lastWeekTransactionsDiff = $lastTransactions - $lastWeekTransactions;

$url="https://api.xeggex.com/api/v2/market/candles?symbol=PHP%2FUSDT&from=".(time()-1140*7)."&to=".time()."&resolution=1440&countBack=7";
$res = file_get_contents($url);
$res = json_decode($res, true);

$prices = [];
foreach ($res['bars'] as $item) {
    $prices[]=$item['close'];
}
$currentPrice = $prices[count($prices)-1];
$lastWeekPrice =$prices[0];
$priceDiff = $currentPrice - $lastWeekPrice;

$currentReward = $rewards[count($rewards)-1];
$lastWeekReward = $rewards[0];
$rewardDiff = $currentReward - $lastWeekReward;

$rewards1 = [];
foreach ($rewards as $ix => $reward) {
    if($ix == 0) continue;
    $rewards1[]= $reward - $rewards[$ix-1];
}

$walletRewards = getWalletRewardsInfo($address);


?>
<!doctype html>
<html lang="en">

    <head>

        <meta charset="utf-8" />
        <title>Dashboard | Minia - Minimal Admin & Dashboard Template</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta content="Premium Multipurpose Admin & Dashboard Template" name="description" />
        <meta content="Themesbrand" name="author" />
        <!-- App favicon -->
        <link rel="shortcut icon" href="assets/images/favicon.ico">

        <!-- plugin css -->
        <link href="assets/libs/admin-resources/jquery.vectormap/jquery-jvectormap-1.2.2.css" rel="stylesheet" type="text/css" />

        <!-- preloader css -->
        <link rel="stylesheet" href="assets/css/preloader.min.css" type="text/css" />

        <!-- Bootstrap Css -->
        <link href="assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css" />
        <!-- Icons Css -->
        <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
        <!-- App Css-->
        <link href="assets/css/app.min.css" id="app-style" rel="stylesheet" type="text/css" />
        <link href="/apps/common/css/sweetalert2.min.css" rel="stylesheet" type="text/css" />
    </head>

    <body data-address="<?php echo $address ?>">

    <!-- <body data-layout="horizontal"> -->

        <!-- Begin page -->
        <div id="layout-wrapper">

            
            <header id="page-topbar">
                <div class="navbar-header">
                    <div class="d-flex">
                        <!-- LOGO -->
                        <div class="navbar-brand-box">
                            <a href="/apps/explorer" class="logo logo-dark">
                                <span class="logo-sm">
                                    <img src="/apps/common/img/logo.png" alt="" height="24">
                                </span>
                                <span class="logo-lg">
                                    <img src="/apps/common/img/logo.png" alt="" height="24"> <span class="logo-txt">PHPCoin</span>
                                </span>
                            </a>

                            <a href="/apps/explorer" class="logo logo-light">
                                <span class="logo-sm">
                                    <img src="/apps/common/img/logo.png" alt="" height="24">
                                </span>
                                <span class="logo-lg">
                                    <img src="/apps/common/img/logo.png" alt="" height="24"> <span class="logo-txt">PHPCoin</span>
                                </span>
                            </a>
                        </div>

                        <button type="button" class="btn btn-sm px-3 font-size-16 header-item" id="vertical-menu-btn">
                            <i class="fa fa-fw fa-bars"></i>
                        </button>

                    </div>

                    <div class="d-flex">

                        <div class="dropdown d-inline-block">
                            <button type="button" class="btn header-item" id="mode-setting-btn">
                                <i data-feather="moon" class="icon-lg layout-mode-dark"></i>
                                <i data-feather="sun" class="icon-lg layout-mode-light"></i>
                            </button>
                        </div>

                        <div class="dropdown d-inline-block ms-1">
                            <button type="button" class="btn header-item"
                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i data-feather="grid" class="icon-lg"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                                <div class="p-2">
                                    <div class="row g-0">
                                        <div class="col">
                                            <a class="dropdown-icon-item" href="/apps/explorer">
                                                <i class="fas fa-binoculars me-2 fa-2x"></i>
                                                <span>Explorer</span>
                                            </a>
                                        </div>
                                        <div class="col">
                                            <a class="dropdown-icon-item" href="/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/miner">
                                                <i class="fas fa-hammer me-2 fa-2x"></i>
                                                <span>Miner</span>
                                            </a>
                                        </div>
                                        <div class="col">
                                            <a class="dropdown-icon-item" href="/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3">
                                                <i class="fas fa-cubes me-2 fa-2x"></i>
                                                <span>Dapps</span>
                                            </a>
                                        </div>
                                    </div>

                                    <div class="row g-0">
                                        <div class="col">
                                            <a class="dropdown-icon-item" href="/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/faucet">
                                                <i class="fas fa-faucet me-2 fa-2x"></i>
                                                <span>Faucet</span>
                                            </a>
                                        </div>
                                        <div class="col">
                                            <a class="dropdown-icon-item" href="https://github.com/phpcoinn">
                                                <i class="mdi mdi-github me-2 mdi-24px"></i>
                                                <span>Github</span>
                                            </a>
                                        </div>
                                        <div class="col">
                                            <a class="dropdown-icon-item" href="https://discord.gg/2H2YvFexQq">
                                                <i class="mdi mdi-discord me-2 mdi-24px"></i>
                                                <span>Discord</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="dropdown d-inline-block">
                            <button type="button" class="btn header-item bg-light-subtle border-start border-end d-flex align-items-center" id="page-header-user-dropdown"
                                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <img class="rounded-circle header-profile-user" src="<?php echo $addressIcon ?>"
                                     alt="Header Avatar">
                                <div class="d-none d-xl-inline-block ms-2 fw-medium text-start">
                                    <div><?php echo $address ?></div>
                                    <div class="d-flex">
                                        <?php echo $balance ?>
                                        <span id="account-name" class="ms-auto text-muted"></span>
                                    </div>
                                </div>
                                <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <?php foreach ($addresses as $address1) { ?>
                                    <a class="dropdown-item <?php if($address1 == $address) { ?>bg-primary text-light<?php } ?>" href="?action=switch_address&address=<?php echo $address1 ?>">
                                        <i class="mdi mdi mdi-wallet-outline font-size-16 align-middle me-1"></i>
                                        <?php echo $address1 ?>
                                    </a>
                                <?php } ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="accounts.php">
                                    <i class="fa fa-address-book font-size-16 align-middle me-1"></i>
                                    Manage accounts
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </header>

            <!-- ========== Left Sidebar Start ========== -->
            <div class="vertical-menu">

                <div data-simplebar class="h-100">

                    <!--- Sidemenu -->
                    <div id="sidebar-menu">
                        <!-- Left Menu Start -->
                        <ul class="metismenu list-unstyled" id="side-menu">
                            <li class="menu-title" data-key="t-menu">Menu</li>

                            <li>
                                <a href="index.php">
                                    <i data-feather="home"></i>
                                    <span data-key="t-dashboard">My wallet</span>
                                </a>
                            </li>
                            <li>
                                <a href="?action=logout">
                                    <i data-feather="home"></i>
                                    <span data-key="t-dashboard">Logout</span>
                                </a>
                            </li>

                            <li>
                                <a href="javascript: void(0);" class="has-arrow">
                                    <i data-feather="grid"></i>
                                    <span data-key="t-apps">Apps</span>
                                </a>
                                <ul class="sub-menu" aria-expanded="false">
                                    <li>
                                        <a href="apps-calendar.html">
                                            <span data-key="t-calendar">Calendar</span>
                                        </a>
                                    </li>

                                    <li>
                                        <a href="javascript: void(0);" class="has-arrow">
                                            <span data-key="t-email">Email</span>
                                        </a>
                                        <ul class="sub-menu" aria-expanded="false">
                                            <li><a href="apps-email-inbox.html" data-key="t-inbox">Inbox</a></li>
                                        </ul>
                                    </li>
                                </ul>
                            </li>
                        </ul>

                        <div class="card sidebar-alert border-0 text-center mx-4 mb-0 mt-5">
                            <div class="card-body">
                                <img src="assets/images/giftbox.png" alt="">
                                <div class="mt-4">
                                    <h5 class="alertcard-title font-size-16">PHPCOin Ads</h5>
                                    <p class="font-size-13">TODO: Display ads here</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Sidebar -->
                </div>
            </div>
            <!-- Left Sidebar End -->

            

            <!-- ============================================================== -->
            <!-- Start right Content here -->
            <!-- ============================================================== -->
            <div class="main-content">

                <div class="page-content">
                    <div class="container-fluid">

                        <!-- start page title -->
                        <div class="row">
                            <div class="col-12">
                                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                                    <h4 class="mb-sm-0 font-size-18">Dashboard</h4>

                                    <div class="page-title-right">
                                        <ol class="breadcrumb m-0">
                                            <li class="breadcrumb-item"><a href="javascript: void(0);">Dashboard</a></li>
                                            <li class="breadcrumb-item active">Dashboard</li>
                                        </ol>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <!-- end page title -->

                        <div class="row">
                            <div class="col-xl-3 col-md-6">
                                <!-- card -->
                                <div class="card card-h-100">
                                    <!-- card body -->
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-6">
                                                <span class="text-muted mb-3 lh-1 d-block text-truncate">Balance</span>
                                                <h4 class="mb-3">
                                                    <span class="counter-value" data-target="<?php echo $balance ?>">
                                                        0
                                                    </span>
                                                </h4>
                                            </div>

                                            <div class="col-6">
                                                <div id="mini-chart1" data-colors='["#5156be"]' class="apex-charts mb-2" data-values="<?php echo json_encode($balances) ?>"></div>
                                            </div>
                                        </div>
                                        <div class="text-nowrap">
                                            <span class="badge bg-<?php echo colorDiff($lastWeekBalance) ?>-subtle text-<?php echo colorDiff($lastWeekBalance) ?>">
                                                <?php echo humanDiff($lastWeekBalanceDiff) ?>
                                            </span>
                                            <span class="ms-1 text-muted font-size-13">Since last week</span>
                                        </div>
                                    </div><!-- end card body -->
                                </div><!-- end card -->
                            </div><!-- end col -->
        
                            <div class="col-xl-3 col-md-6">
                                <!-- card -->
                                <div class="card card-h-100">
                                    <!-- card body -->
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-6">
                                                <span class="text-muted mb-3 lh-1 d-block text-truncate">Transactions</span>
                                                <h4 class="mb-3">
                                                    <span class="counter-value" data-target="<?php echo $lastTransactions ?>">0</span>
                                                </h4>
                                            </div>
                                            <div class="col-6">
                                                <div id="mini-chart2" data-colors='["#5156be"]' class="apex-charts mb-2"
                                                     data-values="<?php echo json_encode($transactions) ?>"></div>
                                            </div>
                                        </div>
                                        <div class="text-nowrap">
                                            <span class="badge bg-<?php echo colorDiff($lastWeekTransactionsDiff) ?>-subtle text-<?php echo colorDiff($lastWeekTransactionsDiff) ?>">
                                                <?php echo humanDiff($lastWeekTransactionsDiff) ?>
                                            </span>
                                            <span class="ms-1 text-muted font-size-13">Since last week</span>
                                        </div>
                                    </div><!-- end card body -->
                                </div><!-- end card -->
                            </div><!-- end col-->
        
                            <div class="col-xl-3 col-md-6">
                                <!-- card -->
                                <div class="card card-h-100">
                                    <!-- card body -->
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-6">
                                                <span class="text-muted mb-3 lh-1 d-block text-truncate">Coin price</span>
                                                <h4 class="mb-3">
                                                    $<span class="counter-value" data-target="<?php echo $currentPrice ?>">0</span>
                                                </h4>
                                            </div>
                                            <div class="col-6">
                                                <div id="mini-chart3" data-colors='["#5156be"]' class="apex-charts mb-2" data-values="<?php echo json_encode($prices) ?>"></div>
                                            </div>
                                        </div>
                                        <div class="text-nowrap">
                                            <span class="badge bg-<?php echo colorDiff($priceDiff) ?>-subtle text-<?php echo colorDiff($priceDiff) ?>">
                                                <?php echo number_format($priceDiff,6) ?>
                                            </span>
                                            <span class="ms-1 text-muted font-size-13">Since last week</span>
                                        </div>
                                    </div><!-- end card body -->
                                </div><!-- end card -->
                            </div><!-- end col -->
        
                            <div class="col-xl-3 col-md-6">
                                <!-- card -->
                                <div class="card card-h-100">
                                    <!-- card body -->
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-6">
                                                <span class="text-muted mb-3 lh-1 d-block text-truncate">Rewards</span>
                                                <h4 class="mb-3">
                                                    <span class="counter-value" data-target="<?php echo $currentReward ?>">0</span>
                                                </h4>
                                            </div>
                                            <div class="col-6">
                                                <div id="mini-chart4" data-colors='["#5156be"]' class="apex-charts mb-2" data-values="<?php echo json_encode($rewards1) ?>"></div>
                                            </div>
                                        </div>
                                        <div class="text-nowrap">
                                            <span class="badge bg-<?php echo colorDiff($rewardDiff) ?>-subtle text-<?php echo colorDiff($rewardDiff) ?>">
                                                <?php echo humanDiff($rewardDiff) ?>
                                            </span>
                                            <span class="ms-1 text-muted font-size-13">Since last week</span>
                                        </div>
                                    </div><!-- end card body -->
                                </div><!-- end card -->
                            </div><!-- end col -->    
                        </div><!-- end row-->

                        <div class="row">
                            <div class="col-xl-5" id="wallet-rewards">
                                <!-- card -->
                                <div class="card card-h-100">
                                    <!-- card body -->
                                    <div class="card-body" ref="walletChart" style="display: none">
                                        <div class="d-flex flex-wrap align-items-center mb-4">
                                            <h5 class="card-title me-2">
                                                Wallet Rewards
                                                <i class="fa fa-spinner fa-spin text-primary ms-2" v-if="!chartData"></i>
                                            </h5>
                                            <div class="ms-auto">
                                                <div>
                                                    <button type="button" @click="loadWalletRewards(period)"
                                                            :class="`btn btn-soft-${period === selPeriod ? 'primary' : 'secondary'} btn-sm me-1`" v-for="period in ['1W','1M','1Y','ALL']">
                                                        {{period}}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row align-items-center">
                                            <div class="col-sm">
                                                <div id="wallet-balance" class="apex-charts"></div>
                                            </div>

                                            <div class="col-sm align-self-center">
                                                <div class="mt-4 mt-sm-0" v-if="chartData">
                                                    <div v-for="(item, ix) in chartData" :key="ix">
                                                        <p class="mb-2">
                                                            <i class="mdi mdi-circle align-middle font-size-10 me-2" :style="`color: ${chartColors[ix]}`"></i>
                                                            {{item.type}}
                                                        </p>
                                                        <h6>
                                                            {{item.amount}} PHP =
                                                            <span class="text-muted font-size-14 fw-normal">$ {{item.usdValue}}</span>
                                                        </h6>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- end card -->
                            </div>
                            <!-- end col -->
                            <div class="col-xl-7">
                                <div class="row">
                                    <div class="col-xl-8">
                                        <!-- card -->
                                        <div class="card card-h-100" id="wallet-mn">
                                            <!-- card body -->
                                            <div class="card-body" ref="mnRoi" style="visibility: hidden">
                                                <div class="d-flex flex-wrap align-items-center mb-4">
                                                    <h5 class="card-title me-2">
                                                        Masternode ROI
                                                        <i class="fa fa-spinner fa-spin text-primary ms-2" v-if="!mnRoiData"></i>
                                                    </h5>
                                                    <div class="ms-auto">
                                                        <div>
                                                            <button type="button" @click="switchPeriod(p)"
                                                                    :class="`btn btn-soft-${p === period ? 'primary' : 'secondary'} btn-sm me-1`"
                                                                    v-for="(label,p) in periods">
                                                                {{label}}
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
            
                                                <div class="row align-items-center">
                                                    <div class="col-sm">
                                                        <div id="invested-overview" class="apex-charts"></div>
                                                    </div>
                                                    <div class="col-sm align-self-center">
                                                        <div class="mt-4 mt-sm-0" v-if="mnRoiData">
                                                            <p class="mb-1">Locked collateral</p>
                                                            <h4>{{mnRoiData.locked}} <br/><span class="text-muted fs-6">$ {{mnRoiData.locked_usd}}</span></h4>

                                                            <p class="text-muted mb-4"> {{mnRoiData.mn_count}} masternodes</p>

                                                            <div class="row g-0">
                                                                <div class="col-6">
                                                                    <div>
                                                                        <p class="mb-2 text-muted text-uppercase font-size-11">Earned</p>
                                                                        <h5 class="fw-medium">{{mnRoiData.earned}} <br/><span class="text-muted fs-6">$ {{mnRoiData.earned_usd}}</span></h5>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6" v-if="mnRoiData[period]">
                                                                    <div>
                                                                        <p class="mb-2 text-muted text-uppercase font-size-11">{{period}}</p>
                                                                        <h5 class="fw-medium">{{mnRoiData[period].earned}}
                                                                            <br/><span class="text-muted fs-6">$ {{mnRoiData[period].usd}}</span></h5>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="mt-2">
                                                                <a href="#" class="btn btn-primary btn-sm">View more <i class="mdi mdi-arrow-right ms-1"></i></a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- end col -->

                                    <div class="col-xl-4">
                                        <!-- card -->
                                        <div class="card bg-primary text-white shadow-primary card-h-100">
                                            <!-- card body -->
                                            <div class="card-body p-0">
                                                <div id="carouselExampleCaptions" class="carousel slide text-center widget-carousel" data-bs-ride="carousel">                                                   
                                                    <div class="carousel-inner">
                                                        <div class="carousel-item active">
                                                            <div class="text-center p-4">
                                                                <i class="mdi mdi-bitcoin widget-box-1-icon"></i>
                                                                <div class="avatar-md m-auto">
                                                                    <span class="avatar-title rounded-circle bg-light-subtle text-white font-size-24">
                                                                        <i class="mdi mdi-currency-btc"></i>
                                                                    </span>
                                                                </div>
                                                                <h4 class="mt-3 lh-base fw-normal text-white"><b>Bitcoin</b> News</h4>
                                                                <p class="text-white-50 font-size-13">Bitcoin prices fell sharply amid the global sell-off in equities. Negative news
                                                                    over the Bitcoin past week has dampened Bitcoin basics
                                                                    sentiment for bitcoin. </p>
                                                                <button type="button" class="btn btn-light btn-sm">View details <i class="mdi mdi-arrow-right ms-1"></i></button>
                                                            </div>
                                                        </div>
                                                        <!-- end carousel-item -->
                                                        <div class="carousel-item">
                                                            <div class="text-center p-4">
                                                                <i class="mdi mdi-ethereum widget-box-1-icon"></i>
                                                                <div class="avatar-md m-auto">
                                                                    <span class="avatar-title rounded-circle bg-light-subtle text-white font-size-24">
                                                                        <i class="mdi mdi-ethereum"></i>
                                                                    </span>
                                                                </div>
                                                                <h4 class="mt-3 lh-base fw-normal text-white"><b>ETH</b> News</h4>
                                                                <p class="text-white-50 font-size-13">Bitcoin prices fell sharply amid the global sell-off in equities. Negative news
                                                                    over the Bitcoin past week has dampened Bitcoin basics
                                                                    sentiment for bitcoin. </p>
                                                                <button type="button" class="btn btn-light btn-sm">View details <i class="mdi mdi-arrow-right ms-1"></i></button>
                                                            </div>
                                                        </div>
                                                        <!-- end carousel-item -->
                                                        <div class="carousel-item">
                                                            <div class="text-center p-4">
                                                                <i class="mdi mdi-litecoin widget-box-1-icon"></i>
                                                                <div class="avatar-md m-auto">
                                                                    <span class="avatar-title rounded-circle bg-light-subtle text-white font-size-24">
                                                                        <i class="mdi mdi-litecoin"></i>
                                                                    </span>
                                                                </div>
                                                                <h4 class="mt-3 lh-base fw-normal text-white"><b>Litecoin</b> News</h4>
                                                                <p class="text-white-50 font-size-13">Bitcoin prices fell sharply amid the global sell-off in equities. Negative news
                                                                    over the Bitcoin past week has dampened Bitcoin basics
                                                                    sentiment for bitcoin. </p>
                                                                <button type="button" class="btn btn-light btn-sm">View details <i class="mdi mdi-arrow-right ms-1"></i></button>
                                                            </div>
                                                        </div>
                                                        <!-- end carousel-item -->
                                                    </div>
                                                    <!-- end carousel-inner -->
                                                    
                                                    <div class="carousel-indicators carousel-indicators-rounded">
                                                        <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="0" class="active"
                                                            aria-current="true" aria-label="Slide 1"></button>
                                                        <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="1" aria-label="Slide 2"></button>
                                                        <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="2" aria-label="Slide 3"></button>
                                                    </div>
                                                    <!-- end carousel-indicators -->
                                                </div>
                                                <!-- end carousel -->
                                            </div>
                                            <!-- end card body -->
                                        </div>
                                        <!-- end card -->
                                    </div>
                                    <!-- end col -->
                                </div>
                                <!-- end row -->
                            </div>
                            <!-- end col -->
                        </div> <!-- end row-->

                        <div class="row">
                            <div class="col-xl-8">
                                <!-- card -->
                                <div class="card">
                                    <!-- card body -->
                                    <div class="card-body">
                                        <div class="d-flex flex-wrap align-items-center mb-4">
                                            <h5 class="card-title me-2">Market Overview</h5>
                                            <div class="ms-auto">
                                                <div>
                                                    <button type="button" class="btn btn-soft-primary btn-sm">
                                                        ALL
                                                    </button>
                                                    <button type="button" class="btn btn-soft-secondary btn-sm">
                                                        1M
                                                    </button>
                                                    <button type="button" class="btn btn-soft-secondary btn-sm">
                                                        6M
                                                    </button>
                                                    <button type="button" class="btn btn-soft-secondary btn-sm">
                                                        1Y
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row align-items-center">
                                            <div class="col-xl-8">
                                                <div>
                                                    <div id="market-overview" data-colors='["#5156be", "#34c38f"]' class="apex-charts"></div>
                                                </div>
                                            </div>
                                            <div class="col-xl-4">
                                                <div class="p-4">
                                                    <div class="mt-0">
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm m-auto">
                                                                <span class="avatar-title rounded-circle bg-light-subtle text-dark font-size-16">
                                                                    1
                                                                </span>
                                                            </div>
                                                            <div class="flex-grow-1 ms-3">
                                                                <span class="font-size-16">Coinmarketcap</span>
                                                            </div>
        
                                                            <div class="flex-shrink-0">
                                                                <span class="badge rounded-pill bg-success-subtle text-success font-size-12 fw-medium">+2.5%</span>
                                                            </div>
                                                        </div>
                                                    </div>
        
                                                    <div class="mt-3">
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm m-auto">
                                                                <span class="avatar-title rounded-circle bg-light-subtle text-dark font-size-16">
                                                                    2
                                                                </span>
                                                            </div>
                                                            <div class="flex-grow-1 ms-3">
                                                                <span class="font-size-16">Binance</span>
                                                            </div>
        
                                                            <div class="flex-shrink-0">
                                                                <span class="badge rounded-pill bg-success-subtle text-success font-size-12 fw-medium">+8.3%</span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mt-3">
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm m-auto">
                                                                <span class="avatar-title rounded-circle bg-light-subtle text-dark font-size-16">
                                                                    3
                                                                </span>
                                                            </div>
                                                            <div class="flex-grow-1 ms-3">
                                                                <span class="font-size-16">Coinbase</span>
                                                            </div>
        
                                                            <div class="flex-shrink-0">
                                                                <span class="badge rounded-pill bg-danger-subtle text-danger font-size-12 fw-medium">-3.6%</span>
                                                            </div>
                                                        </div>
                                                    </div>
        
                                                    <div class="mt-3">
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm m-auto">
                                                                <span class="avatar-title rounded-circle bg-light-subtle text-dark font-size-16">
                                                                    4
                                                                </span>
                                                            </div>
                                                            <div class="flex-grow-1 ms-3">
                                                                <span class="font-size-16">Yobit</span>
                                                            </div>
        
                                                            <div class="flex-shrink-0">
                                                                <span class="badge rounded-pill bg-success-subtle text-success font-size-12 fw-medium">+7.1%</span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mt-3">
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm m-auto">
                                                                <span class="avatar-title rounded-circle bg-light-subtle text-dark font-size-16">
                                                                    5
                                                                </span>
                                                            </div>
                                                            <div class="flex-grow-1 ms-3">
                                                                <span class="font-size-16">Bitfinex</span>
                                                            </div>
        
                                                            <div class="flex-shrink-0">
                                                                <span class="badge rounded-pill bg-danger-subtle text-danger font-size-12 fw-medium">-0.9%</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-4 pt-2">
                                                        <a href="" class="btn btn-primary w-100">See All Balances <i
                                                                class="mdi mdi-arrow-right ms-1"></i></a>
                                                    </div>
        
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- end card -->
                                </div>
                                <!-- end col -->
                            </div>
                            <!-- end row-->
        
                            <div class="col-xl-4">
                                <!-- card -->
                                <div class="card">
                                    <!-- card body -->
                                    <div class="card-body">
                                        <div class="d-flex flex-wrap align-items-center mb-4">
                                            <h5 class="card-title me-2">Sales by Locations</h5>
                                            <div class="ms-auto">
                                                <div class="dropdown">
                                                    <a class="dropdown-toggle text-reset" href="#" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        <span class="text-muted font-size-12">Sort By:</span> <span class="fw-medium">World<i class="mdi mdi-chevron-down ms-1"></i></span>
                                                    </a>

                                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton1">
                                                        <a class="dropdown-item" href="#">USA</a>
                                                        <a class="dropdown-item" href="#">Russia</a>
                                                        <a class="dropdown-item" href="#">Australia</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="sales-by-locations" data-colors='["#5156be"]' style="height: 250px"></div>

                                        <div class="px-2 py-2">
                                            <p class="mb-1">USA <span class="float-end">75%</span></p>
                                            <div class="progress mt-2" style="height: 6px;">
                                                <div class="progress-bar progress-bar-striped bg-primary" role="progressbar"
                                                    style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="75">
                                                </div>
                                            </div>

                                            <p class="mt-3 mb-1">Russia <span class="float-end">55%</span></p>
                                            <div class="progress mt-2" style="height: 6px;">
                                                <div class="progress-bar progress-bar-striped bg-primary" role="progressbar"
                                                    style="width: 55%" aria-valuenow="55" aria-valuemin="0" aria-valuemax="55">
                                                </div>
                                            </div>

                                            <p class="mt-3 mb-1">Australia <span class="float-end">85%</span></p>
                                            <div class="progress mt-2" style="height: 6px;">
                                                <div class="progress-bar progress-bar-striped bg-primary" role="progressbar"
                                                    style="width: 85%" aria-valuenow="85" aria-valuemin="0" aria-valuemax="85">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- end card body -->
                                </div>
                                <!-- end card -->
                            </div>
                            <!-- end col -->
                        </div>
                        <!-- end row-->

                        <div class="row">
                            <div class="col-xl-4">
                                <div class="card">
                                    <div class="card-header align-items-center d-flex">
                                        <h4 class="card-title mb-0 flex-grow-1">Trading</h4>
                                        <div class="flex-shrink-0">
                                            <ul class="nav nav-tabs-custom card-header-tabs" role="tablist">
                                                <li class="nav-item">
                                                    <a class="nav-link active" data-bs-toggle="tab" href="#buy-tab" role="tab">Buy</a>
                                                </li>
                                                <li class="nav-item">
                                                    <a class="nav-link" data-bs-toggle="tab" href="#sell-tab" role="tab">Sell</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div><!-- end card header -->

                                    <div class="card-body">
                                        <div class="tab-content">
                                            <div class="tab-pane active" id="buy-tab" role="tabpanel">
                                                <div class="float-end ms-2">
                                                    <h5 class="font-size-14"><i class="bx bx-wallet text-primary font-size-16 align-middle me-1"></i> <a href="#!" class="text-reset text-decoration-underline">$4335.23</a></h5>
                                                </div>
                                                <h5 class="font-size-14 mb-4">Buy Coins</h5>
                                                <div>
                                                    <div class="form-group mb-3">
                                                        <label>Payment method :</label>
                                                        <select class="form-select">
                                                            <option>Direct Bank Payment</option>
                                                            <option>Credit / Debit Card</option>
                                                            <option>Paypal</option>
                                                            <option>Payoneer</option>
                                                            <option>Stripe</option>
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label>Add Amount :</label>
                                                        <div class="input-group mb-3">
                                                            <label class="input-group-text">Amount</label>
                                                            <select class="form-select" style="max-width: 90px;">
                                                                <option value="BT" selected>BTC</option>
                                                                <option value="ET">ETH</option>
                                                                <option value="LT">LTC</option>
                                                            </select>
                                                            <input type="text" class="form-control" placeholder="0.00121255">
                                                        </div>

                                                        <div class="input-group mb-3">
                                                            <label class="input-group-text">Price</label>
                                                            <input type="text" class="form-control" placeholder="$58,245">
                                                            <label class="input-group-text">$</label>
                                                        </div>

                                                        <div class="input-group mb-3">
                                                            <label class="input-group-text">Total</label>
                                                            <input type="text" class="form-control" placeholder="$36,854.25">
                                                        </div>
                                                    </div>  

                                                    <div class="text-center">
                                                        <button type="button" class="btn btn-success w-md">Buy Coin</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- end tab pane -->
                                            <div class="tab-pane" id="sell-tab" role="tabpanel">
                                                <div class="float-end ms-2">
                                                    <h5 class="font-size-14"><i class="bx bx-wallet text-primary font-size-16 align-middle me-1"></i> <a href="#!" class="text-reset text-decoration-underline">$4235.23</a></h5>
                                                </div>
                                                <h5 class="font-size-14 mb-4">Sell Coins</h5>

                                                <div>

                                                    <div class="form-group mb-3">
                                                        <label>Wallet ID :</label>
                                                        <input type="email" class="form-control" placeholder="1cvb254ugxvfcd280ki">
                                                    </div>

                                                    <div>
                                                        <label>Add Amount :</label>
                                                        <div class="input-group mb-3">
                                                            <label class="input-group-text">Amount</label>
                                                            
                                                            <select class="form-select" style="max-width: 90px;">
                                                                <option value="BT" selected>BTC</option>
                                                                <option value="ET">ETH</option>
                                                                <option value="LT">LTC</option>
                                                            </select>
                                                            <input type="text" class="form-control" placeholder="0.00121255">
                                                        </div>

                                                        <div class="input-group mb-3">
                                                        
                                                            <label class="input-group-text">Price</label>
                                                            
                                                            <input type="text" class="form-control" placeholder="$23,754.25">
                                                            
                                                            <label class="input-group-text">$</label>
                                                        </div>

                                                        <div class="input-group mb-3">
                                                            <label class="input-group-text">Total</label>
                                                            <input type="text" class="form-control" placeholder="$6,852.41">
                                                        </div>
                                                    </div>  

                                                    <div class="text-center">
                                                        <button type="button" class="btn btn-danger w-md">Sell Coin</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- end tab pane -->
                                        </div>
                                        <!-- end tab content -->
                                    </div>
                                    <!-- end card body -->
                                </div>
                                <!-- end card -->
                            </div>
                            <!-- end col -->
                            
                            <div class="col-xl-4">
                                <div class="card">
                                    <div class="card-header align-items-center d-flex">
                                        <h4 class="card-title mb-0 flex-grow-1">Transactions</h4>
                                        <div class="flex-shrink-0">
                                            <ul class="nav justify-content-end nav-tabs-custom rounded card-header-tabs" role="tablist">
                                                <li class="nav-item">
                                                    <a class="nav-link active" data-bs-toggle="tab" href="#transactions-all-tab" role="tab">
                                                        All 
                                                    </a>
                                                </li>
                                                <li class="nav-item">
                                                    <a class="nav-link" data-bs-toggle="tab" href="#transactions-buy-tab" role="tab">
                                                        Buy 
                                                    </a>
                                                </li>
                                                <li class="nav-item">
                                                    <a class="nav-link" data-bs-toggle="tab" href="#transactions-sell-tab" role="tab">
                                                        Sell  
                                                    </a>
                                                </li>
                                            </ul>
                                            <!-- end nav tabs -->
                                        </div>
                                    </div><!-- end card header -->

                                    <div class="card-body px-0">
                                        <div class="tab-content">
                                            <div class="tab-pane active" id="transactions-all-tab" role="tabpanel">
                                                <div class="table-responsive px-3" data-simplebar style="max-height: 352px;">
                                                    <table class="table align-middle table-nowrap table-borderless">
                                                        <tbody>
                                                            <tr>
                                                                <td style="width: 50px;">
                                                                    <div class="font-size-22 text-success">
                                                                        <i class="bx bx-down-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Buy BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">14 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.016 BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$125.20</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td>
                                                                    <div class="font-size-22 text-danger">
                                                                        <i class="bx bx-up-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Sell ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">15 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.56 ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$112.34</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td>
                                                                    <div class="font-size-22 text-success">
                                                                        <i class="bx bx-down-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Buy LTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">16 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">1.88 LTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$94.22</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td>
                                                                    <div class="font-size-22 text-success">
                                                                        <i class="bx bx-down-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Buy ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">17 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.42 ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$84.32</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td>
                                                                    <div class="font-size-22 text-danger">
                                                                        <i class="bx bx-up-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Sell BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">18 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.018 BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$145.80</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td style="width: 50px;">
                                                                    <div class="font-size-22 text-success">
                                                                        <i class="bx bx-down-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Buy BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">14 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.016 BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$125.20</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td>
                                                                    <div class="font-size-22 text-danger">
                                                                        <i class="bx bx-up-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Sell ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">15 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.56 ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$112.34</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <!-- end tab pane -->
                                            <div class="tab-pane" id="transactions-buy-tab" role="tabpanel">
                                                <div class="table-responsive px-3" data-simplebar style="max-height: 352px;">
                                                    <table class="table align-middle table-nowrap table-borderless">
                                                        <tbody>
                                                            <tr>
                                                                <td style="width: 50px;">
                                                                    <div class="font-size-22 text-success">
                                                                        <i class="bx bx-down-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Buy BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">14 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.016 BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$125.20</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                             <tr>
                                                                <td>
                                                                    <div class="font-size-22 text-success">
                                                                        <i class="bx bx-down-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Buy BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">18 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.018 BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$145.80</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td>
                                                                    <div class="font-size-22 text-success">
                                                                        <i class="bx bx-down-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Buy LTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">16 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">1.88 LTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$94.22</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td>
                                                                    <div class="font-size-22 text-success">
                                                                        <i class="bx bx-down-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Buy ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">15 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.56 ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$112.34</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td>
                                                                    <div class="font-size-22 text-success">
                                                                        <i class="bx bx-down-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Buy ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">17 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.42 ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$84.32</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                           
                                                            <tr>
                                                                <td>
                                                                    <div class="font-size-22 text-success">
                                                                        <i class="bx bx-down-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Buy ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">15 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.56 ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$112.34</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td style="width: 50px;">
                                                                    <div class="font-size-22 text-success">
                                                                        <i class="bx bx-down-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Buy BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">14 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.016 BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$125.20</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <!-- end tab pane -->
                                            <div class="tab-pane" id="transactions-sell-tab" role="tabpanel">
                                                <div class="table-responsive px-3" data-simplebar style="max-height: 352px;">
                                                    <table class="table align-middle table-nowrap table-borderless">
                                                        <tbody>
                                                            <tr>
                                                                <td>
                                                                    <div class="font-size-22 text-danger">
                                                                        <i class="bx bx-up-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Sell ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">15 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.56 ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$112.34</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td style="width: 50px;">
                                                                    <div class="font-size-22 text-danger">
                                                                        <i class="bx bx-up-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Sell BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">14 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.016 BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$125.20</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td>
                                                                    <div class="font-size-22 text-danger">
                                                                        <i class="bx bx-up-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Sell BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">18 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.018 BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$145.80</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td>
                                                                    <div class="font-size-22 text-danger">
                                                                        <i class="bx bx-up-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Sell ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">15 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.56 ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$112.34</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td>
                                                                    <div class="font-size-22 text-danger">
                                                                        <i class="bx bx-up-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Sell LTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">16 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">1.88 LTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$94.22</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td>
                                                                    <div class="font-size-22 text-danger">
                                                                        <i class="bx bx-up-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Sell ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">17 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.42 ETH</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$84.32</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            

                                                            <tr>
                                                                <td style="width: 50px;">
                                                                    <div class="font-size-22 text-danger">
                                                                        <i class="bx bx-up-arrow-circle d-block"></i>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1">Sell BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">14 Mar, 2021</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 mb-0">0.016 BTC</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Coin Value</p>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="text-end">
                                                                        <h5 class="font-size-14 text-muted mb-0">$125.20</h5>
                                                                        <p class="text-muted mb-0 font-size-12">Amount</p>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <!-- end tab pane -->
                                        </div>
                                        <!-- end tab content -->
                                    </div>
                                    <!-- end card body -->
                                </div>
                                <!-- end card -->
                            </div>
                            <!-- end col -->

                            <div class="col-xl-4">
                                <div class="card">
                                    <div class="card-header align-items-center d-flex">
                                        <h4 class="card-title mb-0 flex-grow-1">Recent Activity</h4>
                                        <div class="flex-shrink-0">
                                            <select class="form-select form-select-sm mb-0 my-n1">
                                                <option value="Today" selected="">Today</option>
                                                <option value="Yesterday">Yesterday</option>
                                                <option value="Week">Last Week</option>
                                                <option value="Month">Last Month</option>
                                            </select>
                                        </div>
                                    </div><!-- end card header -->

                                    <div class="card-body px-0">
                                        <div class="px-3" data-simplebar style="max-height: 352px;">
                                            <ul class="list-unstyled activity-wid mb-0">

                                                <li class="activity-list activity-border">
                                                    <div class="activity-icon avatar-md">
                                                        <span class="avatar-title bg-warning-subtle text-warning rounded-circle">
                                                        <i class="bx bx-bitcoin font-size-24"></i>
                                                        </span>
                                                    </div>
                                                    <div class="timeline-list-item">
                                                        <div class="d-flex">
                                                            <div class="flex-grow-1 overflow-hidden me-4">
                                                                <h5 class="font-size-14 mb-1">24/05/2021, 18:24:56</h5>
                                                                <p class="text-truncate text-muted font-size-13">0xb77ad0099e21d4fca87fa4ca92dda1a40af9e05d205e53f38bf026196fa2e431</p>
                                                            </div>
                                                            <div class="flex-shrink-0 text-end me-3">
                                                                <h6 class="mb-1">+0.5 BTC</h6>
                                                                <div class="font-size-13">$178.53</div>
                                                            </div>

                                                            <div class="flex-shrink-0 text-end">
                                                                <div class="dropdown">
                                                                    <a class="text-muted dropdown-toggle font-size-24" role="button" data-bs-toggle="dropdown" aria-haspopup="true">
                                                                        <i class="mdi mdi-dots-vertical"></i>
                                                                    </a>
                
                                                                    <div class="dropdown-menu dropdown-menu-end">
                                                                        <a class="dropdown-item" href="#">Action</a>
                                                                        <a class="dropdown-item" href="#">Another action</a>
                                                                        <a class="dropdown-item" href="#">Something else here</a>
                                                                        <div class="dropdown-divider"></div>
                                                                        <a class="dropdown-item" href="#">Separated link</a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div> 
                                                </li>

                                                <li class="activity-list activity-border">
                                                    <div class="activity-icon avatar-md">
                                                        <span class="avatar-title  bg-primary-subtle text-primary rounded-circle">
                                                        <i class="mdi mdi-ethereum font-size-24"></i>
                                                        </span>
                                                    </div>
                                                    <div class="timeline-list-item">
                                                        <div class="d-flex">
                                                            <div class="flex-grow-1 overflow-hidden me-4">
                                                                <h5 class="font-size-14 mb-1">24/05/2021, 18:24:56</h5>
                                                                <p class="text-truncate text-muted font-size-13">0xb77ad0099e21d4fca87fa4ca92dda1a40af9e05d205e53f38bf026196fa2e431</p>
                                                            </div>
                                                            <div class="flex-shrink-0 text-end me-3">
                                                                <h6 class="mb-1">-20.5 ETH</h6>
                                                                <div class="font-size-13">$3541.45</div>
                                                            </div>

                                                            <div class="flex-shrink-0 text-end">
                                                                <div class="dropdown">
                                                                    <a class="text-muted dropdown-toggle font-size-24" role="button" data-bs-toggle="dropdown" aria-haspopup="true">
                                                                        <i class="mdi mdi-dots-vertical"></i>
                                                                    </a>
                
                                                                    <div class="dropdown-menu dropdown-menu-end">
                                                                        <a class="dropdown-item" href="#">Action</a>
                                                                        <a class="dropdown-item" href="#">Another action</a>
                                                                        <a class="dropdown-item" href="#">Something else here</a>
                                                                        <div class="dropdown-divider"></div>
                                                                        <a class="dropdown-item" href="#">Separated link</a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div> 
                                                </li>

                                                <li class="activity-list activity-border">
                                                    <div class="activity-icon avatar-md">
                                                        <span class="avatar-title bg-warning-subtle text-warning rounded-circle">
                                                        <i class="bx bx-bitcoin font-size-24"></i>
                                                        </span>
                                                    </div>
                                                    <div class="timeline-list-item">
                                                        <div class="d-flex">
                                                            <div class="flex-grow-1 overflow-hidden me-4">
                                                                <h5 class="font-size-14 mb-1">24/05/2021, 18:24:56</h5>
                                                                <p class="text-truncate text-muted font-size-13">0xb77ad0099e21d4fca87fa4ca92dda1a40af9e05d205e53f38bf026196fa2e431</p>
                                                            </div>
                                                            <div class="flex-shrink-0 text-end me-3">
                                                                <h6 class="mb-1">+0.5 BTC</h6>
                                                                <div class="font-size-13">$5791.45</div>
                                                            </div>

                                                            <div class="flex-shrink-0 text-end">
                                                                <div class="dropdown">
                                                                    <a class="text-muted dropdown-toggle font-size-24" role="button" data-bs-toggle="dropdown" aria-haspopup="true">
                                                                        <i class="mdi mdi-dots-vertical"></i>
                                                                    </a>
                
                                                                    <div class="dropdown-menu dropdown-menu-end">
                                                                        <a class="dropdown-item" href="#">Action</a>
                                                                        <a class="dropdown-item" href="#">Another action</a>
                                                                        <a class="dropdown-item" href="#">Something else here</a>
                                                                        <div class="dropdown-divider"></div>
                                                                        <a class="dropdown-item" href="#">Separated link</a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div> 
                                                </li>
            
                                                <li class="activity-list activity-border">
                                                    <div class="activity-icon avatar-md">
                                                        <span class="avatar-title  bg-primary-subtle text-primary rounded-circle">
                                                        <i class="mdi mdi-litecoin font-size-24"></i>
                                                        </span>
                                                    </div>
                                                    <div class="timeline-list-item">
                                                        <div class="d-flex">
                                                            <div class="flex-grow-1 overflow-hidden me-4">
                                                                <h5 class="font-size-14 mb-1">24/05/2021, 18:24:56</h5>
                                                                <p class="text-truncate text-muted font-size-13">0xb77ad0099e21d4fca87fa4ca92dda1a40af9e05d205e53f38bf026196fa2e431</p>
                                                            </div>
                                                            <div class="flex-shrink-0 text-end me-3">
                                                                <h6 class="mb-1">-1.5 LTC</h6>
                                                                <div class="font-size-13">$5791.45</div>
                                                            </div>

                                                            <div class="flex-shrink-0 text-end">
                                                                <div class="dropdown">
                                                                    <a class="text-muted dropdown-toggle font-size-24" role="button" data-bs-toggle="dropdown" aria-haspopup="true">
                                                                        <i class="mdi mdi-dots-vertical"></i>
                                                                    </a>
                
                                                                    <div class="dropdown-menu dropdown-menu-end">
                                                                        <a class="dropdown-item" href="#">Action</a>
                                                                        <a class="dropdown-item" href="#">Another action</a>
                                                                        <a class="dropdown-item" href="#">Something else here</a>
                                                                        <div class="dropdown-divider"></div>
                                                                        <a class="dropdown-item" href="#">Separated link</a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div> 
                                                </li>


                                                <li class="activity-list activity-border">
                                                    <div class="activity-icon avatar-md">
                                                        <span class="avatar-title bg-warning-subtle text-warning rounded-circle">
                                                        <i class="bx bx-bitcoin font-size-24"></i>
                                                        </span>
                                                    </div>
                                                    <div class="timeline-list-item">
                                                        <div class="d-flex">
                                                            <div class="flex-grow-1 overflow-hidden me-4">
                                                                <h5 class="font-size-14 mb-1">24/05/2021, 18:24:56</h5>
                                                                <p class="text-truncate text-muted font-size-13">0xb77ad0099e21d4fca87fa4ca92dda1a40af9e05d205e53f38bf026196fa2e431</p>
                                                            </div>
                                                            <div class="flex-shrink-0 text-end me-3">
                                                                <h6 class="mb-1">+0.5 BTC</h6>
                                                                <div class="font-size-13">$5791.45</div>
                                                            </div>

                                                            <div class="flex-shrink-0 text-end">
                                                                <div class="dropdown">
                                                                    <a class="text-muted dropdown-toggle font-size-24" role="button" data-bs-toggle="dropdown" aria-haspopup="true">
                                                                        <i class="mdi mdi-dots-vertical"></i>
                                                                    </a>
                
                                                                    <div class="dropdown-menu dropdown-menu-end">
                                                                        <a class="dropdown-item" href="#">Action</a>
                                                                        <a class="dropdown-item" href="#">Another action</a>
                                                                        <a class="dropdown-item" href="#">Something else here</a>
                                                                        <div class="dropdown-divider"></div>
                                                                        <a class="dropdown-item" href="#">Separated link</a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div> 
                                                </li>

                                                <li class="activity-list">
                                                    <div class="activity-icon avatar-md">
                                                        <span class="avatar-title  bg-primary-subtle text-primary rounded-circle">
                                                        <i class="mdi mdi-litecoin font-size-24"></i>
                                                        </span>
                                                    </div>
                                                    <div class="timeline-list-item">
                                                        <div class="d-flex">
                                                            <div class="flex-grow-1 overflow-hidden me-4">
                                                                <h5 class="font-size-14 mb-1">24/05/2021, 18:24:56</h5>
                                                                <p class="text-truncate text-muted font-size-13">0xb77ad0099e21d4fca87fa4ca92dda1a40af9e05d205e53f38bf026196fa2e431</p>
                                                            </div>
                                                            <div class="flex-shrink-0 text-end me-3">
                                                                <h6 class="mb-1">+.55 LTC</h6>
                                                                <div class="font-size-13">$91.45</div>
                                                            </div>

                                                            <div class="flex-shrink-0 text-end">
                                                                <div class="dropdown">
                                                                    <a class="text-muted dropdown-toggle font-size-24" role="button" data-bs-toggle="dropdown" aria-haspopup="true">
                                                                        <i class="mdi mdi-dots-vertical"></i>
                                                                    </a>
                
                                                                    <div class="dropdown-menu dropdown-menu-end">
                                                                        <a class="dropdown-item" href="#">Action</a>
                                                                        <a class="dropdown-item" href="#">Another action</a>
                                                                        <a class="dropdown-item" href="#">Something else here</a>
                                                                        <div class="dropdown-divider"></div>
                                                                        <a class="dropdown-item" href="#">Separated link</a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div> 
                                                </li>
                                            </ul>
                                        </div>    
                                    </div>
                                    <!-- end card body -->
                                </div>
                                <!-- end card -->
                            </div>
                            <!-- end col -->
                        </div><!-- end row -->
                    </div>
                    <!-- container-fluid -->
                </div>
                <!-- End Page-content -->


                <footer class="footer">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-sm-6">
                                2024 © Minia.
                            </div>
                            <div class="col-sm-6">
                                <div class="text-sm-end d-none d-sm-block">
                                    Design & Develop by <a href="#!" class="text-decoration-underline">Themesbrand</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
            <!-- end main content-->

        </div>
        <!-- END layout-wrapper -->

        
        <!-- Right Sidebar -->
        <div class="right-bar">
            <div data-simplebar class="h-100">
                <div class="rightbar-title d-flex align-items-center p-3">

                    <h5 class="m-0 me-2">Theme Customizer</h5>

                    <a href="javascript:void(0);" class="right-bar-toggle ms-auto">
                        <i class="mdi mdi-close noti-icon"></i>
                    </a>
                </div>

                <!-- Settings -->
                <hr class="m-0" />

                <div class="p-4">
                    <h6 class="mb-3">Layout</h6>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="layout"
                            id="layout-vertical" value="vertical">
                        <label class="form-check-label" for="layout-vertical">Vertical</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="layout"
                            id="layout-horizontal" value="horizontal">
                        <label class="form-check-label" for="layout-horizontal">Horizontal</label>
                    </div>

                    <h6 class="mt-4 mb-3 pt-2">Layout Mode</h6>

                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="layout-mode"
                            id="layout-mode-light" value="light">
                        <label class="form-check-label" for="layout-mode-light">Light</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="layout-mode"
                            id="layout-mode-dark" value="dark">
                        <label class="form-check-label" for="layout-mode-dark">Dark</label>
                    </div>

                    <h6 class="mt-4 mb-3 pt-2">Layout Width</h6>

                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="layout-width"
                            id="layout-width-fuild" value="fuild" onchange="document.body.setAttribute('data-layout-size', 'fluid')">
                        <label class="form-check-label" for="layout-width-fuild">Fluid</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="layout-width"
                            id="layout-width-boxed" value="boxed" onchange="document.body.setAttribute('data-layout-size', 'boxed')">
                        <label class="form-check-label" for="layout-width-boxed">Boxed</label>
                    </div>

                    <h6 class="mt-4 mb-3 pt-2">Layout Position</h6>

                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="layout-position"
                            id="layout-position-fixed" value="fixed" onchange="document.body.setAttribute('data-layout-scrollable', 'false')">
                        <label class="form-check-label" for="layout-position-fixed">Fixed</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="layout-position"
                            id="layout-position-scrollable" value="scrollable" onchange="document.body.setAttribute('data-layout-scrollable', 'true')">
                        <label class="form-check-label" for="layout-position-scrollable">Scrollable</label>
                    </div>

                    <h6 class="mt-4 mb-3 pt-2">Topbar Color</h6>

                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="topbar-color"
                            id="topbar-color-light" value="light" onchange="document.body.setAttribute('data-topbar', 'light')">
                        <label class="form-check-label" for="topbar-color-light">Light</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="topbar-color"
                            id="topbar-color-dark" value="dark" onchange="document.body.setAttribute('data-topbar', 'dark')">
                        <label class="form-check-label" for="topbar-color-dark">Dark</label>
                    </div>

                    <h6 class="mt-4 mb-3 pt-2 sidebar-setting">Sidebar Size</h6>

                    <div class="form-check sidebar-setting">
                        <input class="form-check-input" type="radio" name="sidebar-size"
                            id="sidebar-size-default" value="default" onchange="document.body.setAttribute('data-sidebar-size', 'lg')">
                        <label class="form-check-label" for="sidebar-size-default">Default</label>
                    </div>
                    <div class="form-check sidebar-setting">
                        <input class="form-check-input" type="radio" name="sidebar-size"
                            id="sidebar-size-compact" value="compact" onchange="document.body.setAttribute('data-sidebar-size', 'md')">
                        <label class="form-check-label" for="sidebar-size-compact">Compact</label>
                    </div>
                    <div class="form-check sidebar-setting">
                        <input class="form-check-input" type="radio" name="sidebar-size"
                            id="sidebar-size-small" value="small" onchange="document.body.setAttribute('data-sidebar-size', 'sm')">
                        <label class="form-check-label" for="sidebar-size-small">Small (Icon View)</label>
                    </div>

                    <h6 class="mt-4 mb-3 pt-2 sidebar-setting">Sidebar Color</h6>

                    <div class="form-check sidebar-setting">
                        <input class="form-check-input" type="radio" name="sidebar-color"
                            id="sidebar-color-light" value="light" onchange="document.body.setAttribute('data-sidebar', 'light')">
                        <label class="form-check-label" for="sidebar-color-light">Light</label>
                    </div>
                    <div class="form-check sidebar-setting">
                        <input class="form-check-input" type="radio" name="sidebar-color"
                            id="sidebar-color-dark" value="dark" onchange="document.body.setAttribute('data-sidebar', 'dark')">
                        <label class="form-check-label" for="sidebar-color-dark">Dark</label>
                    </div>
                    <div class="form-check sidebar-setting">
                        <input class="form-check-input" type="radio" name="sidebar-color"
                            id="sidebar-color-brand" value="brand" onchange="document.body.setAttribute('data-sidebar', 'brand')">
                        <label class="form-check-label" for="sidebar-color-brand">Brand</label>
                    </div>

                    <h6 class="mt-4 mb-3 pt-2">Direction</h6>

                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="layout-direction"
                            id="layout-direction-ltr" value="ltr">
                        <label class="form-check-label" for="layout-direction-ltr">LTR</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="layout-direction"
                            id="layout-direction-rtl" value="rtl">
                        <label class="form-check-label" for="layout-direction-rtl">RTL</label>
                    </div>

                </div>

            </div> <!-- end slimscroll-menu-->
        </div>
        <!-- /Right-bar -->

        <!-- Right bar overlay-->
        <div class="rightbar-overlay"></div>

        <!-- JAVASCRIPT -->
        <script src="assets/libs/jquery/jquery.min.js"></script>
        <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="assets/libs/metismenu/metisMenu.min.js"></script>
        <script src="assets/libs/simplebar/simplebar.min.js"></script>
        <script src="assets/libs/node-waves/waves.min.js"></script>
        <script src="assets/libs/feather-icons/feather.min.js"></script>
        <!-- pace js -->
        <script src="assets/libs/pace-js/pace.min.js"></script>

        <!-- apexcharts -->
        <script src="assets/libs/apexcharts/apexcharts.min.js"></script>

        <!-- Plugins js-->
        <script src="assets/libs/admin-resources/jquery.vectormap/jquery-jvectormap-1.2.2.min.js"></script>
        <script src="assets/libs/admin-resources/jquery.vectormap/maps/jquery-jvectormap-world-mill-en.js"></script>
        <!-- dashboard init -->
        <script src="js/dashboard.js"></script>
        <script src="/apps/common/js/sweetalert2.min.js"></script>
        <script src="/apps/common/js/phpcoin-crypto.js" type="text/javascript"></script>

        <script src="assets/js/app.js"></script>


        <script type="module" src="js/wallet.js"></script>
    </body>

</html>