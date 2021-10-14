<?php
require_once dirname(__DIR__)."/apps.inc.php";
if(!Nodeutil::miningEnabled()) {
    header("location: /apps/explorer");
    exit;
}
define("PAGE", true);
define("APP_NAME", "Miner");
?>
<?php
require_once __DIR__. '/../common/include/top.php';
?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/c3/0.7.20/c3.min.css" integrity="sha512-cznfNokevSG7QPA5dZepud8taylLdvgr0lDqw/FEZIhluFsSwyvS81CMnRdrNSKwbsmc43LtRd2/WMQV+Z85AQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<div id="app">

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Web mining</h4>
                <p class="card-title-desc">Enter your address to start mining</p>
            </div>
            <div class="card-body p-0">
                <form id="miningConfig" class="collapse show p-4">
                    <div class="mb-1">
                        <label class="form-label" for="node">Node</label>
                        <input class="form-control" type="text" id="node" v-model="node" disabled="disabled"/>
                    </div>
                    <div class="mb-1">
                        <label class="form-label" for="public_key">Address</label>
                        <input class="form-control" type="text" id="public_key" v-model="address" placeholder="Enter your address"/>
                        <div class="help-block text-muted text-info">
                            In order to mine with address you must have recorded sent transaction on blockchain
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="bgrunning" v-model="minerInBackground">
                            <label class="form-check-label" for="bgrunning">Run in background</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-transparent border-top text-muted">
                <button class="btn btn-success" @click="startMiner" v-if="!running">Start</button>
                <button class="btn btn-danger"  @click="stopMiner" v-if="running">Stop</button>
                <span v-if="running" class="h5 ms-2">
                    Mining address: {{address}}
                </span>
            </div>
        </div>

        <div class="row" v-if="running">
            <div class="col-sm-7">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Mining chart</h4>
                    </div>
                    <div class="card-body">
                        <div id="chart"></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-5">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Mining stat</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-4">
                                <div class="card bg-primary border-primary">
                                    <div class="card-body h5 mb-0 p-2 text-white-50 d-flex justify-content-between">
                                        <span>Block</span>
                                        <span class="text-white font-weight-bolder">{{miner.height}}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="card bg-primary border-primary">
                                    <div class="card-body h5 mb-0 p-2 text-white-50 d-flex justify-content-between">
                                        <span>Elapsed</span>
                                        <span class="text-white font-weight-bolder">{{miner.elapsed}}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-4">
                                <div class="card bg-primary border-primary">
                                    <div class="card-body h5 mb-0 p-2 text-white-50 d-flex justify-content-between">
                                        <span>Hit</span>
                                        <span class="text-white font-weight-bolder">{{miner.hit}}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="card bg-primary border-primary">
                                    <div class="card-body h5 mb-0 p-2 text-white-50 d-flex justify-content-between">
                                        <span>Target</span>
                                        <span class="text-white font-weight-bolder">{{miner.target}}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-4">
                                <div class="card bg-primary border-primary">
                                    <div class="card-body h5 mb-0 p-2 text-white-50 d-flex justify-content-between">
                                        <span>Rounds</span>
                                        <span class="text-white font-weight-bolder">{{miningStat.cnt}}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-4">
                                <div class="card bg-primary border-primary">
                                    <div class="card-body h5 mb-0 p-2 text-white-50 d-flex justify-content-between">
                                        <span>Hashes</span>
                                        <span class="text-white font-weight-bolder">{{miningStat.hashes}}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-4">
                                <div class="card bg-primary border-primary">
                                    <div class="card-body h5 mb-0 p-2 text-white-50 d-flex justify-content-between">
                                        <span>Submits</span>
                                        <span class="text-white font-weight-bolder">{{miningStat.submits}}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-4">
                                <div class="card bg-success border-success">
                                    <div class="card-body h5 mb-0 p-2 text-white-50 d-flex justify-content-between">
                                        <span>Accepted</span>
                                        <span class="text-white font-weight-bolder">{{miningStat.accepted}}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-4">
                                <div class="card bg-danger border-danger">
                                    <div class="card-body h5 mb-0 p-2 text-white-50 d-flex justify-content-between">
                                        <span>Rejected</span>
                                        <span class="text-white font-weight-bolder">{{miningStat.rejected}}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-4">
                                <div class="card bg-warning border-warning">
                                    <div class="card-body h5 mb-0 p-2 text-white-50 d-flex justify-content-between">
                                        <span>Dropped</span>
                                        <span class="text-white font-weight-bolder">{{miningStat.dropped}}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-top text-muted">
                        <button class="btn btn-warning" @click="resetStats">Reset stats</button>
                    </div>
                </div>
            </div>
        </div>

        <?php if(false) { ?>
            <br/>
            {{miningStat}}
            <table>
                <template v-for="val, key in miner">
                    <tr>
                        <td>{{key}}</td>
                        <td>{{val}}</td>
                    </tr>
                </template>
            </table>
        <?php } ?>
	</div>
	<script src="/apps/miner/js/vue.js"></script>
    <script src="/apps/miner/js/argon2-browser/lib/argon2.js"></script>
	<script src="/apps/miner/js/web-miner.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/d3/5.16.0/d3.min.js" integrity="sha512-FHsFVKQ/T1KWJDGSbrUhTJyS1ph3eRrxI228ND0EGaEp6v4a/vGwPWd3Dtd/+9cI7ccofZvl/wulICEurHN1pg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/c3/0.7.20/c3.min.js" integrity="sha512-+IpCthlNahOuERYUSnKFjzjdKXIbJ/7Dd6xvUp+7bEw0Jp2dg6tluyxLs+zq9BMzZgrLv8886T4cBSqnKiVgUw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script type="text/javascript">


        let hashingConfig = {
            mem: <?php echo HASHING_OPTIONS['memory_cost'] ?>,
            time: <?php echo HASHING_OPTIONS['time_cost'] ?>,
            parallelism: <?php echo HASHING_OPTIONS['threads'] ?>
        }

        var app = new Vue({
            el: '#app',
            data: {
                address: null,
                privateKey: null,
	            node: '<?php echo $_config['hostname'] ?>',
                miner: {},
                running: false,
                miningStat: {},
                chart : null,
                webMiner: null,
                minerInBackground: false
            },
            mounted() {
                this.minerInBackground = localStorage.getItem('minerInBackground')!= null &&
                    localStorage.getItem('minerInBackground') === "1"
                if(this.minerInBackground) {
                    this.startMiner()
                }
            },
            methods: {
                setupMiner() {
                    this.webMiner = new WebMiner(this.node,
                        this.address, hashingConfig, <?php echo BLOCK_TIME ?> , {
                            onMinerUpdate: (miner, miningStat)=>{
                                this.miner = miner
                                this.miningStat = miningStat
                                if(miner.attempt === 0) {
                                    this.resetChart(miner.block_date)
                                } else {
                                    this.updateChart(miner)
                                }
                            }
                        })
                },
                stopMiner() {
                    this.running = false
                    $("#miningConfig").collapse('show')
                    this.webMiner.stop()
                    if(this.minerInBackground) {
                        this.minerInBackground = false
                        localStorage.removeItem('minerInBackground')
                    }
                },
                async startMiner() {
                    if(this.minerInBackground) {
                        localStorage.setItem('minerInBackground', "1");
                    }
                    this.address = this.address.trim()
                    if(!this.address) {
                        Swal.fire(
                            {
                                title: 'Address required!',
                                text: 'Please fill valid address',
                                icon: 'error'
                            }
                        )
                        return
                    }
                    this.setupMiner()

                    if(!await this.webMiner.checkAddress(this.address)) {
                        Swal.fire(
                            {
                                title: 'Address not found',
                                text: 'You must have recorded sent transaction on blockchain in order to start mining',
                                icon: 'error'
                            }
                        )
                        return
                    }
                    this.running = true
                    $("#miningConfig").collapse('hide')
                    await this.webMiner.start()
                },
                resetChart(start) {
                    this.chart = c3.generate({
                        bindto: '#chart',
                        data: {
                            x: 'x',
                            columns: [
                                ['x'],
                                ['hit'],
                                ['target']
                            ],
                            xFormat: null,
                            types: {
                                hit: 'step'
                            }
                        },
                        axis: {
                            x: {
                                type: "timeseries",
                                tick: {
                                    format: (x) => {
                                        return Math.round(x.getTime()/1000) - start;
                                    }
                                }
                            }
                        },
                        grid: {
                            x: {
                                lines: [
                                    {value: (start + <?php echo BLOCK_TIME ?>)*1000, text: 'Target time'}
                                ]
                            }
                        },
                        point: {
                            show: false
                        }
                    });
                },
                updateChart(miner) {
                    this.chart.flow({
                        columns: [
                            ['x', Date.now()],
                            ['hit', miner.hit],
                            ['target', miner.target]
                        ],
                        length:0,
                        duration: 0
                    })
                },
                resetStats() {
                    if(!confirm('Confirm?')) {
                        return
                    }
                    this.webMiner.resetStat()
                }
            }
        })


    </script>

<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
