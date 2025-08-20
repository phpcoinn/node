window.pajaxUrl = '/apps/p2p/index.php'
window.pBeforeSend = (formData) => {
    //Pace.restart();
}
window.pOnComplete = () => {
    //Pace.stop();
}
window.pOnError = (json) => {
    showError('There was an error performing your action')
}
function showLoading() {
    $('#offer-modal .modal-body').html('Loading <span class="fa fa-spin fa-spinner"/>')
}
function showError(msg) {
    Swal.fire({
        title: 'Alert',
        text: msg,
        icon: 'error'
    })
}
function showSuccess(msg) {
    Swal.fire({
        title: 'Success',
        text: msg,
        icon: 'success'
    })
}
function closeOfferModal() {
    bootstrap.Modal.getInstance(document.getElementById('offer-modal')).hide();
}
function openOffer(id) {
    paction(null, 'openOffer', id, {view:'#p2p-app', update: '#offer-modal .modal-content', beforeSend: () => {
            showLoading();
        }, afterUpdate: () => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('offer-modal')).show();
    }})
}

function copyToClipboard(event, text) {
    let setCopied = (el) => {
        el._tooltip = new bootstrap.Tooltip(el, {customClass: 'copy-tt'})
        el._tooltip.show();
        setTimeout(()=>{
            el._tooltip && el._tooltip.dispose();
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
function transferWithMetamask(total, usdtAddress, recipient, callback) {
    if (window.ethereum == null) {
        showError("MetaMask not installed");
        stopWaitProcess();
        return;
    }
    window.provider = new ethers.BrowserProvider(window.ethereum);
    provider.getSigner().then(signer => {
        const decimals = 6;
        const amount = ethers.parseUnits(`${total}`, decimals);
        const usdtAbi = [
            "function transfer(address to, uint amount) returns (bool)"
        ];
        const usdtContract = new ethers.Contract(usdtAddress, usdtAbi, signer);
        console.log(usdtAddress,recipient,amount,usdtContract)
        usdtContract.transfer(recipient, amount).then(tx => {
            console.log(tx)
            paction(event, callback, [tx], {view: '#p2p-app'});
        }).catch(err => {
            console.error(err)
            showError("Error creating wallet transaction");
            stopWaitProcess();
        })
    }).catch(err => {
        console.error(err)
        showError("Error connecting metamask");
        stopWaitProcess();
    })
}

function selectOffer(event, id, type) {
    paction(event, 'selectOffer', id, {process: ['trade-cards','offers-book'], update: ['#trade-cards','#offers-book'], afterUpdate: (data)=>{
        if(window.isMobile) {
            if(type === 'sell') {
                openBuyCard()
            } else {
                openSellCard()
            }
        }
    }})
}

function changedSellBasePrice(decimals) {
    let sell_base_amount = parseFloat($('#sell_base_amount').val())
    let sell_base_price = parseFloat($('#sell_base_price').val())
    let min_price =  parseFloat($('#sell_base_price').data('min-price'))
    if(sell_base_price && sell_base_price < min_price) {
        showError('You can not set price less then best buy offer!')
        $('#sell_base_price').val(min_price)
        return
    }
    if(sell_base_price && sell_base_amount) {
        let sell_quote_total = Number(sell_base_amount * sell_base_price).toFixed(decimals)
        $('#sell_quote_total').val(sell_quote_total)
    }
}
function changedSellQuoteTotal() {
    let sell_quote_total = parseFloat($('#sell_quote_total').val())
    let sell_base_price = parseFloat($('#sell_base_price').val())
    if(sell_base_price && sell_quote_total) {
        let sell_base_amount = sell_quote_total / sell_base_price
        $('#sell_base_amount').val(sell_base_amount)
    }
}
function changedBuyBasePrice(decimals) {
    let buy_base_amount = parseFloat($('#buy_base_amount').val())
    let buy_base_price = parseFloat($('#buy_base_price').val())
    let max_price =  parseFloat($('#buy_base_price').data('max-price'))
    if(buy_base_price && buy_base_price > max_price) {
        showError('You can not set price grater then best sell offer!')
        $('#buy_base_price').val(max_price)
        return
    }
    if(buy_base_price && buy_base_amount) {
        let buy_quote_total = Number(buy_base_amount * buy_base_price).toFixed(decimals)
        $('#buy_quote_total').val(buy_quote_total)
    }
}
function changedBuyQuoteTotal() {
    let buy_quote_total = parseFloat($('#buy_quote_total').val())
    let buy_base_price = parseFloat($('#buy_base_price').val())
    if(buy_base_price && buy_quote_total) {
        let buy_base_amount = buy_quote_total / buy_base_price
        $('#buy_base_amount').val(buy_base_amount)
    }
}

function displayChart() {

    console.log('displayChart', window.tvChart)

    if(window.tvChart) {
        return;
    }

    const chartContainer = document.getElementById('chart');
    const myPriceFormatter = p => p.toFixed(8);
    const chart = LightweightCharts.createChart(chartContainer, {
        layout: {backgroundColor: '#fff', textColor: '#333'},
        grid: {vertLines: {color: '#eee'}, horzLines: {color: '#eee'}},
        timeScale: {timeVisible: true, secondsVisible: true}
        ,localization: {
            priceFormatter: myPriceFormatter,
        }
    });
    window.tvChart = chart

    let candleSeries = chart.addCandlestickSeries();
    let lineSeries = chart.addLineSeries();
    chart.lineSeries = lineSeries
    chart.candleSeries = candleSeries

    let params = {interval: '1d'}

    loadChartData(params, (data)=>{
        console.log({data})
        lineSeries.setData(data.lines)
        candleSeries.setData(data.candles)
    })

}

function loadChartData(params, cb) {
    paction(null, 'getChartData', [params], {view: '#p2p-app', update: '#chart', onComplete: (json) => {
            cb(json)
    }});
}

function changeChartInterval(val) {
    loadChartData({interval: val},(data)=>{
        window.tvChart.lineSeries.setData(data.lines)
        window.tvChart.candleSeries.setData(data.candles)
    })
}

function toggleOffers() {
    $('#offers-card').show()
    $('#market-chart').hide()
    $('#markets-card').hide()
    $('#trades-card').hide()
    $('#offers-switch-offers').addClass('active')
    $('#offers-switch-chart').removeClass('active')
}
function toggleChart() {
    $('#offers-card').hide()
    $('#market-chart').show()
    $('#markets-card').hide()
    $('#trades-card').hide()
    window.tvChart.resize($('#chart').width(),$('#chart').height())
    $('#offers-switch-offers').removeClass('active')
    $('#offers-switch-chart').addClass('active')
}
function toggleMarkets() {
    $('#offers-card').hide()
    $('#market-chart').hide()
    $('#trades-card').hide()
    $('#markets-card').show()
}
function toggleTrades() {
    $('#offers-card').hide()
    $('#market-chart').hide()
    $('#markets-card').hide()
    $('#trades-card').show()
}

function toggleSell() {
    $("#body-sell").show();
    $("#body-buy").hide();
}

function toggleBuy() {
    $("#body-sell").hide();
    $("#body-buy").show();
}

function openBuyCard() {
    $("#buy-card").addClass("open").addClass("scale-up-ver-bottom")
    $("#sell-card").removeClass("open")
    $("body .container").css("padding-bottom", $("#buy-card").height())
}

function openSellCard() {
    $("#sell-card").addClass("open").addClass("scale-up-ver-bottom")
    $("#buy-card").removeClass("open")
    $("body .container").css("padding-bottom", $("#sell-card").height())
}

function closeBuyCard() {
    $("#buy-card").removeClass("open")
    $("body .container").css("padding-bottom", 0)
}

function closeSellCard() {
    $("#sell-card").removeClass("open")
    $("body .container").css("padding-bottom", 0)
}

function startWaitForProcess() {
    $("body").addClass("wait-process-body");
}
function stopWaitProcess() {
    $("body").removeClass("wait-process-body");
}
function openHistoryTab() {
    $('#my-offers-card .nav-link').each(function() {
       $(this).removeClass('active')
    });
    $('#my-offers-card .tab-pane').each(function() {
       $(this).removeClass('active')
    });
    $("#my-offers-card .nav-link[href='#offers-history']").addClass('active')
    $('#my-offers-card .tab-pane#offers-history').addClass('active')
}
function flashOfferRow(id) {
    $('#my-offers-card .tab-pane#offers-history tr[data-offer-id="'+id+'"]').addClass('flash')
    setTimeout(()=>{
        $('#my-offers-card .tab-pane#offers-history tr[data-offer-id="'+id+'"]').removeClass('flash')
    }, 2500)
}

function focusOffer(id) {
    stopWaitProcess()
    openHistoryTab()
    flashOfferRow(id)
    openOffer(id)
}

function cancelOffer(event, id) {
    startWaitForProcess();
    paction(event, 'cancelOffer', [id], {onComplete: stopWaitProcess})
}
function depositFromWallet(event) {
    startWaitForProcess();
    paction(event, 'depositFromWallet', {}, {update: '#offer-modal .modal-content'})
}
function transferFromWallet(event) {
    startWaitForProcess();
    paction(event, 'transferFromWallet', {}, {update: '#offer-modal .modal-content'})
}

function createBuyOffer(event) {
    paction(event, 'createBuyOffer', {}, {beforeSend: startWaitForProcess, onComplete: stopWaitProcess})
}
function createSellOffer(event) {
    paction(event, 'createSellOffer', {}, {beforeSend: startWaitForProcess, onComplete: stopWaitProcess})
}

function acceptBuyOffer(event, id) {
    paction(event, 'acceptBuyOffer', id, {beforeSend: startWaitForProcess, onComplete: stopWaitProcess} )
}
function acceptSellOffer(event, id) {
    paction(event, 'acceptSellOffer', id, {beforeSend: startWaitForProcess, onComplete: stopWaitProcess} )
}