<?php if(!defined("INCLUDED")) {require_once __DIR__  ."/template.php";exit;} ?>
<?php
global $db;
$address = $_SESSION['currentAddress'];
$balance=Account::pendingBalance($address);
$addressStat = Transaction::getAddressStat($address);

$res = file_get_contents("https://main1.phpcoin.net/dapps.php?url=PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3/api.php?q=coinInfo");
$res = json_decode($res, true);
$btcPrice = $res['btcPrice'];
$usdPrice = $res['usdPrice'];

$addressTypes = Block::getAddressTypes($address);

include __DIR__.'/inc/phpqrcode.php';
?>
<div class="p-3">

    <div class="container-fluid">

        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0 font-size-18">Dashboard <?php echo $address ?></h4>

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
                            <div class="col">
                                <span class="text-muted mb-3 lh-1 d-block text-truncate">Balance</span>
                                <h4 class="mb-3">
                                    <span><?php echo $balance ?></span>
                                </h4>
                            </div>
                        </div>
                        <div class="text-nowrap">
                            <span class="ms-1 text-muted font-size-13"><?php echo num($balance * $usdPrice,2) ?> $</span>
                        </div>
                    </div><!-- end card body -->
                </div><!-- end card -->
            </div><!-- end col -->

            <div class="col-xl-4 col-md-6">
                <!-- card -->
                <div class="card card-h-100">
                    <!-- card body -->
                    <div class="card-body">
                        <div class="row">
                            <div class="col-4">
                                <span class="text-muted mb-3 lh-1 d-block text-truncate">Transactions</span>
                                <h4 class="mb-3">
                                    <span><?php echo $addressStat['count'] ?></span>
                                </h4>
                            </div>
                            <div class="col-8">
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
                                                <h5 class="font-size-14 mb-1">Received</h5>
                                                <p class="text-muted mb-0 font-size-12">
                                                    <?php echo $addressStat['count_received'] ?>
                                                </p>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="text-end">
                                                <h5 class="font-size-14 mb-1"><?php echo $addressStat['total_received'] ?></h5>
                                                <p class="text-muted mb-0 font-size-12"><?php echo num($addressStat['total_received'] * $usdPrice, 2) ?>$</p>
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
                                                <h5 class="font-size-14 mb-1">Sent</h5>
                                                <p class="text-muted mb-0 font-size-12">
                                                    <?php echo $addressStat['count_sent'] ?>
                                                </p>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="text-end">
                                                <h5 class="font-size-14 mb-0"><?php echo $addressStat['total_sent'] ?></h5>
                                                <p class="text-muted mb-0 font-size-12"><?php echo num($addressStat['total_sent'] * $usdPrice, 2) ?>$</p>
                                            </div>
                                        </td>

                                    </tr>

                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="text-nowrap">
                        </div>
                    </div><!-- end card body -->
                </div><!-- end card -->
            </div><!-- end col-->

            <?php if ($addressTypes['is_generator']) {
                $txStat = Transaction::getTxStatByType($address, "generator");
                $rewardStat = Transaction::getRewardsStat($address, "generator");
                ?>
                <div class="col-xl-3 col-md-6">
                    <!-- card -->
                    <div class="card card-h-100">
                        <!-- card body -->
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <span class="text-muted mb-3 lh-1 d-block text-truncate">Generator</span>
                                    <h4 class="mb-3">
                                        <span><?php echo knum($txStat['total']) ?></span>
                                    </h4>
                                </div>
                            </div>
                            <div class="text-nowrap">
                                <span class="badge bg-success-subtle text-success">+ <?php echo knum($rewardStat['total']['weekly']) ?></span>
                                <span class="ms-1 text-muted font-size-13">Since last week</span>
                            </div>
                        </div><!-- end card body -->
                    </div><!-- end card -->
                </div><!-- end col -->
            <?php } ?>

            <?php if ($addressTypes['is_miner']) {
                $txStat = Transaction::getTxStatByType($address, "miner");
                $rewardStat = Transaction::getRewardsStat($address, "miner");
                ?>
                <div class="col-xl-3 col-md-6">
                    <!-- card -->
                    <div class="card card-h-100">
                        <!-- card body -->
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <span class="text-muted mb-3 lh-1 d-block text-truncate">Miner</span>
                                    <h4 class="mb-3">
                                        <span><?php echo knum($txStat['total']) ?></span>
                                    </h4>
                                </div>
                            </div>
                            <div class="text-nowrap">
                                <span class="badge bg-success-subtle text-success">+ <?php echo knum($rewardStat['total']['weekly']) ?></span>
                                <span class="ms-1 text-muted font-size-13">Since last week</span>
                            </div>
                        </div><!-- end card body -->
                    </div><!-- end card -->
                </div><!-- end col -->
            <?php } ?>

            <?php if ($addressTypes['is_masternode']) {
                $txStat = Transaction::getTxStatByType($address, "masternode");
                $rewardStat = Transaction::getRewardsStat($address, "masternode");
                ?>
                <div class="col-xl-3 col-md-6">
                    <!-- card -->
                    <div class="card card-h-100">
                        <!-- card body -->
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <span class="text-muted mb-3 lh-1 d-block text-truncate">Masternode</span>
                                    <h4 class="mb-3">
                                        <span><?php echo knum($txStat['total']) ?></span>
                                    </h4>
                                </div>
                            </div>
                            <div class="text-nowrap">
                                <span class="badge bg-success-subtle text-success">+ <?php echo knum($rewardStat['total']['weekly']) ?></span>
                                <span class="ms-1 text-muted font-size-13">Since last week</span>
                            </div>
                        </div><!-- end card body -->
                    </div><!-- end card -->
                </div><!-- end col -->
            <?php } ?>

            <?php if ($addressTypes['is_stake']) {
                $sql="select sum(t.val) as total
                    from transactions t
                    where t.type = 0 and
                        t.message = 'stake' and t.dst = ?";
                $row=$db->row($sql, [$address], false);
                $total = $row['total'];
                $sql="select sum(t.val) as lastweek
                from transactions t
                where t.type = 0 and
                    t.message = 'stake' and t.dst = ?
                  and t.date > unix_timestamp() - 60*60*24*7;";
                $row=$db->row($sql, [$address], false);
                $lastweek = $row['lastweek'];
                ?>
                <div class="col-xl-3 col-md-6">
                    <!-- card -->
                    <div class="card card-h-100">
                        <!-- card body -->
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-6">
                                    <span class="text-muted mb-3 lh-1 d-block text-truncate">Staking</span>
                                    <h4 class="mb-3">
                                        <span><?php echo knum($total) ?></span>
                                    </h4>
                                </div>
                            </div>
                            <div class="text-nowrap">
                                <span class="badge bg-success-subtle text-success">+ <?php echo knum($lastweek) ?></span>
                                <span class="ms-1 text-muted font-size-13">Since last week</span>
                            </div>
                        </div><!-- end card body -->
                    </div><!-- end card -->
                </div><!-- end col -->
            <?php } ?>
        </div><!-- end row-->


        <div class="row">

            <div class="col-xl-3">
                <div class="card">
                    <div class="card-header align-items-center d-flex">
                        <h4 class="card-title mb-0 flex-grow-1">Transfer</h4>
                        <div class="flex-shrink-0">
                            <ul class="nav nav-tabs-custom card-header-tabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link active" data-bs-toggle="tab" href="#buy-tab" role="tab" aria-selected="false" tabindex="-1">Send</a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link" data-bs-toggle="tab" href="#sell-tab" role="tab" aria-selected="true">Receive</a>
                                </li>
                            </ul>
                        </div>
                    </div><!-- end card header -->

                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane active show" id="buy-tab" role="tabpanel">
                                <div class="float-end ms-2">
                                    <h5 class="font-size-14"><i class="bx bx-wallet text-primary font-size-16 align-middle me-1"></i>
                                        <a href="#!" class="text-reset text-decoration-underline">
                                            <?php echo $balance ?>
                                        </a></h5>
                                </div>
                                <h5 class="font-size-14 mb-4">Available</h5>
                                <div>
                                    <div class="form-group mb-3">
                                        <label>Receiver:</label>
                                        <div class="input-group mb-3">
                                            <input type="text" class="form-control" placeholder="Address">
                                            <label class="input-group-text">
                                                <i class="fas fa-address-book"></i>
                                            </label>
                                        </div>
                                    </div>

                                    <div>
                                        <label>Amount:</label>
                                        <div class="input-group mb-3">
                                            <label class="input-group-text">Amount</label>
                                            <input type="text" class="form-control">
                                            <label class="input-group-text">Fee</label>
                                            <input type="text" class="form-control">
                                        </div>

                                        <div class="input-group mb-3">
                                            <label>Message:</label>
                                            <div class="input-group mb-3">
                                                <input type="text" class="form-control" placeholder="Optional">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-center">
                                        <button type="button" class="btn btn-success w-md">Send</button>
                                    </div>
                                </div>
                            </div>
                            <!-- end tab pane -->
                            <div class="tab-pane" id="sell-tab" role="tabpanel">

                                <div>

                                    <div class="form-group mb-3">
                                        <label>Address:</label>
                                        <div class="input-group mb-3">
                                            <input type="text" class="form-control" placeholder="1cvb254ugxvfcd280ki" value="<?php echo $address ?>">
                                            <label class="input-group-text">
                                                <i class="fas fa-copy"></i>
                                            </label>
                                        </div>
                                    </div>

                                    <div>
                                        <label>QR code:</label>

                                        <div>
                                            <img src="/apps/wallet2/qrcode.php?address=<?php echo $address ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- end tab pane -->
                        </div>
                        <!-- end tab content -->
                    </div>
                    <!-- end card body -->
                </div>
            </div>

        </div>

        <div class="row">

            <div class="col-xl-5">
                <!-- card -->
                <div class="card card-h-100">
                    <!-- card body -->
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center mb-4">
                            <h5 class="card-title me-2">Wallet Balance</h5>
                            <div class="ms-auto">
                            </div>
                        </div>

                        <div class="row align-items-center">
                            <div class="col-sm">
                                Chart with amounts: transfers, staking, rewards ...
                            </div>
                            <div class="col-sm align-self-center">
                                <div class="mt-4 mt-sm-0">
                                    <div>
                                        <p class="mb-2"><i class="mdi mdi-circle align-middle font-size-10 me-2 text-success"></i> Transfers</p>
                                        <h6>0.4412 BTC = <span class="text-muted font-size-14 fw-normal">$ 4025.32</span></h6>
                                    </div>

                                    <div class="mt-4 pt-2">
                                        <p class="mb-2"><i class="mdi mdi-circle align-middle font-size-10 me-2 text-primary"></i> Rewards</p>
                                        <h6>4.5701 ETH = <span class="text-muted font-size-14 fw-normal">$ 1123.64</span></h6>
                                    </div>

                                    <div class="mt-4 pt-2">
                                        <p class="mb-2"><i class="mdi mdi-circle align-middle font-size-10 me-2 text-info"></i> Staking</p>
                                        <h6>35.3811 LTC = <span class="text-muted font-size-14 fw-normal">$ 2263.09</span></h6>
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
                        <div class="card card-h-100">
                            <!-- card body -->
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-center mb-4">
                                    <h5 class="card-title me-2">Projected earnings</h5>
                                    <div class="ms-auto">
                                        <div>
                                            <button type="button" class="btn btn-soft-secondary btn-sm">
                                                D
                                            </button>
                                            <button type="button" class="btn btn-soft-primary btn-sm">
                                                W
                                            </button>
                                            <button type="button" class="btn btn-soft-secondary btn-sm">
                                                M
                                            </button>
                                            <button type="button" class="btn btn-soft-secondary btn-sm">
                                                Y
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="row align-items-center">
                                    <div class="col-sm">

                                    </div>
                                    <div class="col-sm align-self-center">
                                        <div class="mt-4 mt-sm-0">
                                            <p class="mb-1">Roi</p>
                                            <h4>$ 6134.39</h4>

                                            <p class="text-muted mb-4"> + 0.0012.23 ( 0.2 % ) <i class="mdi mdi-arrow-up ms-1 text-success"></i></p>

                                            <div class="row g-0">
                                                <div class="col-6">
                                                    <div>
                                                        <p class="mb-2 text-muted text-uppercase font-size-11">Income</p>
                                                        <h5 class="fw-medium">$ 2632.46</h5>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div>
                                                        <p class="mb-2 text-muted text-uppercase font-size-11">Expenses</p>
                                                        <h5 class="fw-medium">-$ 924.38</h5>
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
                                        <div class="carousel-item">
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
                                        <div class="carousel-item active">
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
                                        <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="0" class="" aria-label="Slide 1"></button>
                                        <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="1" aria-label="Slide 2" class="active" aria-current="true"></button>
                                        <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="2" aria-label="Slide 3" class=""></button>
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
            <div class="col-xl-6">
                <!-- card -->
                <div class="card">
                    <!-- card body -->
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center mb-4">
                            <h5 class="card-title me-2">Balance Overview</h5>
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
                                <div style="position: relative;">

                                    Chart showing balance chnages in timeline
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
                                        <a href="" class="btn btn-primary w-100">See All Balances <i class="mdi mdi-arrow-right ms-1"></i></a>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end card -->
                </div>
                <!-- end col -->


            </div>
            <div class="col-xl-6">
                <!-- card -->
                <!-- end col -->

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
                        <div class="px-3" data-simplebar="init" style="max-height: 352px;"><div class="simplebar-wrapper" style="margin: 0px -16px;"><div class="simplebar-height-auto-observer-wrapper"><div class="simplebar-height-auto-observer"></div></div><div class="simplebar-mask"><div class="simplebar-offset" style="right: -20px; bottom: 0px;"><div class="simplebar-content-wrapper" style="height: auto; padding-right: 20px; padding-bottom: 0px; overflow: hidden scroll;"><div class="simplebar-content" style="padding: 0px 16px;">
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
                                            </div></div></div></div><div class="simplebar-placeholder" style="width: auto; height: 438px;"></div></div><div class="simplebar-track simplebar-horizontal" style="visibility: hidden;"><div class="simplebar-scrollbar" style="transform: translate3d(0px, 0px, 0px); display: none;"></div></div><div class="simplebar-track simplebar-vertical" style="visibility: visible;"><div class="simplebar-scrollbar" style="height: 296px; transform: translate3d(0px, 0px, 0px); display: block;"></div></div></div>
                    </div>
                    <!-- end card body -->
                </div>

            </div>
            <!-- end row-->

        </div>
        <!-- end row-->

    </div>

</div>
