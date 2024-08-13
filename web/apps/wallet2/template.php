<?php
require_once dirname(__DIR__) . "/apps.inc.php";
require_once ROOT . '/web/apps/explorer/include/functions.php';
define("PAGE", true);
define("APP_NAME", "Wallet");

require_once __DIR__ ."/inc/actions.php";

session_start();

//hardcoded for testing
//$_SESSION['logged']=true;
//$_SESSION['currentAddress']="PnTXVeY7v9EFg72u4Wdiq3yGEZoFnMhozh";

if(!$_SESSION['logged'] && $_SERVER['PHP_SELF'] != "/apps/wallet2/login.php" &&
    $_SERVER['PHP_SELF'] != "/apps/wallet2/create_wallet.php" && $_SERVER['PHP_SELF'] != "/apps/wallet2/import_accounts.php") {
    header("location: /apps/wallet2/login.php");
    exit;
}

if($_SESSION['logged'] && ($_SERVER['PHP_SELF'] == "/apps/wallet2/login.php" ||
    $_SERVER['PHP_SELF'] == "/apps/wallet2/create_wallet.php" || $_SERVER['PHP_SELF'] == "/apps/wallet2/import_accounts.php")) {
    header("location: /apps/wallet2/index.php");
    exit;
}

?>
<?php
define("HEAD_CSS", ["/apps/wallet2/wallet.css"]);
require_once __DIR__ . '/../common/include/top.php';
?>
<div class="wallet-page">
            <?php

            if($_SESSION['logged']) {
                require_once __DIR__ ."/inc/sidebar.php";
            }
            ?>
    <div class="wallet-page-content">
        <?php

            $file = $_SERVER['PHP_SELF'];
            define("INCLUDED", true);
            require ROOT . "/web/" . $file;
        ?>
    </div>
</div>
<style>
    @media (max-width: 992px) {
        .vertical-menu {
            display: block;
            width: 70px;
        }
        .vertical-menu {
            position:absolute;
            width:70px!important;
            z-index:5
        }
        .vertical-menu .simplebar-content-wrapper,
        .vertical-menu .simplebar-mask {
            overflow:visible!important
        }
        .vertical-menu .simplebar-scrollbar {
            display:none!important
        }
        .vertical-menu .simplebar-offset {
            bottom:0!important
        }
        .vertical-menu #sidebar-menu .badge,
        .vertical-menu #sidebar-menu .menu-title,
        .vertical-menu #sidebar-menu .sidebar-alert {
            display:none!important
        }
        .vertical-menu #sidebar-menu .nav.collapse {
            height:inherit!important
        }
        .vertical-menu #sidebar-menu>ul>li {
            position:relative;
            white-space:nowrap
        }
        .vertical-menu #sidebar-menu>ul>li>a {
            padding:15px 20px;
            -webkit-transition:none;
            transition:none
        }
        .vertical-menu #sidebar-menu>ul>li>a:active,
        .vertical-menu #sidebar-menu>ul>li>a:focus,
        .vertical-menu #sidebar-menu>ul>li>a:hover {
            color:var(--bs-sidebar-menu-item-hover-color)
        }
        .vertical-menu #sidebar-menu>ul>li>a i {
            font-size:1.45rem;
            margin-left:4px
        }
        .vertical-menu #sidebar-menu>ul>li>a svg {
            height:18px;
            width:18px;
            margin-left:6px
        }
        .vertical-menu #sidebar-menu>ul>li>a span {
            display:none;
            padding-left:25px
        }
        .vertical-menu #sidebar-menu>ul>li>a.has-arrow:after {
            display:none
        }
        .vertical-menu #sidebar-menu>ul>li:hover>a {
            position:relative;
            width:calc(190px + 70px);
            color:#5156be;
            background-color:var(--bs-sidebar-bg);
            -webkit-transition:none;
            transition:none
        }
        .vertical-menu #sidebar-menu>ul>li:hover>a i {
            color:#5156be
        }
        .vertical-menu #sidebar-menu>ul>li:hover>a svg {
            color:var(--bs-sidebar-menu-item-active-color);
            fill:rgba(var(--bs-sidebar-menu-item-active-color),.2)
        }
        .vertical-menu #sidebar-menu>ul>li:hover>a span {
            display:inline
        }
        .vertical-menu #sidebar-menu>ul>li:hover>ul {
            display:block;
            left:70px;
            position:absolute;
            width:190px;
            height:auto!important;
            -webkit-box-shadow:0 .5rem 1rem rgba(0,0,0,.1);
            box-shadow:0 .5rem 1rem rgba(0,0,0,.1)
        }
        .vertical-menu #sidebar-menu>ul>li:hover>ul ul {
            -webkit-box-shadow:0 .5rem 1rem rgba(0,0,0,.1);
            box-shadow:0 .5rem 1rem rgba(0,0,0,.1)
        }
        .vertical-menu #sidebar-menu>ul>li:hover>ul a {
            -webkit-box-shadow:none;
            box-shadow:none;
            padding:8px 20px;
            position:relative;
            width:190px;
            z-index:6;
            color:var(--bs-sidebar-menu-sub-item-color)
        }
        .vertical-menu #sidebar-menu>ul>li:hover>ul a:hover {
            color:var(--bs-sidebar-menu-item-hover-color)
        }
        .vertical-menu #sidebar-menu>ul ul {
            padding:5px 0;
            z-index:9999;
            display:none;
            background-color:#fff
        }
        .vertical-menu #sidebar-menu>ul ul li:hover>ul {
            display:block;
            left:190px;
            height:auto!important;
            margin-top:-36px;
            position:absolute;
            width:190px;
            padding:5px 0
        }
        .vertical-menu #sidebar-menu>ul ul li>a span.pull-right {
            position:absolute;
            right:20px;
            top:12px;
            -webkit-transform:rotate(270deg);
            transform:rotate(270deg)
        }
        .vertical-menu #sidebar-menu>ul ul li.active a {
            color:#f8f9fa
        }
        #sidebar-menu .mm-active>.has-arrow:after {
            -webkit-transform:rotate(0);
            transform:rotate(0)
        }
        .navbar-header {
            display: none;
        }
        .wallet-page-content {
            padding-left: 80px;
        }
    }
</style>


<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
