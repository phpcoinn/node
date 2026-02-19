<?php
// wallet.php - PHP Coin Wallet Gateway
// Handles browser wallet authentication via postMessage protocol

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <title>PHP Coin Wallet Gateway</title>
    <link rel="icon" href="/apps/common/img/phpcoin-icon.png">

    <!-- PHP Coin Theme CSS -->
    <link rel="stylesheet" href="/apps/common/css/preloader.min.css" type="text/css" />
    <link href="/apps/common/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css" />
    <link href="/apps/common/css/sweetalert2.min.css" rel="stylesheet" type="text/css" />
    <link href="/apps/common/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="/apps/common/css/app.min.css" id="app-style" rel="stylesheet" type="text/css" />

    <style>
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        html, body {
            min-height: 100%;
        }
        #layout-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .main-content {
            flex: 1;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 0;
        }
        .page-content {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 0;
        }
        .container-fluid {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 0;
        }
        .login-view {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin: 0;
            min-height: 0;
        }
        .card {
            max-width: 100%;
            width: 100%;
            margin: 0;
        }
        .private-key-input-group {
            display: flex;
            gap: 5px;
        }
        .private-key-input-group input {
            flex: 1;
        }
        .toggle-password-btn {
            padding: 10px 15px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
        }
        .accounts-menu {
            max-height: 50vh;
            overflow-y: auto;
        }
        .dropdown-item.account {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
        }
        .account-info {
            flex: 1;
        }
        .account-name {
            font-weight: bold;
            font-size: 14px;
        }
        .account-address {
            font-size: 12px;
            color: #666;
        }
        .account-balance {
            font-size: 12px;
            color: #0077ff;
            margin-left: 10px;
        }
        .text-secret.hide {
            color: transparent !important;
            text-shadow: 0 0 8px rgba(0,0,0,0.5);
        }
        #log {
            text-align: left;
            margin-top: 20px;
            max-height: 150px;
            overflow-y: auto;
            background: #f9f9f9;
            padding: 10px;
            font-size: 13px;
            border-radius: 6px;
        }
    </style>

    <!-- Vue 3 from CDN -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <!-- PHP Coin Crypto Library -->
    <script src="/apps/common/js/phpcoin-crypto.browser.js"></script>
</head>
<body class="phpcoin" data-layout="horizontal" data-layout-mode="light">
    <div id="layout-wrapper">
        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">
                    <div id="app" class="row login-view">
                        <div class="col-12 col-md-10 col-lg-8 col-xl-6">
                            <div class="card shadow-primary">
                                <div class="card-header">
                                    <h4 class="">PHP Coin Wallet Gateway</h4>
                                    <h6 class="card-subtitle text-muted">Browser wallet authentication</h6>
                                </div>
                                <div class="card-body">
                                    <div class="card-text">
                                        <!-- Status Message -->
                                        <div class="alert alert-info" v-if="statusMessage">
                                            {{ statusMessage }}
                                        </div>

                                        <!-- Request Approval UI -->
                                        <div v-if="currentView === 'request'" :key="'request-ui-' + requestDomain" class="mb-4">
                                            <div class="alert alert-warning">
                                                <strong>Login Request:</strong>
                                                <div class="mt-2">Domain: {{ requestDomain }}</div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-primary" @click="showLoginForApproval">Approve</button>
                                                <button type="button" class="btn btn-outline-danger" @click="rejectRequest">Reject</button>
                                            </div>
                                        </div>

                                        <!-- Sign Transaction UI (reference: gateway approve.php) -->
                                        <div v-if="currentView === 'signTx'" :key="'sign-tx-' + (pendingSignTxRequest?.request?.issued_at || 0)" class="mb-4">
                                            <div class="alert alert-warning mb-3">
                                                <strong>Sign transaction</strong>
                                                <div class="mt-1 text-muted small">Domain: {{ pendingSignTxRequest?.request?.domain }}</div>
                                            </div>
                                            <template v-if="signTx">
                                                <div class="card mb-2 text-center">
                                                    <h5 class="card-title p-1 text-muted">Your Address</h5>
                                                    <h5 class="card-subtitle p-1">{{ signTx.src }}</h5>
                                                    <h4 class="p-1" v-if="signTxBalance != null">{{ signTxBalance }}</h4>
                                                </div>
                                                <div class="text-center d-flex align-items-center justify-content-center m-2">
                                                    <i class="fas fa-arrow-down fa-2x text-primary"></i>
                                                    <div>
                                                        <h3 class="ms-2 text-primary">{{ formatAmount(signTx.val) }}</h3>
                                                        <h5 class="ms-2 text-secondary" v-if="signTx.fee">Fee: {{ formatAmount(signTx.fee) }}</h5>
                                                    </div>
                                                </div>
                                                <div class="card text-center mb-2">
                                                    <h5 class="card-title p-1 text-muted">Receiver</h5>
                                                    <h5 class="card-subtitle p-1">{{ signTx.dst || '(empty)' }}</h5>
                                                </div>
                                                <div class="mb-3">
                                                    <i class="fas fa-info-circle"></i>
                                                    <a href="#" @click.prevent="showSignTxDetails = !showSignTxDetails">Transaction details</a>
                                                    <div v-show="showSignTxDetails" class="card p-2 mt-1">
                                                        <pre class="text-muted mb-0" style="white-space: pre-wrap; word-break: break-all; font-size: 12px;">{{ JSON.stringify(signTx, null, 2) }}</pre>
                                                    </div>
                                                </div>
                                                <div class="alert alert-danger alert-outline" v-if="signTxBalance != null && parseFloat(signTxBalance) < parseFloat(signTx.val || 0)" role="alert">
                                                    Not enough balance for transfer
                                                </div>
                                                <template v-else>
                                                    <div class="mb-3">
                                                        <label class="form-label" for="signTxPrivateKey">Enter your private key to sign:</label>
                                                        <div class="input-group auth-pass-inputgroup">
                                                            <input
                                                                id="signTxPrivateKey"
                                                                class="form-control"
                                                                placeholder="Lzh..."
                                                                v-model="signTxPrivateKey"
                                                                :type="showSignTxPassword ? 'text' : 'password'"
                                                            />
                                                            <button class="btn btn-light shadow-none ms-0 border" type="button" @click.prevent="showSignTxPassword = !showSignTxPassword" :aria-label="showSignTxPassword ? 'Hide' : 'Show'">
                                                                <i :class="showSignTxPassword ? 'mdi mdi-eye-off-outline' : 'mdi mdi-eye-outline'"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-primary" @click="approveSignTx">Approve</button>
                                                        <button type="button" class="btn btn-outline-danger" @click="rejectSignTx">Reject</button>
                                                    </div>
                                                </template>
                                            </template>
                                        </div>

                                        <!-- Login Options -->
                                        <div v-if="currentView === 'initial' || currentView === 'privateKeyForm' || currentView === 'generatedAccount'" :key="'login-ui-' + currentView">
                                            <!-- Initial Options: Two Buttons Side by Side -->
                                            <div v-if="currentView === 'initial'">
                                                <div class="row">
                                                    <div class="col-sm-6 text-center">
                                                        <div class="mb-2">
                                                            Authenticate with your private key
                                                        </div>
                                                        <button class="btn btn-primary" @click="showPrivateKeyInput">Authenticate</button>
                                                    </div>
                                                    <div class="col-sm-6 text-center">
                                                        <div class="mb-2">
                                                            Create new account
                                                        </div>
                                                        <button class="btn btn-outline-primary" @click="createNewAccount">Create</button>
                                                    </div>
                                                </div>

                                                <hr/>

                                                <!-- Multiwallet Section -->
                                                <div id="accounts-app">
                                                    <div class="text-center" v-if="multiwalletAccounts && multiwalletAccounts.length > 0">
                                                        <div class="mb-2">
                                                            Login with
                                                            <a class="fw-bold" href="#" @click.prevent>Multiwallet</a>
                                                        </div>
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" class="btn btn-outline-primary">Select stored account</button>
                                                            <button
                                                                type="button"
                                                                class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split"
                                                                data-bs-toggle="dropdown"
                                                                aria-expanded="false"
                                                            >
                                                                <i class="mdi mdi-chevron-down"></i>
                                                            </button>
                                                            <div class="dropdown-menu accounts-menu" style="max-height: 50vh">
                                                                <a
                                                                    class="dropdown-item account"
                                                                    href="#"
                                                                    v-for="account in multiwalletAccounts"
                                                                    :key="account.address"
                                                                    @click.prevent="loginWithMultiwallet(account)"
                                                                >
                                                                    <div>
                                                                        <div class="account-name">{{ account.name || 'Account' }}</div>
                                                                        <div class="account-address">{{ shortAddress(account.address) }}</div>
                                                                    </div>
                                                                    <div class="account-balance">{{ account.balance || '0' }}</div>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div v-else class="text-center">
                                                        Manage accounts with
                                                        <a class="fw-bold" href="#" @click.prevent>Multiwallet</a>
                                                        <br/>
                                                        and login with one click
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Generated Account View (shown when Create is clicked) -->
                                            <div v-if="currentView === 'generatedAccount'" class="mb-4">
                                                <h5>Generated account:</h5>

                                                <dl class="row">
                                                    <dt class="col-sm-3">Address:</dt>
                                                    <dd class="col-sm-9"><span>{{ generatedAccount?.address }}</span></dd>
                                                    <dt class="col-sm-3">Public Key:</dt>
                                                    <dd class="col-sm-9" style="word-break: break-all"><span>{{ generatedAccount?.publicKey }}</span></dd>
                                                    <dt class="col-sm-12 d-flex justify-content-between">
                                                        <span>Private Key:</span>
                                                        <span>
                                                            <a href="#" @click.prevent="toggleSecret">{{ showPrivateKeySecret ? 'Hide' : 'Show' }}</a> |
                                                            <a href="#" @click.prevent="copyToClipboard">Copy</a>
                                                        </span>
                                                    </dt>
                                                    <dd class="col-sm-12" :class="{ 'text-secret hide': !showPrivateKeySecret }" style="word-break: break-all">
                                                        <span>{{ generatedAccount?.privateKey }}</span>
                                                    </dd>
                                                </dl>

                                                <p class="alert alert-warning">
                                                    <strong>Important: Save Your Private Key Securely!</strong>
                                                    <br/>
                                                    Your private key is the only way to access your PHP Coin account.
                                                    If you lose it, you will permanently lose access to your funds, and no one can recover it for you.
                                                </p>

                                                <div class="d-flex justify-content-between">
                                                    <button type="button" @click="loginWithGeneratedAccount" class="btn btn-primary w-md">Login with this account</button>
                                                    <button type="button" @click="closeGeneratedAccount" class="btn btn-light w-md">Cancel</button>
                                                </div>
                                            </div>

                                            <!-- Private Key Input Form (shown when Authenticate is clicked) -->
                                            <div v-if="currentView === 'privateKeyForm'" class="mb-4">
                                                <h5 class="mb-3">Authenticate with your private key</h5>
                                                <div class="mb-3">
                                                    <div class="private-key-input-group">
                                                        <input
                                                            class="form-control"
                                                            v-model="privateKey"
                                                            placeholder="Enter your private key (Lzh...)"
                                                            :type="showPassword ? 'text' : 'password'"
                                                            @keyup.enter="loginWithPrivateKey"
                                                        />
                                                        <button
                                                            type="button"
                                                            class="toggle-password-btn"
                                                            @click="showPassword = !showPassword"
                                                        >
                                                            <i :class="showPassword ? 'mdi mdi-eye-off' : 'mdi mdi-eye'"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="form-check mb-3">
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        id="rememberPrivateKey"
                                                        v-model="rememberPrivateKey"
                                                    />
                                                    <label class="form-check-label" for="rememberPrivateKey">
                                                        Remember private key (stored locally)
                                                    </label>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-primary" @click="loginWithPrivateKey">Login</button>
                                                    <button class="btn btn-secondary" @click="currentView = 'initial'">Cancel</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent border-top text-muted">
                                    <small>PHP Coin Wallet Gateway - Ready to accept requests</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="/apps/common/js/jquery.min.js"></script>
    <script src="/apps/common/js/bootstrap.bundle.min.js"></script>
    <script src="/apps/common/js/sweetalert2.min.js"></script>

    <script>
        const { createApp } = Vue;
        const LOCAL_STORAGE_NAME = 'walletData';
        const LOCAL_STORAGE_PASSWORD = 'walletPassword';

        createApp({
            data() {
                return {
                    privateKey: '',
                    showPassword: false,
                    rememberPrivateKey: true,
                    statusMessage: 'Waiting for login requests...',
                    currentView: 'initial', // 'initial', 'request', 'privateKeyForm', 'generatedAccount', 'signTx'
                    requestDomain: '',
                    pendingRequest: null,
                    pendingSignTxRequest: null,
                    signTxPrivateKey: '',
                    showSignTxPassword: false,
                    showSignTxDetails: false,
                    signTxBalance: null,
                    multiwalletAccounts: [],
                    showAccountDropdown: false,
                    generatedAccount: null,
                    showPrivateKeySecret: false
                }
            },
            computed: {
                signTx() {
                    const req = this.pendingSignTxRequest?.request;
                    return req && req.transaction ? req.transaction : null;
                }
            },
            mounted() {
                // Load multiwallet accounts
                this.loadMultiwalletAccounts();

                // Initialize wallet listener for postMessage protocol
                this.initWalletListener();

                // Close dropdown when clicking outside
                document.addEventListener('click', (e) => {
                    if (!this.$refs.accountDropdown?.contains(e.target)) {
                        this.showAccountDropdown = false;
                    }
                });
            },
            methods: {
                initWalletListener() {
                    phpcoinCrypto.walletListen({
                        onRequest: (request, actions) => {
                            this.showRequestUI(request, actions);
                        },
                        onSignTransaction: (request, actions) => {
                            this.showSignTxUI(request, actions);
                        }
                    });
                },

                showRequestUI(request, actions) {
                    this.requestDomain = request.domain;
                    this.currentView = 'request';
                    this.statusMessage = 'Login request received';
                    this.pendingRequest = { request, actions };
                },

                showSignTxUI(request, actions) {
                    this.pendingSignTxRequest = { request, actions };
                    this.signTxPrivateKey = localStorage.getItem('privateKey') || '';
                    this.showSignTxDetails = false;
                    this.signTxBalance = null;
                    this.currentView = 'signTx';
                    this.statusMessage = 'Sign transaction request';
                    const src = request.transaction && request.transaction.src;
                    if (src) {
                        fetch('/api.php?q=getBalance&address=' + encodeURIComponent(src))
                            .then(r => r.json())
                            .then(data => { if (data && data.data != null) this.signTxBalance = data.data; })
                            .catch(() => {});
                    }
                },

                formatAmount(val) {
                    if (val == null) return '0';
                    const n = Number(val);
                    return isNaN(n) ? String(val) : n.toFixed(8);
                },

                approveSignTx() {
                    if (!this.pendingSignTxRequest) return;
                    const keyToUse = this.signTxPrivateKey.trim();
                    if (!keyToUse) {
                        Swal.fire({ title: 'Private Key Required', text: 'Please enter your private key', icon: 'warning' });
                        return;
                    }
                    try {
                        this.pendingSignTxRequest.actions.approve(keyToUse);
                        this.statusMessage = 'Transaction signed';
                        this.pendingSignTxRequest = null;
                        this.currentView = 'initial';
                        if (window.opener) window.close();
                    } catch (err) {
                        Swal.fire({ title: 'Sign failed', text: err.message, icon: 'error' });
                    }
                },

                rejectSignTx() {
                    const actions = this.pendingSignTxRequest?.actions;
                    this.pendingSignTxRequest = null;
                    if (actions?.reject) actions.reject();
                    this.currentView = 'initial';
                    if (window.opener) window.close();
                },

                showLoginForApproval() {
                    // Show login options instead of request UI
                    this.currentView = 'initial';
                    this.statusMessage = 'Please login to approve the request from ' + this.requestDomain;
                },

                showPrivateKeyInput() {
                    // Load saved private key only when user explicitly requests to login
                    const savedPrivateKey = localStorage.getItem("privateKey");
                    if (savedPrivateKey) {
                        this.privateKey = savedPrivateKey;
                        this.rememberPrivateKey = true;
                    } else {
                        // Clear form if no saved key
                        this.privateKey = '';
                        this.rememberPrivateKey = true;
                    }
                    this.currentView = 'privateKeyForm';
                },

                approveWithPrivateKey() {
                    if (!this.pendingRequest) return;

                    const keyToUse = this.privateKey.trim();
                    if (!keyToUse) {
                        Swal.fire({
                            title: 'Private Key Required',
                            text: 'Please enter your private key',
                            icon: 'warning'
                        });
                        return;
                    }

                    // Validate private key
                    try {
                        const account = phpcoinCrypto.importPrivateKey(keyToUse);

                        // Handle remember checkbox - only store if checked
                        if (this.rememberPrivateKey) {
                            localStorage.setItem("privateKey", keyToUse);
                        }
                        // If unchecked, don't store (preserves existing stored key if any)

                        // Call approve action - pass private key directly
                        try {
                            this.pendingRequest.actions.approve(keyToUse);
                            this.statusMessage = 'Request approved';

                            this.currentView = 'initial';
                            this.pendingRequest = null;
                            // Close window if it's a popup
                            if (window.opener) {
                                window.close();
                            }
                        } catch (err) {
                            throw err;
                        }
                    } catch (err) {
                        Swal.fire({
                            title: 'Invalid Private Key',
                            text: err.message,
                            icon: 'error'
                        });
                    }
                },

                rejectRequest() {
                    const actions = this.pendingRequest?.actions;
                    this.pendingRequest = null;

                    if (actions?.reject) {
                        try {
                            actions.reject();
                        } catch (err) {
                            // Silently handle rejection errors
                        }
                    }

                    if (window.opener) {
                        window.close();
                    }
                },

                loginWithPrivateKey() {
                    if (!this.privateKey.trim()) {
                        Swal.fire({
                            title: 'Empty Private Key',
                            text: 'Please enter your private key',
                            icon: 'warning'
                        });
                        return;
                    }

                    try {
                        // Validate private key by importing it
                        const account = phpcoinCrypto.importPrivateKey(this.privateKey.trim());
                        const privateKeyToUse = this.privateKey.trim();

                        // Handle remember checkbox - only store if checked
                        if (this.rememberPrivateKey) {
                            localStorage.setItem("privateKey", privateKeyToUse);
                        }
                        // If unchecked, don't store (preserves existing stored key if any)

                        this.statusMessage = 'Logged in: ' + account.address.substring(0, 10) + '...';

                        // If there's a pending request, approve it automatically
                        if (this.pendingRequest) {
                            try {
                                // Pass private key directly to approve function
                                this.pendingRequest.actions.approve(privateKeyToUse);
                                this.statusMessage = 'Request approved';
                                this.currentView = 'initial';
                                this.pendingRequest = null;

                                // Close window if it's a popup after approval
                                if (window.opener) {
                                    window.close();
                                }
                            } catch (err) {
                                Swal.fire({
                                    title: 'Login error',
                                    text: err.message,
                                    icon: 'error'
                                });
                                // Show request UI if auto-approval fails
                                this.showRequestUI(this.pendingRequest.request, this.pendingRequest.actions);
                            }
                        } else {
                            // No pending request - show private key form again so user can continue
                            this.currentView = 'privateKeyForm';
                            this.statusMessage = 'Logged in: ' + account.address.substring(0, 10) + '... - Ready for requests';
                        }
                    } catch (err) {
                        Swal.fire({
                            title: 'Invalid Private Key',
                            text: err.message,
                            icon: 'error'
                        });
                    }
                },

                createNewAccount() {
                    try {
                        const account = phpcoinCrypto.generateAccount();
                        this.generatedAccount = account;
                        this.currentView = 'generatedAccount';
                        this.showPrivateKeySecret = false; // Start with hidden private key
                    } catch (err) {
                        Swal.fire({
                            title: 'Account Generation Failed',
                            text: err.message,
                            icon: 'error'
                        });
                    }
                },

                loginWithGeneratedAccount() {
                    if (!this.generatedAccount) return;

                    // Prefill private key form with generated account's private key
                    this.privateKey = this.generatedAccount.privateKey;
                    this.rememberPrivateKey = true;

                    // Hide generated account view and show private key form
                    this.generatedAccount = null;
                    this.showPrivateKeySecret = false;
                    this.currentView = 'privateKeyForm';

                    this.statusMessage = 'Enter your private key to continue';
                },

                closeGeneratedAccount() {
                    this.currentView = 'initial';
                    this.generatedAccount = null;
                    this.showPrivateKeySecret = false;
                },

                toggleSecret(event) {
                    event.preventDefault();
                    this.showPrivateKeySecret = !this.showPrivateKeySecret;
                },

                copyToClipboard(event) {
                    event.preventDefault();
                    if (!this.generatedAccount) return;

                    const text = this.generatedAccount.privateKey;
                    const textarea = document.createElement("textarea");
                    document.body.appendChild(textarea);
                    textarea.value = text;
                    textarea.select();
                    document.execCommand("copy");
                    document.body.removeChild(textarea);

                    // Show feedback
                    const originalText = event.target.textContent;
                    event.target.textContent = 'Copied!';
                    setTimeout(() => {
                        event.target.textContent = originalText;
                    }, 2000);
                },

                loadMultiwalletAccounts() {
                    try {
                        const storedData = localStorage.getItem(LOCAL_STORAGE_NAME);
                        const walletPassword = localStorage.getItem(LOCAL_STORAGE_PASSWORD);

                        if (!storedData || !walletPassword) {
                            return;
                        }

                        const decrypted = phpcoinCrypto.decryptString(storedData, walletPassword);
                        const walletData = JSON.parse(decrypted);

                        if (walletData && walletData.accounts && walletData.accounts.length > 0) {
                            this.multiwalletAccounts = walletData.accounts;
                            this.loadAccountBalances();
                        }
                    } catch (err) {
                        console.error("Failed to load multiwallet accounts:", err);
                    }
                },

                loadAccountBalances() {
                    if (this.multiwalletAccounts.length === 0) return;

                    const addresses = this.multiwalletAccounts.map(acc => acc.address);
                    const url = "/api.php?q=getBalances&addresses=" + encodeURIComponent(JSON.stringify(addresses));

                    fetch(url)
                        .then(response => response.json())
                        .then(data => {
                            if (data && data.data) {
                                const balanceMap = {};
                                data.data.forEach(item => {
                                    balanceMap[item.id] = item.balance;
                                });

                                this.multiwalletAccounts.forEach(account => {
                                    account.balance = balanceMap[account.address] || 0;
                                });
                            }
                        })
                        .catch(err => {
                            console.error("Failed to load balances:", err);
                        });
                },


                shortAddress(address) {
                    if (!address) return '';
                    return address.substring(0, 6) + '...' + address.substring(address.length - 6);
                },

                toggleAccountDropdown() {
                    this.showAccountDropdown = !this.showAccountDropdown;
                },

                loginWithMultiwallet(account) {
                    this.showAccountDropdown = false;
                    try {
                        this.privateKey = account.privateKey;
                        localStorage.setItem("privateKey", account.privateKey);
                        this.statusMessage = 'Logged in: ' + account.address.substring(0, 10) + '...';

                        // If there's a pending request, approve it automatically
                        if (this.pendingRequest) {
                            try {
                                // Pass the multiwallet account's private key
                                this.pendingRequest.actions.approve(account.privateKey);
                                this.statusMessage = 'Request approved';
                                this.currentView = 'initial';
                                this.pendingRequest = null;
                                // Close window if it's a popup after approval
                                if (window.opener) {
                                    window.close();
                                }
                            } catch (err) {
                                Swal.fire({
                                    title: 'Login error',
                                    text: err.message,
                                    icon: 'error'
                                });
                                // Show request UI if auto-approval fails
                                this.showRequestUI(this.pendingRequest.request, this.pendingRequest.actions);
                            }
                        } else {
                            // No pending request - show private key form again so user can continue
                            this.privateKey = account.privateKey;
                            this.currentView = 'privateKeyForm';
                            this.statusMessage = 'Logged in: ' + account.address.substring(0, 10) + '... - Ready for requests';
                        }
                    } catch (err) {
                        Swal.fire({
                            title: 'Login Failed',
                            text: err.message,
                            icon: 'error'
                        });
                    }
                }
            }
        }).mount('#app');

        console.log("PHP Coin wallet gateway ready to accept postMessage requests");
    </script>
</body>
</html>
