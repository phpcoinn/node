const crypto = require('crypto')
const axios = require('axios')
const ellipticcurve = require("starkbank-ecdsa")
const Ecdsa = ellipticcurve.Ecdsa
const PrivateKey = ellipticcurve.PrivateKey
const Base58 = require("base-58")
const jsonKeySort = require("json-keys-sort")

function str_split (string, splitLength) { // eslint-disable-line camelcase
    //  discuss at: https://locutus.io/php/str_split/
    // original by: Martijn Wieringa
    // improved by: Brett Zamir (https://brett-zamir.me)
    // bugfixed by: Onno Marsman (https://twitter.com/onnomarsman)
    //  revised by: Theriault (https://github.com/Theriault)
    //  revised by: Rafał Kukawski (https://blog.kukawski.pl)
    //    input by: Bjorn Roesbeke (https://www.bjornroesbeke.be/)
    //   example 1: str_split('Hello Friend', 3)
    //   returns 1: ['Hel', 'lo ', 'Fri', 'end']
    if (splitLength === null) {
        splitLength = 1
    }
    if (string === null || splitLength < 1) {
        return false
    }
    string += ''
    const chunks = []
    let pos = 0
    const len = string.length
    while (pos < len) {
        chunks.push(string.slice(pos, pos += splitLength))
    }
    return chunks
}

function hex2coin(hash) {
    return Base58.encode(Buffer.from(hash, 'hex'))
}

function sortTx (tx) {
    return jsonKeySort.sort(tx)
}

function private_key_to_pem(private_key) {
    let private_key_bin = Base58.decode(private_key)
    // console.log(private_key_bin)
    let private_key_base64 = Buffer.from(private_key_bin).toString('base64');
    // console.log(private_key_base64)
    let private_key_pem = '-----BEGIN EC PRIVATE KEY-----\n'
        + str_split(private_key_base64, 64)
            .join('\n')
        + '\n-----END EC PRIVATE KEY-----\n'
    return private_key_pem
}

function sign (message, private_key) {
    // console.log(private_key)
    let private_key_pem = private_key_to_pem(private_key)

    let privateKey = PrivateKey.fromPem(private_key_pem)
    // console.log(privateKey)

    let signature = Ecdsa.sign(message, privateKey);
    let signature_b64 = signature.toBase64()
    // console.log(signature_b64)
    let signature_bin = Buffer.from(signature_b64, 'base64');
    // console.log(signature_bin)
    let signature_b58 = Base58.encode(signature_bin)
    // console.log(signature_b58)
    return signature_b58
}

let pem2coin = (pem) => {
    let pemB58 = pem.replace('-----BEGIN EC PRIVATE KEY-----','')
    pemB58 = pemB58.replace('-----END EC PRIVATE KEY-----','')
    pemB58 = pemB58.replace('-----BEGIN PUBLIC KEY-----','')
    pemB58 = pemB58.replace('-----END PUBLIC KEY-----','')
    pemB58 = pemB58.replace(/\n/g,'')
    pemB58 = Buffer.from(pemB58, 'base64')
    pemB58 = Base58.encode(pemB58)
    return pemB58
}

function get_public_key(private_key) {
    let private_key_pem = private_key_to_pem(private_key)
    let privateKey = PrivateKey.fromPem(private_key_pem)
    let publicKey = privateKey.publicKey();
    let publicKeyPem = publicKey.toPem()
    let publicKeyB58 = pem2coin(publicKeyPem)
    return publicKeyB58;
}

class WebMiner {

    constructor(node, address, options, callbacks) {
        this.node = node
        this.address = address
        this.hashingConfig = options.hashingConfig || {
            mem: 32768,
            parallelism: 1,
            time: 2
        }
        this.block_time = options.block_time || 60
        this.callbacks = callbacks
        this.cpu = options.cpu || 0
        this.updateUITimer = null
        this.mineInfoTimer = null
        this.break = false
        this.miner = null
        if(options.miningStat) {
            this.miningStat = options.miningStat
        } else {
            this.resetStat()
        }
        this.minerInfo = 'web'
        if(options.minerInfo) {
            this.minerInfo = options.minerInfo
        }

    }

    async start() {
        this.running = true
        this.updateUITimer = setInterval(()=>{
            this.callbacks.onMinerUpdate({miner: this.miner, miningStat: this.miningStat})
        }, 1000)
        this.mineInfoTimer = setInterval(()=>{
            axios({
                method: 'get',
                url: this.node + '/mine.php?q=info',
            }).then(response => {
                let info = response.data
                if (info.status === 'ok') {
                    let height = parseInt(info.data.height)
                    // console.log(`Node height ${height} we mine ${this.miner.height}`)
                    if(info.data.block !==  this.miner.block) {
                        // console.log(`New block detected - starting over`)
                        this.miningStat.dropped ++
                        this.break = true
                    }
                }
            })

        }, 10000)
        await this.loop()
    }

    stop() {
        this.running = false
        clearInterval(this.updateUITimer)
        clearInterval(this.mineInfoTimer)
    }

    sha256(pwd) {
        return crypto.createHash('sha256').update(pwd).digest('hex');
    }

    resetStat() {
        this.miningStat = {}
        this.miningStat.cnt = 0
        this.miningStat.hashes = 0
        this.miningStat.submits = 0
        this.miningStat.accepted = 0
        this.miningStat.rejected = 0
        this.miningStat.dropped = 0
    }

    updateCpu(cpu) {
        this.cpu = cpu
    }

    async loop() {

        let max = this.hexToDec('FFFFFFFF') * 1000

        while(this.running) {

            this.miningStat.cnt++

            let response = await axios({
                method: 'get',
                url: this.node + '/mine.php?q=info',
            })
            let info = response.data
            if (info.status !== 'ok') {
                console.error("Can not start Miner")
                return
            }
            let address = this.address
            let block_date = parseInt(info.data.date)
            let now = Math.round(Date.now() / 1000)
            let nodeTime = info.data.time
            let data = info.data.data
            let block = info.data.block
            let offset = nodeTime - now
            let elapsed = 0
            let new_block_date
            let argonBase
            let height = parseInt(info.data.height) + 1
            let difficulty = info.data.difficulty
            let argon, nonceBase, calcNonce, hitBase, hashPart, hitValue, hit, target
            let signatureBase, signature, json
            let submitResponse
            let calOffset = 0
            let blockFound = false
            let version = info.data.version
            let attempt = 0
            let speed = 0

            if(Array.isArray(data) && data.length === 0) {
                data = {}
            }

            this.miner = {
                address,
                block_date,
                now,
                nodeTime,
                offset,
                elapsed,
                new_block_date,
                calOffset,
                argon,
                nonceBase,
                calcNonce,
                height,
                difficulty,
                hitBase,
                hashPart,
                hitValue,
                hit,
                target,
                blockFound,
                data,
                signatureBase,
                signature,
                json,
                version,
                submitResponse,
                attempt,
                speed,
                block
            }
            this.callbacks.onMinerUpdate({miner:this.miner, miningStat: this.miningStat})

            let salt

            let t1 = Date.now()
            while(!blockFound) {


                if (this.break) {
                    this.break = false
                    break
                }

                attempt++

                this.miningStat.hashes++

                if(!this.running) {
                    this.miningStat.dropped ++
                    break
                }

                let t2 = Date.now()
                let diff = t2 - t1
                this.miner.speed = ( attempt / (diff / 1000)).toFixed(2)

                let ms = (100 - this.cpu) * 5
                await new Promise(resolve => setTimeout(resolve, ms));

                now = Math.round(Date.now() / 1000)
                elapsed = now + offset - block_date
                argonBase = `${block_date}-${elapsed}`
                new_block_date = block_date + elapsed

                this.miner.elapsed = elapsed
                this.miner.attempt = attempt
                this.miner.new_block_date = new_block_date

                salt = address.substr(0, 16)
                if(info.data.hashingOptions) {
                    let hashingOptions = info.data.hashingOptions
                    this.hashingConfig.mem = hashingOptions.memory_cost
                    this.hashingConfig.parallelism = hashingOptions.threads
                    this.hashingConfig.time = hashingOptions.time_cost
                    salt = crypto.randomBytes(8).toString('hex')
                    salt = Buffer.from(salt)
                }

                let hash = await argon2.hash({
                    pass: argonBase,
                    salt,
                    mem: this.hashingConfig.mem,
                        time: this.hashingConfig.time,
                        parallelism: this.hashingConfig.parallelism,
                        type: argon2.ArgonType.Argon2i,
                        hashLen: 32
                    })

                argon = hash.encoded
                nonceBase = `${address}-${block_date}-${elapsed}-${argon}`
                this.miner.argon = argon
                this.miner.nonceBase = nonceBase

                calcNonce = await this.sha256(nonceBase)

                hitBase = `${address}-${calcNonce}-${height}-${difficulty}`
                this.miner.calcNonce = calcNonce
                this.miner.hitBase = hitBase
                let hash1 = await this.sha256(hitBase)
                let hash2 = await this.sha256(hash1)

                hashPart = hash2.substr(0, 8)
                hitValue = this.hexToDec(hashPart)
                hit = Math.round(max / hitValue)
                target = Math.round(difficulty * this.block_time / elapsed)
                blockFound = (hit > 0 && target > 0 && hit > target);
                this.miner.hashPart = hashPart
                this.miner.hitValue = hitValue
                this.miner.hit = hit
                this.miner.target = target
                this.miner.blockFound = blockFound
            }

            if(!blockFound || elapsed<0) {
                continue
            }

            this.miningStat.submits++


            let postData = {
                argon,
                nonce: calcNonce,
                height,
                difficulty,
                address,
                date: new_block_date,
                data: JSON.stringify(data),
                elapsed,
                minerInfo: this.minerInfo
            }
            response = await axios({
                method: 'post',
                url: this.node + '/mine.php?q=submitHash',
                headers: {'Content-type': 'application/x-www-form-urlencoded'},
                data: new URLSearchParams(postData).toString()
            })
            if(response.data.status === 'ok') {
                this.miningStat.accepted ++
                if(this.callbacks.onAccepted) {
                    this.callbacks.onAccepted(response)
                }
            } else {
                this.miningStat.rejected ++
                if(this.callbacks.onRejected) {
                    this.callbacks.onRejected(response)
                }
            }
            submitResponse = response.data
            this.miner.submitResponse = submitResponse

            this.callbacks.onMinerUpdate({miner: this.miner, miningStat: this.miningStat})

            await new Promise(resolve => setTimeout(resolve, 3000));
        }
    }


    hexToDec(s) {
        let i, j, digits = [0], carry;
        for (i = 0; i < s.length; i += 1) {
            carry = parseInt(s.charAt(i), 16);
            for (j = 0; j < digits.length; j += 1) {
                digits[j] = digits[j] * 16 + carry;
                carry = digits[j] / 10 | 0;
                digits[j] %= 10;
            }
            while (carry > 0) {
                digits.push(carry % 10);
                carry = carry / 10 | 0;
            }
        }
        return digits.reverse().join('');
    }

    async getRewardTx(dst, val, date, public_key) {
        let tx = {
            dst, val,
            fee: "0.00000000",
            message: '',
            type: 0,
            date, public_key,
            src: dst
        }
        let sig = this.signTx(tx)
        tx.signature = sig
        let data = `${tx.val}-${tx.fee}-${tx.dst}-${tx.message}-${tx.type}-${tx.public_key}-${tx.date}-${tx.signature}`
        // console.log("data",data)
        let hash = await this.sha256(data)
        // console.log("hash_base64",hash)
        hash = hex2coin(hash)
        tx.id = hash
        // console.log(hash)
        tx = sortTx(tx)
        return tx
    }

    async getAddress() {
        let response = await axios({
            method: 'post',
            url: this.node + '/api.php?q=getAddress',
            headers: {'Content-type': 'application/x-www-form-urlencoded'},
            data: 'data=' + JSON.stringify({public_key: this.publicKey})
        })
        let info = response.data
        if(info.status !== 'ok') {
            return null
        } else {
            return info.data
        }
    }

    signTx(tx) {
        let data = `${tx.val}-${tx.fee}-${tx.dst}-${tx.message}-${tx.type}-${tx.public_key}-${tx.date}`
        // console.log("sign data", data)
        let sig = sign(data, this.privateKey)
        // console.log("sig", sig)
        return sig
    }

    async checkAddress(address) {
        let response = await axios({
            method: 'get',
            url: this.node + '/api.php?q=getPublicKey&address='+address,
        })
        let info = response.data
        if(info.status !== 'ok') {
            return null
        } else {
            return info.data
        }
    }

}

if(typeof window !== 'undefined')  {
    window.WebMiner = WebMiner
    window.sign = sign
    window.get_public_key = get_public_key
}

global.WebMiner = WebMiner
