const crypto = require('crypto')
const axios = require('axios')
const Base58 = require("base-58")
const jsonKeySort = require("json-keys-sort")
const phpcoinCrypto = require("phpcoin-crypto")

const version = '1.3'

function hex2coin(hash) {
    return Base58.encode(Buffer.from(hash, 'hex'))
}

function sortTx (tx) {
    return jsonKeySort.sort(tx)
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
        this.sendStatTimer = null
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
        this.development = options.development
        this.minerid = Math.round(Date.now()/1000) + Math.random().toString(16).slice(2)
        this.prevHashes=0

        this.hashingTime = 0
        this.hashingCnt = 0
        this.speed = 0
        this.attempt = 0
        this.cpu = options.cpu
        this.sleepTime = (100 - this.cpu) * 5
    }

    measureSpeed(t1, th) {
        let t2 = Date.now()
        this.hashingCnt++
        this.hashingTime = this.hashingTime + (t2-th)
        let diff = (t2-t1)/1000
        this.speed = Number(this.attempt/diff).toFixed(2)
        let calcCount = Math.round(this.speed * 60)
        let mod = this.hashingCnt % calcCount
        if(mod === 0) {
            this.sleepTime = this.cpu === 0 ? Infinity : Math.round(((this.hashingTime/this.hashingCnt))*(100-this.cpu)/this.cpu)
            if(this.sleepTime < 0) {
                this.sleepTime = 0
            }
        }
        // console.log({cpu: this.cpu,t1,th,hashingCnt: this.hashingCnt,
        //     hashingTime: this.hashingTime,diff,speed:this.speed,calcCount,rem:this.hashingCnt/calcCount,sleepTime:this.sleepTime})
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
        this.sendStatTimer = setInterval(()=>{
            let address = this.address
            let hashes = this.miningStat.hashes - this.prevHashes
            this.prevHashes = this.miningStat.hashes
            let height = this.miner.height
            let postData = {address, minerid: this.minerid,cpu: this.cpu, hashes, height, interval: 60, miner_type: 'web-miner', minerInfo: this.minerInfo, version}
            axios({
                method: 'post',
                url: this.node + '/mine.php?q=submitStat',
                headers: {'Content-type': 'application/x-www-form-urlencoded'},
                data: new URLSearchParams(postData).toString()
            }).then(response => {
            })
        }, 60000)
        await this.loop()
    }

    stop() {
        this.running = false
        clearInterval(this.updateUITimer)
        clearInterval(this.mineInfoTimer)
        clearInterval(this.sendStatTimer)
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
        this.prevHashes = 0
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
            let block = info.data.block
            let chain_id = info.data.chain_id
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
            this.attempt = 0
            let speed = 0

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
                signatureBase,
                signature,
                json,
                version,
                submitResponse,
                attempt:this.attempt,
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

                this.attempt++

                if(this.sleepTime === Infinity) {
                    this.running = false
                    break
                }

                this.miningStat.hashes++

                if(!this.running) {
                    this.miningStat.dropped ++
                    break
                }

                let t2 = Date.now()
                let diff = t2 - t1

                await new Promise(resolve => setTimeout(resolve, this.sleepTime));

                now = Math.round(Date.now() / 1000)
                elapsed = now + offset - block_date
                argonBase = `${block_date}-${elapsed}`
                new_block_date = block_date + elapsed

                this.miner.elapsed = elapsed
                this.miner.attempt = this.attempt
                this.miner.new_block_date = new_block_date
                this.miner.height = height

                salt = address.substr(0, 16)
                if(info.data.hashingOptions) {
                    let hashingOptions = info.data.hashingOptions
                    this.hashingConfig.mem = hashingOptions.memory_cost
                    this.hashingConfig.parallelism = hashingOptions.threads
                    this.hashingConfig.time = hashingOptions.time_cost
                    salt = crypto.randomBytes(8).toString('hex')
                    salt = Buffer.from(salt)
                }

                let th = Date.now()
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
                nonceBase = `${chain_id}${address}-${block_date}-${elapsed}-${argon}`
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
                if(this.development && target > 100) target = 100
                blockFound = (hit > 0 && target > 0 && hit > target);
                this.miner.hashPart = hashPart
                this.miner.hitValue = hitValue
                this.miner.hit = hit
                this.miner.target = target
                this.miner.blockFound = blockFound

                this.measureSpeed(t1, th)
                this.miner.speed = this.speed
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
                elapsed,
                minerInfo: this.minerInfo,
                version
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
        let sig = phpcoinCrypto.sign(data, this.privateKey)
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
    window.sign = phpcoinCrypto.sign
}

global.WebMiner = WebMiner
