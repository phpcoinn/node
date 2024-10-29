<?php if(!defined("INCLUDED")) {require_once __DIR__  ."/template.php";exit;} ?>
    <div class="auth-page h-100 d-flex" id="app">
        <div class="container-fluid p-0">
            <div class="row g-0 h-100">
                <div class="col-xxl-3 col-lg-4 col-md-5 h-100 overflow-auto">
                    <div class="d-flex p-sm-5 p-4">
                        <div class="w-100">
                            <div class="d-flex flex-column h-100">
                                <div class="mb-0 mb-md-1 text-center">
                                    <a href="/" class="d-block auth-logo">
                                        <img src="/apps/common/img/logo.png" alt="" height="28"> <span class="logo-txt">PHPCoin</span>
                                    </a>
                                </div>

                                <template v-if="noData">
                                    <div class="auth-content my-auto">
                                        <div class="text-center">
                                            <h5 class="mb-3">Welcome !</h5>
                                            <div class="text-muted mt-2">
                                                Looks like you do not have account stored in this browser
                                            </div>
                                        </div>

                                        <div class="mt-5 text-center">
                                            <p class="text-muted mb-2">Don't have a wallet ?</p>
                                            <a href="/apps/wallet2/create_wallet.php" class="btn btn-outline-primary fw-semibold">Create one now</a>
                                        </div>
                                        <div class="mt-3 text-center">
                                            <p class="text-muted mb-2">Have a backup ?</p>
                                            <a href="/apps/wallet2/import_accounts.php" class="btn btn-outline-primary fw-semibold"> Import accounts </a>
                                        </div>

                                        <div class="mt-3 text-center">
                                            <p class="text-muted mb-2">OR</p>
                                            <a href="/apps/wallet2/auth.php" class="btn btn-primary waves-effect waves-light fw-semibold">Authenticate</a>
                                        </div>
                                    </div>

                                </template>
                                <template v-else>
                                    <div class="auth-content my-auto">
                                        <div class="text-center">
                                            <h5 class="mb-0">Welcome Back !</h5>
                                            <p class="text-muted mt-2">Sign in to access your wallet.</p>
                                        </div>
                                        <form class="mt-4 pt-2" action="">
                                            <div class="mb-3">
                                                <div class="d-flex align-items-start">
                                                    <div class="flex-grow-1">
                                                        <label class="form-label">Password</label>
                                                    </div>
                                                    <div class="flex-shrink-0">
                                                        <div class="">
                                                            <a class="text-muted" data-bs-toggle="offcanvas" href="#forgot_pass" role="button" aria-controls="forgot_pass">
                                                                Forgot password?
                                                            </a>
                                                            <div class="offcanvas offcanvas-start" tabindex="-1" id="forgot_pass" style="width:25%">
                                                                <div class="offcanvas-header">
                                                                    <h5 class="offcanvas-title" id="offcanvasExampleLabel">Forgot password?</h5>
                                                                    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                                                                </div>
                                                                <div class="offcanvas-body">
                                                                    Instructons how to recover password

                                                                    If you do not remember your password it can be stored in your browser

                                                                    If you write id down somewhere look for it

                                                                    If your browser remember it, find it in browser passwords

                                                                    Your password is important to unlocking your wallet and accounts inside it.

                                                                    If you lost your password access to your funds is irreparable
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="input-group auth-pass-inputgroup">
                                                    <input v-model="password" type="password" class="form-control" placeholder="Enter password" aria-label="Password" aria-describedby="password-addon">
                                                    <button class="btn btn-light shadow-none ms-0" type="button" id="password-addon"><i class="mdi mdi-eye-outline"></i></button>
                                                </div>
                                            </div>
                                            <div class="row mb-4">
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="remember-check" v-model="rememberMe">
                                                        <label class="form-check-label" for="remember-check">
                                                            Remember me
                                                        </label>
                                                    </div>
                                                </div>

                                            </div>
                                            <div class="mb-3">
                                                <button class="btn btn-primary w-100 waves-effect waves-light" type="button" @click="login">Log In</button>
                                            </div>
                                        </form>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <!-- end auth full page content -->
                </div>
                <!-- end col -->
                <div class="col-xxl-9 col-lg-8 col-md-7 h-100">
                    <?php require_once __DIR__ ."/inc/carousel.php" ?>
                </div>
                <!-- end col -->
            </div>
            <!-- end row -->
        </div>
        <!-- end container fluid -->
    </div>


<script src="/apps/common/js/jquery.min.js"></script>
<script src="/apps/common/js/phpcoin-crypto.js" type="text/javascript"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<script type="text/javascript">
    $(function(){
        $("#password-addon").on("click", function () {
            0 < $(this).siblings("input").length && ("password" === $(this).siblings("input").attr("type") ? $(this).siblings("input").attr("type", "input") : $(this).siblings("input").attr("type", "password"))
        });
    });
</script>
<script type="module">
    import { createApp, defineComponent } from 'https://cdn.jsdelivr.net/npm/vue@3/dist/vue.esm-browser.js';
    import {Wallet} from '/apps/wallet2/js/common.js';

    const LOCAL_STORAGE_PASSWORD = 'walletPasswordDev'
    const LOCAL_STORAGE_NAME = 'walletDataDev'

    let wallet;

    const app = createApp({
        setup() {
            wallet = new Wallet();
        },
        data() {
            return {
                password: null,
                rememberMe: false,
                noData: false
            }
        },
        mounted() {
            this.noData = wallet.noData;
            if(wallet.password) {
                this.password = wallet.password;
                this.rememberMe = true;
            }
        },
        methods:{
            login() {
                if (!this.password) {
                    Swal.fire(
                        {
                            title: 'Empty password',
                            text: 'You must enter password',
                            icon: 'error'
                        }
                    )
                    return;
                }

                let res = wallet.login(this.password, this.rememberMe);
                if (res) {
                    let addresses = wallet.getAddresses();
                    axios.post('/apps/wallet2/login.php?action=login', {addresses}).then(res => {
                        if (res.data.status === 'ok') {
                            document.location.href = '/apps/wallet2/'
                        } else {
                            Swal.fire(
                                {
                                    title: 'Error login',
                                    text: 'There is error during login to wallet',
                                    icon: 'error'
                                }
                            )
                        }
                    })
                }
            }
        }
    })
    app.mount('#app');
</script>



<style>
    .wallet-page-content {
        padding-left: 0;
    }
    .auth-page {
        margin-bottom: -55px;
    }
</style>
