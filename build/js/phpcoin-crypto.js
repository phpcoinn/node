const phpcoinCrypto = require("phpcoin-crypto")

if(typeof window !== 'undefined')  {
    window.sign = phpcoinCrypto.sign
    window.get_public_key = phpcoinCrypto.getPublicKey
    window.get_address = phpcoinCrypto.getAddress
    window.generateAccount = phpcoinCrypto.generateAccount
}
