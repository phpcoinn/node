<?php if (!defined("INCLUDED")) {
    require_once __DIR__ . "/template.php";
    exit;
} ?>
<div id="app" class="p-3">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">
                    <i class="fa fa-address-book"></i>
                    Accounts
                </h4>

                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Wallet</a></li>
                        <li class="breadcrumb-item active">Accounts</li>
                    </ol>
                </div>

            </div>


            <div class="card" v-if="accounts.length">
                <div class="card-body">

                    <div class="row mb-3">
                        <div class="col-12">
                            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#create-account" @click="openCreateAccount">Create account</button>
                            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#import-account" @click="openImportAccount">Import account</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-light">
                            <tr>
                                <th></th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Balance</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="account in accounts" :key="account.address">
                                <td>
                                    <a href="" class="btn btn-sm btn-warning me-2" v-tooltip="'Edit'" @click="openEditAccount(account)"
                                       data-bs-toggle="modal" data-bs-target="#edit-account">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="" class="btn btn-sm btn-danger me-2" v-tooltip="'Delete'" @click.prevent="deleteAccount(account)">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <a href="" class="btn btn-sm btn-info" v-tooltip="'Export'" data-bs-toggle="modal" data-bs-target="#export-account"
                                       @click="openExportAccount(account)">
                                        <i class="fas fa-file-export"></i>
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex">
                                        <span>{{account.name}}</span>
                                        <span v-if="account.description" class="ms-auto" v-tooltip="account.description">
                                        <i class="fas fa-comment-alt cursor-pointer"></i>
                                    </span>
                                    </div>
                                </td>
                                <td>
                                    {{account.address}}
                                </td>
                                <td>
                                    {{balances[account.address]}}
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <button class="btn btn-secondary me-2" data-bs-toggle="modal" data-bs-target="#backup-accounts" @click="backupAccounts">Backup accounts</button>
                            <button class="btn btn-danger" @click="deleteAccountsAction">Delete accounts</button>
                        </div>
                    </div>
                </div>
            </div>




        <div class="modal fade" id="create-account" ref="createAccountModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel"
             style="display: none;" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">Create new account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            Following account will be added to wallet:
                        </div>
                        <div v-if="newAccount">
                            <account-info id="address" label="Address" :value="newAccount.address"></account-info>
                            <account-info id="public_key" label="Public key" :value="newAccount.publicKey"></account-info>
                            <account-info id="private_key" label="Private key" :value="newAccount.privateKey" hidden="true"></account-info>
                            <div class="mb-3">
                                <label class="form-label" for="formrow-name-input">Name:</label>
                                <input type="text" class="form-control" id="formrow-name-input" v-model="newAccount.name"/>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="formrow-desc-input">Description:</label>
                                <textarea class="form-control" id="formrow-desc-input" v-model="newAccount.description"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" @click="createAccount">Create</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="import-account" ref="importAccountModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel"
             style="display: none;" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">Import account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div v-if="importAccount">
                            <div class="mb-3">
                                <label class="form-label" for="formrow-pk-input">Private key:</label>
                                <textarea class="form-control" id="formrow-pk-input" v-model="importAccount.privateKey"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="formrow-name-input">Name:</label>
                                <input type="text" class="form-control" id="formrow-name-input" v-model="importAccount.name"/>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="formrow-desc-input">Description:</label>
                                <textarea class="form-control" id="formrow-desc-input" v-model="importAccount.description"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" @click="importAccountAction">Import</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="edit-account" ref="editAccountModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel"
             style="display: none;" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">Edit account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div v-if="editAccount">
                            <account-info id="address" label="Address" :value="editAccount.address"></account-info>
                            <account-info id="public_key" label="Public key" :value="editAccount.publicKey"></account-info>
                            <account-info id="private_key" label="Private key" :value="editAccount.privateKey" hidden="true"></account-info>
                            <div class="mb-3">
                                <label class="form-label" for="formrow-name-input">Name:</label>
                                <input type="text" class="form-control" id="formrow-name-input" v-model="editAccount.name"/>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="formrow-desc-input">Description:</label>
                                <textarea class="form-control" id="formrow-desc-input" v-model="editAccount.description"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" @click="saveAccountAction">Save</button>
                    </div>
                </div>
            </div>
        </div>

            <div class="modal fade" id="export-account" ref="exportAccountModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel"
                 style="display: none;" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="staticBackdropLabel">Export account</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div v-if="exportAccount">
                                {{exportAccount.type}}
                                <div class="mb-3">
                                    <label class="form-label" for="formrow-desc-input">Export type:</label>
                                    <select class="form-select" v-model="exportAccount.type" @change="exportAccountAction">
                                        <option value="">Select</option>
                                        <option value="wallet">Wallet</option>
                                        <option value="json">JSON</option>
                                        <option value="php">PHP</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex">
                                        <label class="form-label">Exported account:</label>
                                        <a href="" class="ms-auto" @click.prevent="copyExportToClipboard" v-if="exportAccount.exportText">
                                            Copy
                                        </a>
                                    </div>
                                    <textarea class="form-control" v-model="exportAccount.exportText" rows="10" readonly></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="backup-accounts" ref="backupAccountsModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel"
                 style="display: none;" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="staticBackdropLabel">Backup accounts</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div>
                                This is encoded backup off all accounts.
                                <br/>Your password is used to encode it.
                                <br/>
                                You can copy it and store in some file
                            </div>
                            <div class="d-flex">
                                <label class="form-label">Backup text:</label>
                                <a href="" class="ms-auto" @click.prevent="copyBackupToClipboard" v-if="backupAccountsText">
                                    Copy
                                </a>
                            </div>
                            <textarea class="form-control" v-model="backupAccountsText" rows="10" readonly></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/apps/common/js/phpcoin-crypto.js" type="text/javascript"></script>
<script type="module">
    import { createApp, defineComponent } from 'https://cdn.jsdelivr.net/npm/vue@3/dist/vue.esm-browser.js';
    import {vTooltip,
        copyToClipboard, confirmMsg, Wallet} from '/apps/wallet2/js/common.js';
    import axios from 'https://cdn.jsdelivr.net/npm/axios@1.7.2/+esm'

    let wallet = new Wallet();

    // Define the custom button component
    const CustomButton = defineComponent({
        template: `
        <button class="custom-button">
          <slot></slot>
        </button>
      `,
        name: 'CustomButton'
    });

    const AccountInfo = defineComponent({
        name: 'AccountInfo',
        data: function () {
            return {
                visibleHidden: false
            }
        },
        props: ['label', 'value', 'hidden', 'id'],
        computed: {
            textClass() {
                let s = 'text-break'
                if(this.hidden && !this.visibleHidden) {
                    s += ' hide'
                }
                return s
            },
            hiddenText() {
                return this.visibleHidden ? 'Hide' : 'Show'
            },
        },
        methods: {
            copyToClipboard(event) {
                let text = this.value
                if (!navigator.clipboard) {
                    let sampleTextarea = document.createElement("textarea");
                    document.body.appendChild(sampleTextarea);
                    sampleTextarea.value = text; //save main text in it
                    sampleTextarea.select(); //select textarea contenrs
                    try {
                        let successful = document.execCommand('copy');
                        console.log("successful",successful, text)
                        if(successful) {
                            this.setCopied(event.target)
                        }
                    } catch (err) {
                        console.error(err);
                    }
                    document.body.removeChild(sampleTextarea);
                    return
                }
                navigator.clipboard.writeText(text).then(()=>{
                    this.setCopied(event.target)
                })
            },
            setCopied(el) {
                console.log("setCopied", el)
                let tooltip = new bootstrap.Tooltip(el, {customClass: 'copy-tt'})
                tooltip.show();
                setTimeout(()=>{
                    tooltip.hide();
                }, 1000)
            },
            toggle() {
                this.visibleHidden = !this.visibleHidden
            }
        },
        template: `<div class="mb-2">
                        <div class="d-flex">
                            <div class="text-muted fw-bold">{{label}}</div>
                            <div class="ms-auto">
                                <a href="" :id="id" @click.prevent="copyToClipboard" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-trigger="manual" title="Copied!">Copy</a>
                                <a href="" @click.prevent="toggle" v-if="hidden"> | {{hiddenText}}</a>
                            </div>
                        </div>
                        <div :class="textClass">{{value}}</div>
                    </div>
`
    });

    // Create the main application
    const App = {
        components: {
            CustomButton, AccountInfo
        },
        data() {
            return {
                newAccount: null,
                accounts: [],
                balances: {},
                importAccount: null,
                editAccount: null,
                exportAccount: null,
                backupAccountsText: null

            }
        },
        mounted() {
            wallet.unlock(()=>{
                this.reload();
            });
        },
        methods: {
            reload() {
                this.accounts = wallet.walletData.accounts;
                this.fetchBalances();
            },
            openCreateAccount() {
                this.newAccount = generateAccount();
            },
            createAccount() {
                wallet.storeNewAccount(this.newAccount);
                let modal = bootstrap.Modal.getInstance(this.$refs.createAccountModal);
                modal.hide();
                this.storeAddresses();
                this.reload();
            },
            storeAddresses() {
                let addresses = wallet.getAddresses();
                console.log({addresses});
                axios.post('/apps/wallet2/api.php?q=setAddresses', {addresses});
            },
            fetchBalances() {
                let addresses = [];
                for(let account of this.accounts) {
                    addresses.push(account.address)
                }
                axios.post('/apps/wallet2/api.php?q=fetchBalances', {addresses}).then(res => {
                    this.balances = res.data.data;
                });
            },
            openImportAccount() {
                this.importAccount = {};
            },
            importAccountAction() {
                let account = importPrivateKey(this.importAccount.privateKey);
                if(!account) {
                    Swal.fire(
                        {
                            title: 'Can not import private key',
                            text: 'Check if private key is correct',
                            icon: 'error'
                        }
                    )
                    return
                }
                account.name = this.importAccount.name
                account.description = this.importAccount.description
                wallet.storeNewAccount(account);
                let modal = bootstrap.Modal.getInstance(this.$refs.importAccountModal)
                modal.hide();
                this.importAccount =  null;
                this.storeAddresses();
                this.reload();
            },
            openEditAccount(account) {
                this.editAccount = {...account};
            },
            saveAccountAction() {
                wallet.updateAccount(this.editAccount);
                let modal = bootstrap.Modal.getInstance(this.$refs.editAccountModal)
                modal.hide();
                this.editAccount = null;
                this.reload();
            },
            deleteAccount(account) {
                confirmMsg("Are you sure to want to delete this account?", ()=>{
                    wallet.deleteAccount(account.address);
                    this.storeAddresses();
                    this.reload();
                });
            },
            openExportAccount(account) {
                this.exportAccount = account;
            },
            exportAccountAction() {
                let exportText = null
                let type = this.exportAccount.type
                let account = {...this.exportAccount}
                delete account.name
                delete account.description
                delete account.balance
                if(type === 'wallet') {
                    exportText = `phpcoin\r${account.privateKey}\n${account.publicKey}`
                } else if (type === 'json' ) {
                    exportText = JSON.stringify(account, null, 4)
                } else if (type === 'php' ) {
                    exportText = `
$account=[
                        "address"=>"${account.address}",
                        "privateKey"=>"${account.privateKey}",
                        "publicKey"=>"${account.publicKey}",
];`
                }
                this.exportAccount.exportText = exportText
            },
            copyExportToClipboard(event){
                copyToClipboard(event, this.exportAccount.exportText);
            },
            backupAccounts() {
                this.backupAccountsText = wallet.generateAccountsBackup();
            },
            copyBackupToClipboard(event) {
                copyToClipboard(event, this.backupAccountsText);
            },
            deleteAccountsAction() {
                confirmMsg("Are you sure to want to delete all accounts? "+
                    "With this action all data stored in browser will be deleted. "+
                    "Make sure that you already save backup of accounts", ()=>{
                    wallet.deleteAccounts();
                    document.location.href='/apps/wallet2/index.php?action=logout'
                })
            }

        }

    };

    // Mount the application
    const app = createApp(App);
    app.directive('tooltip', vTooltip);
    app.mount('#app');
</script>


<style>
    .copy-tt .tooltip-inner{
        background-color: var(--bs-success);
    }
    .copy-tt .tooltip-arrow::before {
        border-top-color: var(--bs-success);
    }
    .hide {
        color: transparent !important;
        text-shadow: 0 0 8px rgba(0,0,0,0.5);
        user-select: none;
    }
</style>
