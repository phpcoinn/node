<?php if(!defined("INCLUDED")) {require_once __DIR__  ."/template.php";exit;} ?>
<div id="app">
    <div class="auth-page">
        <div class="container-fluid p-0">
            <div class="row g-0">
                <div class="col-xxl-3 col-lg-4 col-md-5">
                    <div class="auth-full-page-content d-flex p-sm-5 p-4">
                        <div class="w-100">
                            <div class="d-flex flex-column h-100">
                                <div class="mb-4 mb-md-5 text-center">
                                    <a href="/" class="d-block auth-logo">
                                        <img src="/apps/common/img/logo.png" alt="" height="28"> <span class="logo-txt">PHPCoin</span>
                                    </a>
                                </div>
                                <div class="auth-content my-auto">
                                    <div class="text-center">
                                        <h5 class="mb-0">Import accounts</h5>
                                        <p class="text-muted mt-2">Recover account from backup file.</p>
                                    </div>
                                    <div class="alert alert-warning text-center my-4" role="alert">
                                        Only backup exported with Multiwallet can be imported
                                    </div>
                                    <form class="mt-4" action="index.html">
                                        <div class="mb-3">
                                            <label class="form-label">Password</label>
                                            <div class="input-group auth-pass-inputgroup">
                                                <input type="password" class="form-control" v-model="password" aria-label="Password" aria-describedby="password-addon">
                                                <button class="btn btn-light shadow-none ms-0" type="button" id="password-addon"><i class="mdi mdi-eye-outline"></i></button>
                                            </div>
                                            <small class="form-text text-muted">
                                                Your stored backup phrase
                                            </small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Backup</label>
                                            <textarea class="form-control" v-model="backupText"></textarea>
                                            <small class="form-text text-muted">
                                                Content of your backup file
                                            </small>
                                        </div>
                                        <div class="mb-3 mt-4">
                                            <button class="btn btn-primary w-100 waves-effect waves-light" @click.prevent="importAccountsAction">Import</button>
                                        </div>
                                    </form>

                                    <div class="mt-5 text-center">
                                        <p class="text-muted mb-0">Have an account ?  <a href="/apps/wallet2/login.php" class="text-primary fw-semibold"> Sign In </a> </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end auth full page content -->
                </div>
                <!-- end col -->
                <div class="col-xxl-9 col-lg-8 col-md-7">
                    <?php require_once __DIR__ ."/inc/carousel.php" ?>
                </div>
                <!-- end col -->
            </div>
            <!-- end row -->
        </div>
        <!-- end container fluid -->
    </div>
</div>
<script src="/apps/common/js/jquery.min.js"></script>
<script src="/apps/common/js/phpcoin-crypto.js" type="text/javascript"></script>
<script type="text/javascript">
    $(function(){
        $("#password-addon").on('click', function () {
            if ($(this).siblings('input').length > 0) {
                $(this).siblings('input').attr('type') === "password" ? $(this).siblings('input').attr('type', 'input') : $(this).siblings('input').attr('type', 'password');
            }
        })
    })
</script>
<script type="module">
    import { createApp, defineComponent } from 'https://cdn.jsdelivr.net/npm/vue@3/dist/vue.esm-browser.js';
    import {Wallet} from '/apps/wallet2/js/common.js';

    let wallet;

    const App = {
        setup() {
            wallet = new Wallet();
        },
        data() {
            return {
                password: null,
                backupText: null
            }
        },
        methods: {
            importAccountsAction() {
                let res = wallet.importAccounts(this.backupText, this.password);
                if(res) {
                    document.location.href='/apps/wallet2';
                }
            }
        }
    };
    const app = createApp(App);
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

