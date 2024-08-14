<?php
if(!defined("PAGE")) exit;

if(empty($_SESSION['currentAddress'])) {
    if(isset($_SESSION['wallet'])) {
        $_SESSION['currentAddress'] = $_SESSION['wallet']['addresses'][0];
    } else {
        $_SESSION['currentAddress'] = $_SESSION['account']['address'];
    }

}
$address = $_SESSION['currentAddress'];
$balance = Account::pendingBalance($address);

$addresses = $_SESSION['wallet']['addresses'] ?? [];

$singleAccount = isset($_SESSION['account']);
if($singleAccount) {
    $address = $_SESSION['account']['address'];
}
?>
<div class="vertical-menu mm-active">

    <div class="navbar-header mx-0 w-100" style="max-width:100%">
        <div class="d-flex w-100">
            <div class="navbar-brand-box w-100 me-0 pe-0 d-flex align-items-center" id="app-sidebar">
                <div class="logo flex-grow-1" style="line-height: initial">
                    <span class="logo-lg">
                        <div class="dropdown" v-if="currentAddress">
                            <a class="d-block dropdown-toggle text-end" href="#" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <div class="">
                                    <div class="d-flex align-items-center">
                                        <div class="flex flex-column text-start ps-4">
                                            <div class="fw-bold">{{truncate(currentAddress)}}</div>
                                            <div>{{currentBalance}}</div>
                                        </div>
                                        <i class="mdi mdi-chevron-down ms-1 fw-bold font-size-24" v-if="!singleAccount"></i>
                                    </div>
                                </div>
                            </a>

                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton1" v-if="!singleAccount">
                                <template v-for="address in addresses" :key="address">
                                    <a :class="`dropdown-item ${address === currentAddress ? 'active' : ''}`" href="" @click.prevent="setCurrentAddress(address)">
                                        <div>
                                            <div>{{truncate(address)}}</div>
                                            <small>{{balances[address]}}</small>
                                        </div>
                                    </a>
                                </template>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="/apps/wallet2/accounts.php">Manage accounts...</a>
                            </div>
                        </div>
                    </span>
                </div>
<!--                <a href="index.html" class="logo logo-dark ps-3" style="line-height: initial;">-->
<!--                    <span class="logo-lg">-->
<!--                        <span class="logo-txt">-->
<!--                            <i class="fas fa-user me-2"></i>-->
<!--                            Pfdm....hdjye-->
<!--                        </span>-->
<!--                        <div class="ps-4 ms-2">-->
<!--                            123.2434-->
<!--                        </div>-->
<!--                    </span>-->
<!--                </a>-->
            </div>

            <button type="button" class="btn btn-sm px-3 font-size-16 header-item" id="vertical-menu-btn" style="margin-left: 0 !important; width:100%">
                <i class="fa fa-fw fa-bars"></i>
            </button>

        </div>
    </div>

    <div data-simplebar="init" class="h-100 mm-show">
        <div class="simplebar-wrapper" style="margin: 0px;">
            <div class="simplebar-height-auto-observer-wrapper">
                <div class="simplebar-height-auto-observer"></div>
            </div>
            <div class="simplebar-mask">
                <div class="simplebar-offset" style="right: -20px; bottom: 60px;">
                    <div class="simplebar-content-wrapper"
                         style="height: 100%; padding-right: 20px; padding-bottom: 0px; overflow: hidden scroll;">
                        <div class="simplebar-content" style="padding: 0px;">

                            <!--- Sidemenu -->
                            <div id="sidebar-menu" class="mm-active">
                                <!-- Left Menu Start -->
                                <ul class="metismenu list-unstyled mm-show" id="side-menu">
                                    <li class="menu-title" data-key="t-menu">Menu</li>

                                    <li class="mm-active">
                                        <a href="/apps/wallet2" class="active">
                                            <i class="fas fa-wallet"></i>
                                            <span data-key="t-dashboard">Wallet</span>
                                        </a>
                                    </li>
                                    <?php if(!$singleAccount) { ?>
                                        <li class="mm-active">
                                            <a href="/apps/wallet2/accounts.php" class="active">
                                                <i class="fa fa-address-book"></i>
                                                <span data-key="t-dashboard">Accounts</span>
                                            </a>
                                        </li>
                                    <?php } ?>
                                    <li class="mm-active">
                                        <a href="index.html" class="active">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                 class="feather feather-home">
                                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                            </svg>
                                            <span data-key="t-dashboard">Address book</span>
                                        </a>
                                    </li>
                                    <li class="mm-active">
                                        <a href="index.html" class="active">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                 class="feather feather-home">
                                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                            </svg>
                                            <span data-key="t-dashboard">Transactions</span>
                                        </a>
                                    </li>
                                    <li class="mm-active">
                                        <a href="index.html" class="active">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                 class="feather feather-home">
                                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                            </svg>
                                            <span data-key="t-dashboard">Masternodes</span>
                                        </a>
                                    </li>
                                    <li class="mm-active">
                                        <a href="index.html" class="active">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                 class="feather feather-home">
                                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                            </svg>
                                            <span data-key="t-dashboard">Tokens</span>
                                        </a>
                                    </li>
                                    <li class="mm-active">
                                        <a href="index.html" class="active">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                 class="feather feather-home">
                                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                            </svg>
                                            <span data-key="t-dashboard">Settings</span>
                                        </a>
                                    </li>
                                    <li class="mm-active">
                                        <a href="/apps/wallet2/index.php?action=logout" class="active">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                 class="feather feather-home">
                                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                            </svg>
                                            <span data-key="t-dashboard">Logout</span>
                                        </a>
                                    </li>

                                    <li>
                                        <a href="javascript: void(0);" class="has-arrow">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                 class="feather feather-grid">
                                                <rect x="3" y="3" width="7" height="7"></rect>
                                                <rect x="14" y="3" width="7" height="7"></rect>
                                                <rect x="14" y="14" width="7" height="7"></rect>
                                                <rect x="3" y="14" width="7" height="7"></rect>
                                            </svg>
                                            <span data-key="t-apps">Apps</span>
                                        </a>
                                        <ul class="sub-menu mm-collapse" aria-expanded="false">
                                            <li>
                                                <a href="apps-calendar.html">
                                                    <span data-key="t-calendar">Calendar</span>
                                                </a>
                                            </li>

                                            <li>
                                                <a href="javascript: void(0);" class="has-arrow">
                                                    <span data-key="t-email">Email</span>
                                                </a>
                                                <ul class="sub-menu mm-collapse" aria-expanded="false">
                                                    <li><a href="apps-email-inbox.html" data-key="t-inbox">Inbox</a>
                                                    </li>
                                                    <li><a href="apps-email-read.html" data-key="t-read-email">Read
                                                            Email</a></li>
                                                </ul>
                                            </li>
                                            <li>
                                                <a href="javascript: void(0);" class="">
                                                    <span data-key="t-blog">Blog</span>
                                                    <span class="badge rounded-pill badge-soft-danger float-end"
                                                          key="t-new">New</span>
                                                </a>
                                                <ul class="sub-menu mm-collapse" aria-expanded="false">
                                                    <li><a href="apps-blog-grid.html" data-key="t-blog-grid">Blog
                                                            Grid</a></li>
                                                    <li><a href="apps-blog-list.html" data-key="t-blog-list">Blog
                                                            List</a></li>
                                                    <li><a href="apps-blog-detail.html" data-key="t-blog-details">Blog
                                                            Details</a></li>
                                                </ul>
                                            </li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>
                            <!-- Sidebar -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="simplebar-placeholder" style="width: auto; height: 995px;"></div>
        </div>
        <div class="simplebar-track simplebar-horizontal" style="visibility: hidden;">
            <div class="simplebar-scrollbar" style="transform: translate3d(0px, 0px, 0px); display: none;"></div>
        </div>
        <div class="simplebar-track simplebar-vertical" style="visibility: visible;">
            <div class="simplebar-scrollbar"
                 style="height: 152px; transform: translate3d(0px, 0px, 0px); display: block;"></div>
        </div>
    </div>
</div>
<script src="/apps/common/js/phpcoin-crypto.js" type="text/javascript"></script>
<script type="module">
    import { createApp, defineComponent } from 'https://cdn.jsdelivr.net/npm/vue@3/dist/vue.esm-browser.js';
    import {Wallet} from '/apps/wallet2/js/common.js';
    import axios from 'https://cdn.jsdelivr.net/npm/axios@1.7.2/+esm'

    let wallet;

    const App = {
        setup() {
            wallet = new Wallet();
        },
        data() {
            return {
                addresses: [],
                balances: {},
                currentAddress: null,
                singleAccount: <?php echo "'".$_SESSION['account']['address']."'" ?? 'null' ?>
            }
        },
        mounted() {
            if(this.singleAccount) {
                this.currentAddress = this.singleAccount;
                this.addresses = [this.singleAccount];
            } else {
                this.addresses = <?php echo json_encode($addresses) ?>;
                this.currentAddress = wallet.currentAddress;
                if(!this.currentAddress) {
                    this.currentAddress = this.addresses[0];
                }
            }
            this.fetchBalances();
        },
        computed: {
            currentBalance() {
                return this.currentAddress && this.balances && this.balances[this.currentAddress];
            }
        },
        methods: {
            fetchBalances() {
                let addresses = this.addresses;
                axios.post('/apps/wallet2/api.php?q=fetchBalances', {addresses}).then(res => {
                    this.balances = res.data.data;
                });
            },
            truncate(s) {
                return s && s.substr(0, 6) + "..." + s.substr(-6);
            },
            setCurrentAddress(address) {
                this.currentAddress = address;
                wallet.storeCurrentAddress(address);
                axios.post('/apps/wallet2/api.php?q=setCurrentAddress', {address}).then(res => {
                    window.document.location.reload();
                });
            }
        }
    };
    const app = createApp(App);
    app.mount('#app-sidebar');
</script>



<style>
    #vertical-menu-btn {
        width: auto !important;
    }
    .sidebar-enable #vertical-menu-btn {
        width: 100% !important;
    }
    .sidebar-enable .vertical-menu .navbar-brand-box {
        width: 0 !important;
    }
</style>
