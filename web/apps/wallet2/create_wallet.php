<?php if(!defined("INCLUDED")) {require_once __DIR__  ."/template.php";exit;} ?>
<div>
    <div class="auth-page" id="app">
        <div class="container-fluid p-0">
            <div class="row g-0">
                <div class="col-xxl-3 col-lg-4 col-md-5">
                    <div class="auth-full-page-content d-flex p-sm-5 p-4">
                        <div class="w-100">
                            <div class="d-flex flex-column h-100">
                                <div class="mb-0 mb-md-1 text-center">
                                    <a href="/" class="d-block auth-logo">
                                        <img src="/apps/common/img/logo.png" alt="" height="28"> <span class="logo-txt">PHPCoin</span>
                                    </a>
                                </div>
                                <div class="auth-content my-auto">
                                    <div class="text-center">
                                        <h5 class="mb-0">Create Account</h5>
                                        <p class="text-muted mt-2">Get your PHPCoin address now.</p>
                                    </div>
                                    <form class="needs-validation mt-2 pt-1" novalidate="" action="index.html">

                                        <div class="mb-2">
                                            <label for="userpassword" class="form-label">Create password</label>
                                            <div class="input-group auth-pass-inputgroup">
                                                <input type="password" v-model="password" class="form-control" placeholder="Create password" aria-label="Password" aria-describedby="password-addon">
                                                <button class="btn btn-light password-addon shadow-none ms-0" type="button" id="password-addon"><i class="mdi mdi-eye-outline"></i></button>
                                            </div>
                                            <small class="form-text text-muted">
                                            Password must contain numbers, special characters,
                                            upper and lower case letters of the Latin alphabet and be at least 8 characters long.
                                            </small>
                                        </div>
                                        <div class="mb-2">
                                            <label for="userpassword" class="form-label">Confirm password</label>
                                            <div class="input-group auth-pass-inputgroup">
                                                <input type="password" v-model="password2" class="form-control" placeholder="Confirm password" aria-label="Password" aria-describedby="password-addon">
                                                <button class="btn btn-light password-addon shadow-none ms-0" type="button" id="password-addon"><i class="mdi mdi-eye-outline"></i></button>
                                            </div>
                                        </div>
                                        <div class="mt-2 mb-2">
                                            <div class="col">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="save_pass" v-model="savePass" @click="savePassWarning">
                                                    <label class="form-check-label" for="save_pass">
                                                        Save password locally
                                                    </label>
                                                </div>
                                            </div>
                                        </div>




                                        <div class="text-danger mt-2 mb-2">
                                            <div>
                                                <strong>Important</strong>
                                            </div>
                                            <ul>
                                                <li>With this password your wallet data will be encrypted in browser</li>
                                                <li>If you lose this password you will have no longer access to wallet addresses.</li>
                                                <li>If you delete cache or reinstall browser you will lose access to wallet addresses.</li>
                                                <li>It is recommended to keep password stored also somewhere else</li>
                                            </ul>
                                        </div>

                                        <div class="text-danger mt-2 mb-2">
                                            <div class="col">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="agree" v-model="agree">
                                                    <label class="form-check-label" for="agree">
                                                        I understand the risks
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-2">
                                            <button class="btn btn-primary w-100 waves-effect waves-light" type="button" @click="createWallet">Create</button>
                                        </div>
                                    </form>

                                    <div class="mt-4 text-center">
                                        <p class="text-muted mb-0">Already have an account ?
                                            <a href="/apps/wallet2/login.php" class="text-primary fw-semibold"> Login </a> </p>
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
    function checkPassword(str)
    {
        let re = /^(?=.*\d)(?=.*[!@#$%^&*])(?=.*[a-z])(?=.*[A-Z]).{8,}$/;
        return re.test(str);
    }

    $(function(){
        $(".password-addon").on("click", function () {
            0 < $(this).siblings("input").length && ("password" === $(this).siblings("input").attr("type") ? $(this).siblings("input").attr("type", "input") : $(this).siblings("input").attr("type", "password"))
        });
    });
</script>
<script type="module">
    import { createApp, defineComponent } from 'https://cdn.jsdelivr.net/npm/vue@3/dist/vue.esm-browser.js';
    import {Wallet} from '/apps/wallet2/js/common.js';

    let wallet;

    const app = createApp({
        setup() {
            wallet = new Wallet();
        },
        data() {
            return {
                password: null,
                password2: null,
                agree: false,
                savePass: true
            }
        },
        mounted() {
            console.log(wallet);
        },
        methods:{
            createWallet() {
                if(wallet.password) {
                    Swal.fire(
                        {
                            title: 'You already have account',
                            text: 'Please login',
                            icon: 'warning'
                        }
                    )
                    return;
                }
                if(!checkPassword(this.password)) {
                    Swal.fire(
                        {
                            title: 'Invalid password',
                            text: 'Password must meet requirements',
                            icon: 'error'
                        }
                    )
                    return
                }
                if(this.password !== this.password2) {
                    Swal.fire(
                        {
                            title: 'Passwords not identical',
                            text: 'Check both your passwords are the same',
                            icon: 'error'
                        }
                    )
                    return
                }
                if(!this.agree) {
                    Swal.fire(
                        {
                            title: 'Terms not accepted',
                            text: 'You must agree with terms above',
                            icon: 'error'
                        }
                    )
                    return
                }
                wallet.createWallet(this.password, this.savePass);
                document.location.href='/apps/wallet2/login.php';
            },
            savePassWarning() {
                if(!this.savePass) {
                    return;
                }
                Swal.fire(
                    {
                        title: 'Important note',
                        text: 'If you choose to not save password it can not be retrievable. You MUST remember it elsewhere!',
                        icon: 'warning'
                    }
                )
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
