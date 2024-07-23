function confirmMsg(title, text, cb) {
    Swal.fire({
        title,
        text,
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

function enterPrivateKey(cb) {
    let privateKey = localStorage.getItem("privateKey");
    if (!privateKey) {
        Swal.fire({
            title: "Sign transaction",
            html: `
    <p>Sign transaction with your private key</p>
    <input type="password" class="form-control" id="private_key"/>
    <input class="form-check-input" type="checkbox" id="remember-private-key"/>
    <label class="form-check-label" for="remember-private-key">
    Remember private key
    </label>
    </div>
  `,
            focusConfirm: false,
            allowOutsideClick: false,
            showCancelButton: true,
            preConfirm: () => {
                let privateKey = document.getElementById("private_key").value;
                if(!privateKey) {
                    return false;
                }
                let rememberKey = document.getElementById("remember-private-key").checked;
                return [
                    privateKey,
                    rememberKey
                ];
            }
        }).then(result => {
            if(result.isConfirmed) {
                let privateKey = result.value[0];
                let rememberKey = result.value[1];
                if(rememberKey) {
                    localStorage.setItem('privateKey', privateKey)
                }
                cb(privateKey);
            } else {
                cb(null);
            }
        });
    } else {
        cb(privateKey);
    }
}

let sendTransaction = (tx, signature_base, privateKey) => {
    console.log("sendTransaction", signature_base, privateKey);
    try {
        let signature = sign(chainId+signature_base, privateKey);
        if(signature) {
            tx.signature = signature;
            axios.post('/api.php?q=sendTransactionJson',tx).then(res=>{
                if(res.data.status === 'ok') {
                    let hash = res.data.data;
                    if(hash) {
                        Swal.fire(
                            {
                                title: 'Transaction sent',
                                text: 'Your transaction is added to mempool ['+hash+']',
                                icon: 'success'
                            }
                        )
                    } else{
                        Swal.fire(
                            {
                                title: 'Error sending transaction',
                                text: 'Error from API server when sending transaction',
                                icon: 'error'
                            }
                        )
                    }
                } else {
                    console.error(res.data.data);
                    Swal.fire(
                        {
                            title: 'Error sending transaction',
                            text: 'Error from API server when sending transaction: ' + res.data.data,
                            icon: 'error'
                        }
                    )
                }
            }).catch(err=>{
                Swal.fire(
                    {
                        title: 'Error sending transaction',
                        text: 'Error from API server when sending transaction',
                        icon: 'error'
                    }
                )
            });
        }
    } catch (e) {
        Swal.fire(
            {
                title: 'Error signing transaction',
                text: 'Check if your private key is correct',
                icon: 'error'
            }
        )
    }
};
