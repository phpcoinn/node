importScripts('/apps/common/js/argon2-bundled.min.js');
importScripts('/apps/common/js/web-miner.js?t='+Date.now());


let webMiner
let miner, miningStat

self.addEventListener('message', function(e) {
    let cmd = e.data.cmd
    if(cmd === 'INIT') {
        let node = e.data.params.node
        let address = e.data.params.address
        let options = e.data.params.options

        webMiner = new WebMiner(node, address, options, {
            onMinerUpdate(data) {
                self.postMessage({cmd:'EVENT', event: 'onMinerUpdate', data});
            },
            onAccepted(response) {
                self.postMessage({cmd:'EVENT', event: 'onAccepted', data: JSON.stringify(response)})
            },
            onRejected(response) {
                self.postMessage({cmd:'EVENT', event: 'onRejected', data: JSON.stringify(response)})
            },
            saveStat(data) {
                self.postMessage({cmd:'EVENT', event: 'saveStat', data});
            }
        })
    }
    if(cmd === 'START') {
        webMiner.start()
        self.postMessage({cmd:'STARTED'});
    }
    if(cmd === 'STOP') {
        webMiner.stop()
    }
    if(cmd === "LOAD_STAT") {
        if(e.data.data) {
            try {
                webMiner.miningStat = JSON.parse(e.data)
            } catch (e) {
            }
        }
    }
    if(cmd === 'checkAddress') {
        let address = e.data.params.address
        webMiner.checkAddress(address).then(response => {
            self.postMessage({cmd:'checkAddressResponse', response});
        })
    }
    if(cmd === 'updateCpu') {
        let cpu = e.data.params.cpu
        webMiner.updateCpu(cpu)
    }
    if(cmd === 'resetStat') {
        webMiner.resetStat()
    }
}, false);




