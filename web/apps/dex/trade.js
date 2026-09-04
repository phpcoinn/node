(function () {
    const cfg = window.DEX_MARKET || {};
    const publicKey = window.DEX_PUBLIC_KEY;
    const scAddress = cfg.scAddress;
    const selectedToken = cfg.token;
    const dexMulti = !!cfg.multi;
    const myAddress = cfg.address || '';
    const symbol = cfg.symbol || 'TOKEN';
    const storageKey = 'phpcoin-dex-jobs-' + (selectedToken || scAddress || 'dex');
    const jobs = [];
    let sessionPk = null;
    let running = {};

    function sleep(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    function showError(title, text) {
        const box = document.getElementById('dex-orders') || document.getElementById('dex-order-status');
        if (box && box.scrollIntoView) {
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        Swal.fire({
            title: title,
            text: text || '',
            icon: 'error',
            footer: 'The attempt stays listed under Your orders'
        });
    }

    function rememberedKey() {
        try { return localStorage.getItem('privateKey'); } catch (e) { return null; }
    }

    function activeKey() {
        return sessionPk || rememberedKey();
    }

    function loadJobs() {
        try {
            const raw = sessionStorage.getItem(storageKey);
            const data = raw ? JSON.parse(raw) : [];
            if (Array.isArray(data)) {
                data.forEach(function (job) { jobs.push(job); });
            }
        } catch (e) {}
    }

    function errText(err) {
        if (!err) return 'Unknown error';
        if (err.response && err.response.data) {
            const d = err.response.data;
            if (typeof d.data === 'string' && d.data) return d.data;
            if (typeof d === 'string') return d;
        }
        return err.message ? err.message : String(err);
    }

    function contractErrorCode(text) {
        const s = String(text || '');
        const m = s.match(/process:\s*([A-Z][A-Z0-9_]+)/)
            || s.match(/\b(INVALID_AMOUNT|INVALID_TOKEN|NOT_ERC20|UNAUTHORIZED|OFFER_NOT_FOUND|INVALID_OFFER|PHP_AMOUNT_MUST_MATCH_OFFER)\b/);
        return m ? m[1] : '';
    }

    function friendlyError(err) {
        const raw = errText(err);
        const code = contractErrorCode(raw);
        const map = {
            INVALID_AMOUNT: 'Token and PHP amounts must be greater than 0. Fractions are allowed down to 0.00000001 (8 decimals).',
            INVALID_TOKEN: 'That token is not a valid ERC-20 on this DEX.',
            NOT_ERC20: 'That address is not an ERC-20 token.',
            UNAUTHORIZED: 'Only the maker can cancel this offer.',
            OFFER_NOT_FOUND: 'That offer is no longer on the book.',
            INVALID_OFFER: 'That offer cannot be filled.',
            PHP_AMOUNT_MUST_MATCH_OFFER: 'Send exactly the PHP listed on the ask.'
        };
        if (map[code]) return map[code];
        const cut = raw.split(' trace=')[0].replace(/\s+/g, ' ').trim();
        return cut.length > 220 ? cut.slice(0, 220) + '…' : (cut || raw);
    }

    function normalizeAmount(v, maxDecimals) {
        let s = String(v == null ? '' : v).replace(/,/g, '').trim();
        if (!s) return '';
        if (s.charAt(0) === '.') s = '0' + s;
        if (!/^(?:0|[1-9]\d*)(?:\.\d+)?$/.test(s)) return '';
        const dec = maxDecimals == null ? 8 : maxDecimals;
        const parts = s.split('.');
        let whole = parts[0] || '0';
        let frac = parts[1] || '';
        if (frac.length > dec) frac = frac.slice(0, dec);
        frac = frac.replace(/0+$/, '');
        const out = frac ? (whole + '.' + frac) : whole;
        return out === '0' || out === '' ? '' : out;
    }

    function positiveAmount(v) {
        return normalizeAmount(v) !== '';
    }

    function amountString(v) {
        return normalizeAmount(v) || String(v == null ? '' : v).replace(/,/g, '').trim();
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    function shortHash(hash) {
        if (!hash) return '';
        return hash.length > 16 ? hash.slice(0, 8) + '…' + hash.slice(-6) : hash;
    }

    function jobStatusLabel(job) {
        if (job.status === 'error') {
            const msg = job.error || 'Failed';
            const short = msg.length > 140 ? msg.slice(0, 140) + '…' : msg;
            return '<span class="dex-status-err">Failed</span><div class="font-size-12 text-muted">' + escapeHtml(short) + '</div>';
        }
        if (job.status === 'posted') return '<span class="dex-status-wait">Submitted</span>';
        if (job.status === 'needs_signature') {
            return '<span class="dex-status-wait">Approve confirmed</span><div class="font-size-12 text-muted">Sign to finish posting</div>';
        }
        const labels = {
            queued: 'Queued',
            sending_approve: 'Sending approve…',
            waiting_approve: job.detail || 'Waiting for approve to confirm',
            sending: 'Sending to the book…'
        };
        return '<span class="dex-status-wait">' + (labels[job.status] || job.status) + '</span>';
    }

    function jobSide(job) {
        if (job.kind === 'sell' || job.method === 'postSell') return 'ASK';
        if (job.kind === 'buy' || job.method === 'postBuy') return 'BID';
        return job.label || job.method || 'Order';
    }

    function paintMyOrders() {
        const body = document.getElementById('dex-orders-body');
        const status = document.getElementById('dex-order-status');
        const activity = window.DEX_LAST_ACTIVITY || [];
        const mine = activity.filter(function (row) {
            return myAddress && row.maker === myAddress;
        });
        const rows = [];
        jobs.forEach(function (job) {
            const matched = job.status === 'posted' && mine.some(function (row) {
                return (job.sellHash && row.id === job.sellHash)
                    || (String(job.tokenAmount) === String(row.amount) && String(job.phpAmount) === String(row.php));
            });
            if (matched) return;
            const side = jobSide(job);
            const sideCls = side === 'ASK' ? 'dex-down' : (side === 'BID' ? 'dex-up' : '');
            const hash = job.sellHash || job.approveHash || '';
            let action = '';
            if (job.status === 'needs_signature') {
                action += '<button type="button" class="btn btn-primary btn-sm" data-dex-continue="' + escapeHtml(job.id) + '">Sign</button> ';
            }
            if (job.status === 'posted' || job.status === 'error' || job.status === 'needs_signature') {
                action += '<button type="button" class="btn btn-outline-secondary btn-sm" data-dex-dismiss="' + escapeHtml(job.id) + '">Dismiss</button>';
            }
            const amt = parseFloat(job.tokenAmount);
            const php = parseFloat(job.phpAmount);
            let price = '—';
            if (amt && php && isFinite(amt) && isFinite(php)) {
                const p = php / amt;
                price = p >= 1 ? p.toFixed(4) : String(p);
            }
            rows.push('<tr>' +
                '<td class="' + sideCls + '">' + escapeHtml(side) + '</td>' +
                '<td>' + escapeHtml(price) + '</td>' +
                '<td>' + escapeHtml(job.tokenAmount || '—') + '</td>' +
                '<td>' + escapeHtml(job.phpAmount || '—') + '</td>' +
                '<td>' + jobStatusLabel(job) + (hash ? '<div class="font-size-12"><a href="/apps/explorer/tx.php?id=' + encodeURIComponent(hash) + '">' + shortHash(hash) + '</a></div>' : '') + '</td>' +
                '<td class="text-end">' + action + '</td>' +
                '</tr>');
        });
        mine.forEach(function (row) {
            const pending = !!row.pending;
            const side = row.side === 'buy' ? 'BID' : 'ASK';
            const sideCls = row.side === 'buy' ? 'dex-up' : 'dex-down';
            const cancel = (!pending && row.id)
                ? '<button type="button" class="btn btn-outline-secondary btn-sm" data-dex-cancel-side="' + escapeHtml(row.side) + '" data-dex-cancel-id="' + escapeHtml(row.id) + '">Cancel</button>'
                : '';
            rows.push('<tr>' +
                '<td class="' + sideCls + '">' + side + '</td>' +
                '<td>' + escapeHtml(row.price_display || '—') + '</td>' +
                '<td>' + escapeHtml(row.amount || '—') + '</td>' +
                '<td>' + escapeHtml(row.php || '—') + '</td>' +
                '<td>' + (pending ? '<span class="dex-status-wait">In mempool</span>' : '<span class="dex-status-ok">Open</span>') + '</td>' +
                '<td class="text-end">' + cancel + '</td>' +
                '</tr>');
        });
        if (body) {
            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-3">No open orders yet. Sells and bids you place show up here.</td></tr>';
            } else {
                body.innerHTML = rows.join('');
            }
        }
        if (status) {
            status.innerHTML = '';
        }
    }

    window.dexRenderMyOrders = paintMyOrders;
    window.dexPendingJobs = function () {
        return jobs.slice();
    };

    function saveJobs() {
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(jobs));
        } catch (e) {}
        paintMyOrders();
    }

    function generateExec(method, params, amount, toAddress) {
        const sendAmount = amountString(amount);
        return axios.post('/api.php?q=generateSmartContractExecTx', {
            public_key: publicKey,
            sc_address: toAddress || scAddress,
            amount: sendAmount === '' ? 0 : sendAmount,
            method: method,
            params: params || []
        }).then(function (res) {
            if (res.data.status !== 'ok') {
                throw new Error(res.data.data || 'Could not generate transaction');
            }
            const gen = res.data.data;
            if ((method === 'postBuy' || method === 'fillSell') && parseFloat(gen.tx && gen.tx.val) <= 0) {
                throw new Error('This transaction did not attach PHP. Enter a PHP amount greater than 0 and try again.');
            }
            return gen;
        });
    }

    function signAndSend(tx, signatureBase, privateKey) {
        let signature;
        try {
            signature = phpcoinCrypto.sign(String(cfg.chainId || '') + signatureBase, privateKey);
        } catch (e) {
            return Promise.reject(new Error('Check if your private key is correct'));
        }
        if (!signature) {
            return Promise.reject(new Error('Check if your private key is correct'));
        }
        tx.signature = signature;
        return axios.post('/api.php?q=sendTransactionJson', tx).then(function (res) {
            if (res.data.status === 'ok' && res.data.data) {
                return res.data.data;
            }
            throw new Error(res.data.data || 'Node rejected the transaction');
        });
    }

    function txId(raw) {
        if (!raw) return '';
        if (typeof raw === 'string') return raw;
        if (raw.id) return String(raw.id);
        return String(raw);
    }

    function isConfirmed(tx) {
        if (!tx) return false;
        if (tx.type_label === 'mempool') return false;
        const conf = parseInt(tx.confirmations, 10);
        if (!isNaN(conf) && conf < 0) return false;
        return !!(tx.block || tx.height);
    }

    async function getTx(hash) {
        const id = txId(hash);
        if (!id) return null;
        try {
            const res = await axios.get('/api.php', { params: { q: 'getTransaction', transaction: id } });
            if (res.data && res.data.status === 'ok') return res.data.data;
        } catch (e) {}
        return null;
    }

    async function getCurrentBlock() {
        try {
            const res = await axios.get('/api.php', { params: { q: 'currentBlock' } });
            if (res.data && res.data.status === 'ok') return res.data.data;
        } catch (e) {}
        return null;
    }

    async function waitUntilSpendable(needed, job, privateKey) {
        const deadline = Date.now() + 10 * 60 * 1000;
        let lastResend = 0;
        while (Date.now() < deadline) {
            const have = await readAllowance();
            if (have + 1e-12 >= parseFloat(needed)) {
                job.detail = '';
                saveJobs();
                return;
            }
            const hash = job.approveHash;
            const tx = hash ? await getTx(hash) : null;
            const block = await getCurrentBlock();
            const blockAge = block && block.date ? Math.max(0, Math.floor(Date.now() / 1000 - parseInt(block.date, 10))) : null;
            let detail = 'Waiting for approve to confirm';
            if (tx && isConfirmed(tx)) {
                detail = 'Approve is in a block, updating allowance…';
            } else if (tx && tx.type_label === 'mempool') {
                detail = 'Approve is in the mempool — it is not on the book until a miner includes it';
                if (blockAge != null) {
                    detail += '. Last block ' + blockAge + 's ago (height ' + (block.height || '?') + ')';
                }
                const txAge = tx.date ? Math.floor(Date.now() / 1000 - parseInt(tx.date, 10)) : 0;
                if (txAge > 240 && !privateKey) {
                    job.status = 'needs_signature';
                    job.detail = 'Approve sat in the mempool too long and expired. Sign to send a fresh one.';
                    saveJobs();
                    throw new Error(job.detail);
                }
                if (txAge > 240 && privateKey && Date.now() - lastResend > 30000) {
                    detail = 'Approve sat too long in mempool; sending a fresh approve';
                    job.detail = detail;
                    saveJobs();
                    const amount = approveAmount(needed);
                    job.approveHash = await sendMethod(privateKey, 'approve', [scAddress, amount], 0, selectedToken);
                    lastResend = Date.now();
                }
            } else if (hash) {
                detail = 'Looking up approve transaction…';
            }
            job.status = 'waiting_approve';
            job.detail = detail;
            saveJobs();
            await sleep(3000);
        }
        throw new Error('Approve never confirmed, so the sell cannot go on the book. This node may be behind or not receiving blocks. After a new block, open this pair and sign again.');
    }

    async function readAllowance() {
        if (!selectedToken || !myAddress || !scAddress) return 0;
        const params = btoa(JSON.stringify([myAddress, scAddress]));
        const res = await fetch(
            '/api.php?q=getSmartContractView&address=' + encodeURIComponent(selectedToken) +
            '&method=allowance&params=' + encodeURIComponent(params)
        );
        const json = await res.json();
        if (json.status !== 'ok') return 0;
        const n = parseFloat(json.data);
        return isFinite(n) ? n : 0;
    }

    function approveAmount(needed) {
        const bal = parseFloat(cfg.tokenBalance);
        const need = parseFloat(needed) || 0;
        if (isFinite(bal) && bal > need) {
            return String(cfg.tokenBalance);
        }
        return String(needed);
    }

    async function sendMethod(privateKey, method, params, amount, toAddress) {
        const gen = await generateExec(method, params, amount, toAddress);
        const hash = txId(await signAndSend(gen.tx, gen.signature_base, privateKey));
        if (typeof window.dexRefresh === 'function') window.dexRefresh();
        return hash;
    }

    async function ensureAllowance(privateKey, needed, job) {
        const have = await readAllowance();
        if (have + 1e-12 >= parseFloat(needed)) {
            return;
        }
        if (!job.approveHash) {
            if (!privateKey) {
                job.status = 'needs_signature';
                saveJobs();
                throw new Error('Sign to send the approve transaction');
            }
            job.status = 'sending_approve';
            saveJobs();
            const amount = approveAmount(needed);
            job.approveHash = await sendMethod(privateKey, 'approve', [scAddress, amount], 0, selectedToken);
            job.status = 'waiting_approve';
            saveJobs();
        }
        await waitUntilSpendable(needed, job, privateKey);
    }

    function failJob(job, err) {
        job.status = 'error';
        job.error = friendlyError(err);
        saveJobs();
        running[job.id] = false;
        showError('Order failed', job.error);
    }

    async function runJob(job, privateKey) {
        if (running[job.id]) return;
        running[job.id] = true;
        if (privateKey) sessionPk = privateKey;
        try {
            if (job.kind === 'sell') {
                if (dexMulti) {
                    await ensureAllowance(privateKey, job.tokenAmount, job);
                }
                const pk = activeKey();
                if (!pk) {
                    job.status = 'needs_signature';
                    saveJobs();
                    running[job.id] = false;
                    return;
                }
                job.status = 'sending';
                saveJobs();
                const params = dexMulti
                    ? [selectedToken, job.tokenAmount, job.phpAmount]
                    : [job.tokenAmount, job.phpAmount];
                job.sellHash = await sendMethod(pk, 'postSell', params, 0, scAddress);
            } else if (job.kind === 'fillBuy') {
                if (dexMulti && selectedToken) {
                    await ensureAllowance(privateKey, job.tokenAmount, job);
                }
                const pk = activeKey();
                if (!pk) {
                    job.status = 'needs_signature';
                    saveJobs();
                    running[job.id] = false;
                    return;
                }
                job.status = 'sending';
                saveJobs();
                job.sellHash = await sendMethod(pk, 'fillBuy', [job.offerId], 0, scAddress);
            } else if (job.kind === 'buy' || (job.kind === 'exec' && job.method === 'postBuy')) {
                const tok = amountString(job.tokenAmount);
                const php = amountString(job.phpAmount || job.amount);
                if (!positiveAmount(tok) || !positiveAmount(php)) {
                    throw new Error('Token amount and PHP amount must both be greater than 0');
                }
                job.status = 'sending';
                saveJobs();
                const params = dexMulti ? [selectedToken, tok] : [tok];
                job.sellHash = await sendMethod(privateKey, 'postBuy', params, php, scAddress);
            } else if (job.kind === 'exec') {
                job.status = 'sending';
                saveJobs();
                job.sellHash = await sendMethod(privateKey, job.method, job.params, job.amount, job.toAddress);
            }
            job.status = 'posted';
            saveJobs();
            if (typeof window.dexRefresh === 'function') window.dexRefresh();
        } catch (err) {
            const msg = err && err.message ? err.message : String(err);
            if (job.approveHash && /allowance|sign|private key/i.test(msg)) {
                job.status = 'needs_signature';
                job.error = msg;
                saveJobs();
            } else {
                failJob(job, err);
            }
        }
        running[job.id] = false;
    }

    function withPrivateKey(then) {
        const have = activeKey();
        if (have) {
            Promise.resolve(then(have)).catch(function (err) {
                showError('Error sending transaction', friendlyError(err));
            });
            return;
        }
        enterPrivateKey(function (privateKey) {
            if (!privateKey) return;
            sessionPk = privateKey;
            Promise.resolve(then(privateKey)).catch(function (err) {
                showError('Error sending transaction', friendlyError(err));
            });
        });
    }

    function enqueue(job) {
        job.id = 'j' + Date.now() + Math.floor(Math.random() * 1000);
        job.status = job.status || 'queued';
        jobs.unshift(job);
        saveJobs();
        const box = document.getElementById('dex-orders');
        if (box && box.scrollIntoView) {
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        withPrivateKey(function (pk) { return runJob(job, pk); });
        return job;
    }

    function resumeOpenJobs() {
        jobs.forEach(function (job) {
            if (job.status === 'posted' || job.status === 'error' || job.status === 'needs_signature') return;
            const pk = activeKey();
            if (pk) {
                runJob(job, pk);
            } else if (job.approveHash && (job.status === 'waiting_approve' || job.status === 'sending_approve')) {
                runJob(job, null);
            }
        });
    }

    document.addEventListener('click', function (ev) {
        const dismiss = ev.target.closest('[data-dex-dismiss]');
        if (dismiss) {
            const id = dismiss.getAttribute('data-dex-dismiss');
            const ix = jobs.findIndex(function (j) { return j.id === id; });
            if (ix >= 0) jobs.splice(ix, 1);
            saveJobs();
            return;
        }
        const cont = ev.target.closest('[data-dex-continue]');
        if (cont) {
            const id = cont.getAttribute('data-dex-continue');
            const job = jobs.find(function (j) { return j.id === id; });
            if (!job) return;
            withPrivateKey(function (pk) { return runJob(job, pk); });
            return;
        }
        const cancelBtn = ev.target.closest('[data-dex-cancel-id]');
        if (cancelBtn) {
            const id = cancelBtn.getAttribute('data-dex-cancel-id');
            const side = cancelBtn.getAttribute('data-dex-cancel-side');
            confirmMsg('Cancel order', 'Return locked funds and remove this offer from the book?', function () {
                window.dexExec(side === 'buy' ? 'cancelBuy' : 'cancelSell', [id], 0);
            });
        }
    });

    window.dexExec = function (method, params, amount, toAddress) {
        const php = amountString(amount);
        const list = params || [];
        let tokenAmount = '';
        if (method === 'postBuy' || method === 'postSell') {
            tokenAmount = list.length >= 2 ? list[1] : list[0];
        } else if (list.length) {
            tokenAmount = list[list.length === 3 ? 1 : 0];
        }
        enqueue({
            kind: method === 'postBuy' ? 'buy' : 'exec',
            label: method === 'postBuy' ? 'Buy offer' : (method === 'fillSell' ? 'Take ask' : method),
            method: method,
            params: list,
            amount: php === '' ? 0 : php,
            toAddress: toAddress,
            tokenAmount: tokenAmount,
            phpAmount: php
        });
    };

    window.dexNormalizeAmount = normalizeAmount;

    window.dexPostBuy = function (tokenAmount, phpAmount) {
        enqueue({
            kind: 'buy',
            label: 'Buy offer',
            method: 'postBuy',
            tokenAmount: amountString(tokenAmount),
            phpAmount: amountString(phpAmount),
            amount: amountString(phpAmount)
        });
    };

    window.dexPostSell = function (tokenAmount, phpAmount) {
        enqueue({
            kind: 'sell',
            label: 'Sell ' + symbol,
            tokenAmount: tokenAmount,
            phpAmount: phpAmount
        });
    };

    window.dexTake = function (side, id, phpAmount, tokenAmount) {
        if (side === 'sell') {
            confirmMsg('Take ask', 'Send ' + phpAmount + ' PHP to fill this sell offer?', function () {
                window.dexExec('fillSell', [id], phpAmount);
            });
            return;
        }
        confirmMsg('Take bid', 'Deliver tokens to fill this buy offer?', function () {
            enqueue({
                kind: 'fillBuy',
                label: 'Take bid',
                offerId: id,
                tokenAmount: tokenAmount,
                phpAmount: phpAmount
            });
        });
    };

    loadJobs();
    paintMyOrders();
    resumeOpenJobs();
})();
