import { Wallet } from './common.js';
import { createApp, ref } from 'https://unpkg.com/vue@3/dist/vue.esm-browser.js';
import axios from 'https://cdn.jsdelivr.net/npm/axios@1.7.7/+esm'

let wallet = new Wallet();

$(()=>{
    if(wallet.password) {
        wallet.unlock(()=>{
            let account = wallet.fetchAccount($(window.document.body).data('address'));
            let el = $("#account-name");
            el.html(account.name).attr("data-bs-toggle","tooltip")
                .attr("data-bs-placement","bottom").attr("title", account.description)
            new window.bootstrap.Tooltip(el);

        })
    }
});

const chartColors= ["#777aca", "#8a8dce", "#9fa1d2", "#b3b5d7", "#c8c9db"];

createApp({
    setup() {
    },
    data() {
        return {
            chartColors: chartColors,
            chartData: null,
            selPeriod:'1W',
            chart: null,
            address: null
        }
    },
    mounted() {
        this.address = window.document.body.attributes['data-address'].value;
        this.loadWalletRewards('1W');
    },
    methods: {
        loadWalletRewards(period) {
            this.selPeriod = period;
            this.chartData = null;
            this.$refs.walletChart.style.display = 'block';
            axios.get('/apps/wallet2/api.php?q=getWalletRewards', {params: {period: this.selPeriod, address: this.address}}).then(res => {
                let data = res.data.data;
                let series = [];
                let labels = [];
                for(let item of data) {
                    series.push(item.amount);
                    labels.push(item.type);
                }
                this.chartData = data;
                let options = {
                    series,
                    chart: {
                        width: 227,
                        height: 227,
                        type: 'pie',
                    },
                    labels,
                    colors: chartColors,
                    stroke: {
                        width: 0,
                    },
                    legend: {
                        show: false
                    },
                    responsive: [{
                        breakpoint: 480,
                        options: {
                            chart: {
                                width: 200
                            },
                        }
                    }]
                };
                if(this.chart) {
                    this.chart.destroy();
                }
                this.chart = new window.ApexCharts(document.querySelector("#wallet-balance"), options);
                this.chart.render();
            })
        }
    },
}).mount('#wallet-rewards')


createApp({
    setup() {

    },
    data() {
        return {
            period: 'monthly',
            address: null,
            mnRoiData: null,
            periods: {
                daily: 'D',
                weekly: 'W',
                monthly: 'M',
                yearly: 'Y',
            },
            chart: null
        }
    },
    mounted() {
        this.address = window.document.body.attributes['data-address'].value;
        this.loadMasternodeRoi('monthly');
    },
    methods: {
        loadMasternodeRoi() {
            this.mnRoiData = null;
            this.$refs.mnRoi.style.visibility = 'visible';
            axios.get('/apps/wallet2/api.php?q=getWalletMnRoi', {params: {address: this.address}}).then(res => {
                this.mnRoiData = res.data.data;
                this.showChart(this.mnRoiData[this.period].roi)
            })
        },
        showChart(value) {
            let options = {
                chart: {
                    height: 270,
                    type: 'radialBar',
                    offsetY: -10
                },
                plotOptions: {
                    radialBar: {
                        startAngle: -130,
                        endAngle: 130,
                        dataLabels: {
                            name: {
                                show: false
                            },
                            value: {
                                offsetY: 10,
                                fontSize: '18px',
                                color: undefined,
                                formatter: function (val) {
                                    return val + "%";
                                }
                            }
                        }
                    }
                },
                colors: ['#5156be'],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shade: 'dark',
                        type: 'horizontal',
                        gradientToColors: ['#34c38f'],
                        shadeIntensity: 0.15,
                        inverseColors: false,
                        opacityFrom: 1,
                        opacityTo: 1,
                        stops: [20, 60]
                    },
                },
                stroke: {
                    dashArray: 4,
                },
                legend: {
                    show: false
                },
                series: [value]
            }
            if(this.chart) {
                this.chart.destroy();
            }
            this.chart = new window.ApexCharts(
                document.querySelector("#invested-overview"),
                options
            );
            this.chart.render();
        },
        switchPeriod(p) {
            this.period = p;
            this.showChart(this.mnRoiData[this.period].roi)
        }
    }
}).mount('#wallet-mn')



