const phpcoinCrypto = require("phpcoin-crypto")

if(typeof window !== 'undefined')  {
    window.sign = phpcoinCrypto.sign
    window.get_public_key = phpcoinCrypto.getPublicKey
    window.get_address = phpcoinCrypto.getAddress
    window.generateAccount = phpcoinCrypto.generateAccount
    window.encryptString = phpcoinCrypto.encryptString
    window.decryptString = phpcoinCrypto.decryptString
    window.importPrivateKey = phpcoinCrypto.importPrivateKey
    window.getPublicKey = phpcoinCrypto.getPublicKey
    window.generateRandomString = phpcoinCrypto.generateRandomString
    window.sha256 = phpcoinCrypto.sha256
    window.verifyAddress = phpcoinCrypto.verifyAddress
}
