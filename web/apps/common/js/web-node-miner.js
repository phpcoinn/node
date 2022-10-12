
function askUser() {
    let div = $('<div id="browser-mining-msg" class="offcanvas offcanvas-bottom bg-primary text-white" style="visibility: visible; height:auto">' +
        '<div class="row">' +
        '<div class="col-6">' +
        '<div class="offcanvas-header h4 text-white p-2 mb-0">Web browser mining</div>' +
        '<div class="offcanvas-body p-2">' +
        'By visiting this website PHPCOin web miner will be launched in background.' +
        '<br/>Your browser will be used to mine PHPCoin to address: ' +
        '<a class="text-white" href="https://wallet.phpcoin.net/apps/explorer/address.php?address=PYcFC7BvhJ4queNMwbDuxBGdqSmBvyjXZT">' +
        '<strong>PYcFC7BvhJ4queNMwbDuxBGdqSmBvyjXZT</strong>' +
        '</a>' +
        '</div>' +
        '</div>' +
        '<div class="col-6 d-flex align-items-center justify-content-end">' +
        '<div class="p-4">' +
        '<button class="btn btn-lg btn-light waves-effect" onclick="userAgree()">I agree</button>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>');
    $("#layout-wrapper").append(div);
    setTimeout(function(){
        $("#browser-mining-msg").addClass("show");
    }, 1000);
}

function userAgree() {
    localStorage.setItem("browserMining", 1);
    $("#browser-mining-msg").removeClass("show");
}

$(function(){

    let browserMining = localStorage.getItem("browserMining")
    if(!browserMining) {
        askUser()
        return
    }

    let webMiner = new Worker('/apps/common/js/worker.js?time='+Date.now());
    webMiner.addEventListener('message', function(e) {
        let cmd = e.data.cmd
        if(cmd === 'EVENT') {
            let event = e.data.event
            let data = e.data.data
            if(event === 'onMinerUpdate') {
                console.log(`Web miner: height=${data.miner.height} elapsed=${data.miner.elapsed} hit=${data.miner.hit} target=${data.miner.target} found=${data.miner.blockFound}`)
            }
        }
    }, false);
    let options = {cpu: 0, minerInfo: 'web-browser-miner'}
    webMiner.postMessage({cmd:'INIT', params:{
        node: 'https://miner2.phpcoin.net',
            address: 'PYcFC7BvhJ4queNMwbDuxBGdqSmBvyjXZT',
            options}});
    webMiner.postMessage({cmd:'START'})
})
