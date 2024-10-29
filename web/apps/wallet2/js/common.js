const LOCAL_STORAGE_NAME = 'walletDataDev'
const LOCAL_STORAGE_PASSWORD = 'walletPasswordDev'
const LOCAL_STORAGE_CURRENT_ACCOUNT = 'walletCurrentAccount'

export const vTooltip = {
    mounted(el, binding) {
        // Ensure Bootstrap Tooltip is initialized correctly
        if (el instanceof HTMLElement) {
            el.setAttribute('title', binding.value);
            new bootstrap.Tooltip(el, {
                title: binding.value,
                placement: binding.arg || 'top'
            });
        }
    },
    updated(el, binding) {
        // Ensure Bootstrap Tooltip is updated correctly
        if (el._tooltip && el instanceof HTMLElement) {
            el.setAttribute('title', binding.value);
            el._tooltip.setContent({
                '.tooltip-inner': binding.value
            });
        }
    },
    unmounted(el) {
        // Ensure Bootstrap Tooltip is disposed correctly
        if (el._tooltip) {
            el._tooltip.dispose();
        }
    }
};

export function copyToClipboard(event, text) {

    let setCopied = (el) => {
        let tooltip = new bootstrap.Tooltip(el, {customClass: 'copy-tt'})
        tooltip.show();
        setTimeout(()=>{
            tooltip.hide();
        }, 1000)
    };

    event.target.setAttribute("title", "Copied!");

    if (!navigator.clipboard) {
        let sampleTextarea = document.createElement("textarea");
        document.body.appendChild(sampleTextarea);
        sampleTextarea.value = text; //save main text in it
        sampleTextarea.select(); //select textarea contenrs
        try {
            let successful = document.execCommand('copy');
            console.log("successful",successful, text)
            if(successful) {
                setCopied(event.target)
            }
        } catch (err) {
            console.error(err);
        }
        document.body.removeChild(sampleTextarea);
        return
    }
    navigator.clipboard.writeText(text).then(()=>{
        setCopied(event.target)
    })
}

export function confirmMsg(msg, cb) {
    Swal.fire({
        title: "Delete!",
        text: msg,
        icon: "warning",
        confirmButtonText: "Confirm",
        closeOnConfirm: false,
        showCancelButton: true,
        allowOutsideClick: false,
        focusCancel: true,
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            cb();
        }
    })
}

export class Wallet {

    constructor() {
        this.password = localStorage.getItem(LOCAL_STORAGE_PASSWORD);
        this.accounts = [];
        this.currentAddress = localStorage.getItem(LOCAL_STORAGE_CURRENT_ACCOUNT);
        this.noData = localStorage.getItem(LOCAL_STORAGE_NAME) === null
    }

    createWallet(password, savePass) {
        this.password = password;
        if(savePass) {
            localStorage.setItem(LOCAL_STORAGE_PASSWORD, password)
        }
        let data = {}
        let account = generateAccount();
        data.accounts = [account]
        let base = JSON.stringify(data)
        let encrypted = encryptString(base, password)
        localStorage.setItem(LOCAL_STORAGE_NAME, encrypted)
    }

    login(password,savePass) {
        this.password = password;
        let storedData = localStorage.getItem(LOCAL_STORAGE_NAME)
        let decrypted
        try {
            decrypted = decryptString(storedData, password)
        } catch (e) {
            Swal.fire(
                {
                    title: 'Wrong password',
                    text: 'Check if you entered password correctly',
                    icon: 'error'
                }
            )
            return false;
        }
        let walletData = JSON.parse(decrypted)
        if(!walletData || !walletData.accounts) {
            Swal.fire(
                {
                    title: 'Error loading wallet',
                    text: 'There is error loading wallet',
                    icon: 'error'
                }
            )
            return false;
        }
        this.walletData = walletData;
        if(savePass) {
            localStorage.setItem(LOCAL_STORAGE_PASSWORD, password)
        } else {
            localStorage.removeItem(LOCAL_STORAGE_PASSWORD);
        }
        return true;
    }

    getAddresses() {
        let addresses = [];
        for(let account of this.walletData.accounts) {
            addresses.push(account.address)
        }
        return addresses;
    }

    importAccounts(importText, password) {
        let decrypted
        try {
            decrypted = decryptString(importText, password);
        } catch (e) {
            Swal.fire(
                {
                    title: 'Import accounts failed',
                    text: 'Check if you entered password correctly and have correct export',
                    icon: 'error'
                }
            )
            return false;
        }
        localStorage.setItem(LOCAL_STORAGE_NAME, importText)
        return true;
    }

    storeCurrentAddress(address) {
        localStorage.setItem(LOCAL_STORAGE_CURRENT_ACCOUNT, address);
    }

    loadWalletData() {
        let storedData = localStorage.getItem(LOCAL_STORAGE_NAME)
        let decrypted;

        let error = () => {
            Swal.fire(
                {
                    title: 'Error',
                    text: 'Error loading accounts',
                    icon: 'error'
                }
            )
        }

        try {
            decrypted = decryptString(storedData, this.password)
        } catch (e) {
            error();
            return false;
        }
        this.walletData = JSON.parse(decrypted)
        return true;
    }

    unlock(cb) {

        if(this.password) {
            this.loadWalletData();
            cb();
            return;
        }


        Swal.fire({
            title: "Unlock wallet",
            html: `
    <p>Enter password to unlock wallet</p>
    <input type="password" class="form-control" id="password"/>
    <input class="form-check-input" type="checkbox" id="remember-password"/>
    <label class="form-check-label" for="remember-check">
    Remember password
    </label>
    </div>
  `,
            focusConfirm: false,
            allowOutsideClick: false,
            showCancelButton: true,
            preConfirm: () => {
                let password = document.getElementById("password").value;
                if(!password) {
                    return false;
                }
                let rememberPassword = document.getElementById("remember-password").checked;
                return [
                    password,
                    rememberPassword
                ];
            }
        }).then(result => {
            console.log(result);
            if(result.isConfirmed) {
                let password = result.value[0];
                let rememberPassword = result.value[1];
                try {
                    this.password = password;
                    if(this.loadWalletData()) {
                        if(rememberPassword) {
                            localStorage.setItem(LOCAL_STORAGE_PASSWORD, password)
                        }
                    }
                    cb();
                } catch (e) {
                    Swal.fire(
                        {
                            title: 'Error',
                            text: 'Error loading accounts',
                            icon: 'error'
                        }
                    )
                    cb();
                }
            } else {
                cb();
            }
        });
    }

    storeNewAccount(account) {
        this.walletData.accounts.push(account);
        this.storeWalletData();
    }

    deleteAccount(address) {
        for(let ix in this.walletData.accounts) {
            let account = this.walletData.accounts[ix];
            if(account.address === address) {
                this.walletData.accounts.splice(ix, 1);
                break;
            }
        }
        this.storeWalletData();
    }

    storeWalletData() {
        let base = JSON.stringify(this.walletData);
        let encrypted = encryptString(base, this.password);
        localStorage.setItem(LOCAL_STORAGE_NAME, encrypted);
    }

    updateAccount(editAccount) {
        for(let account of this.walletData.accounts) {
            if(account.address === editAccount.address) {
                account.name = editAccount.name;
                account.description = editAccount.description;
            }
        }
        this.storeWalletData();
    }

    generateAccountsBackup() {
        let data = {};
        data.accounts = this.walletData.accounts;
        let base = JSON.stringify(data)
        let encrypted = encryptString(base, this.password);
        return encrypted;
    }

    deleteAccounts() {
        localStorage.removeItem(LOCAL_STORAGE_NAME)
        localStorage.removeItem(LOCAL_STORAGE_PASSWORD)
        localStorage.removeItem(LOCAL_STORAGE_CURRENT_ACCOUNT)
    }

}
