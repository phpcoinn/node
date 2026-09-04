(function () {
    const cfg = window.DEX_MARKET || {};
    const token = cfg.token || '';
    const canTrade = !!cfg.canTrade;
    const myAddress = cfg.address || '';
    const storageKey = 'phpcoin-dex-px-' + token;
    let showing = 'price';
    let lastPrice = null;

    function history() {
        try {
            const raw = localStorage.getItem(storageKey);
            const data = raw ? JSON.parse(raw) : [];
            return Array.isArray(data) ? data : [];
        } catch (e) {
            return [];
        }
    }

    function pushPoint(price) {
        const n = parseFloat(price);
        if (!n || !isFinite(n)) return history();
        const pts = history();
        const t = Math.floor(Date.now() / 1000);
        const last = pts[pts.length - 1];
        if (last && t - last.time < 2) {
            last.value = n;
        } else {
            pts.push({ time: t, value: n });
        }
        while (pts.length > 240) pts.shift();
        localStorage.setItem(storageKey, JSON.stringify(pts));
        return pts;
    }

    function isDark() {
        return document.body.getAttribute('data-layout-mode') === 'dark';
    }

    function themeColors() {
        if (isDark()) {
            return {
                bg: '#2a3042',
                text: '#a6b0cf',
                grid: '#363c51',
                line: '#8d92e3',
                up: '#2ab57d',
                down: '#fd625e',
                muted: '#8b92a9',
                fillTop: 'rgba(81, 86, 190, 0.28)',
                fillBot: 'rgba(81, 86, 190, 0.00)'
            };
        }
        return {
            bg: '#ffffff',
            text: '#74788d',
            grid: '#e9e9ef',
            line: '#5156be',
            up: '#2ab57d',
            down: '#fd625e',
            muted: '#74788d',
            fillTop: 'rgba(81, 86, 190, 0.22)',
            fillBot: 'rgba(81, 86, 190, 0.00)'
        };
    }

    function sizeCanvas(canvas) {
        if (!canvas) return null;
        const stage = canvas.parentElement;
        const cssW = Math.max(1, Math.floor((stage && stage.clientWidth) || 600));
        const cssH = Math.max(1, Math.floor((stage && stage.clientHeight) || 300));
        const dpr = window.devicePixelRatio || 1;
        canvas.width = Math.floor(cssW * dpr);
        canvas.height = Math.floor(cssH * dpr);
        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        return { w: cssW, h: cssH, ctx: ctx };
    }

    function drawPrice(pts) {
        const canvas = document.getElementById('dex-price-chart');
        if (!canvas) return;
        const sized = sizeCanvas(canvas);
        if (!sized) return;
        const ctx = sized.ctx;
        const w = sized.w;
        const h = sized.h;
        const c = themeColors();
        ctx.fillStyle = c.bg;
        ctx.fillRect(0, 0, w, h);
        pts = pts || history();
        if (!pts.length) {
            ctx.fillStyle = c.muted;
            ctx.font = '13px "IBM Plex Sans", sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Price appears after the book has a live mid', w / 2, h / 2);
            return;
        }
        const padL = 12;
        const padR = 64;
        const padT = 16;
        const padB = 28;
        let min = pts[0].value;
        let max = pts[0].value;
        pts.forEach(function (p) {
            if (p.value < min) min = p.value;
            if (p.value > max) max = p.value;
        });
        if (min === max) {
            min = min * 0.99;
            max = max * 1.01 || 1;
        }
        const span = max - min;
        function X(i) {
            return padL + (pts.length === 1 ? (w - padL - padR) / 2 : i / (pts.length - 1) * (w - padL - padR));
        }
        function Y(v) {
            return padT + (1 - (v - min) / span) * (h - padT - padB);
        }
        ctx.strokeStyle = c.grid;
        ctx.lineWidth = 1;
        ctx.font = '11px "IBM Plex Sans", sans-serif';
        ctx.fillStyle = c.muted;
        ctx.textAlign = 'right';
        for (let g = 0; g <= 4; g++) {
            const v = max - span * (g / 4);
            const y = Y(v);
            ctx.beginPath();
            ctx.moveTo(padL, y);
            ctx.lineTo(w - padR + 8, y);
            ctx.stroke();
            ctx.fillText(formatAxis(v), w - 8, y + 4);
        }
        ctx.beginPath();
        pts.forEach(function (p, i) {
            if (i === 0) ctx.moveTo(X(i), Y(p.value));
            else ctx.lineTo(X(i), Y(p.value));
        });
        ctx.lineTo(X(pts.length - 1), h - padB);
        ctx.lineTo(X(0), h - padB);
        ctx.closePath();
        const grad = ctx.createLinearGradient(0, padT, 0, h - padB);
        grad.addColorStop(0, c.fillTop);
        grad.addColorStop(1, c.fillBot);
        ctx.fillStyle = grad;
        ctx.fill();
        ctx.beginPath();
        pts.forEach(function (p, i) {
            if (i === 0) ctx.moveTo(X(i), Y(p.value));
            else ctx.lineTo(X(i), Y(p.value));
        });
        ctx.strokeStyle = c.line;
        ctx.lineWidth = 2;
        ctx.stroke();
        const last = pts[pts.length - 1];
        const ly = Y(last.value);
        ctx.beginPath();
        ctx.setLineDash([4, 4]);
        ctx.strokeStyle = c.line;
        ctx.lineWidth = 1;
        ctx.moveTo(padL, ly);
        ctx.lineTo(w - padR + 8, ly);
        ctx.stroke();
        ctx.setLineDash([]);
        ctx.fillStyle = c.line;
        ctx.beginPath();
        ctx.arc(X(pts.length - 1), ly, 3, 0, Math.PI * 2);
        ctx.fill();
        ctx.font = '11px "IBM Plex Sans", sans-serif';
        ctx.fillStyle = c.muted;
        ctx.textAlign = 'left';
        const firstT = new Date(pts[0].time * 1000);
        const lastT = new Date(last.time * 1000);
        function hhmm(d) {
            return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        }
        ctx.fillText(hhmm(firstT), padL, h - 8);
        ctx.textAlign = 'right';
        ctx.fillText(hhmm(lastT), w - padR, h - 8);
    }

    function formatAxis(v) {
        const n = Number(v);
        if (!isFinite(n)) return '—';
        if (Math.abs(n) >= 1000) return n.toFixed(2);
        if (Math.abs(n) >= 1) return n.toFixed(4);
        return n.toPrecision(4);
    }

    function initChart() {
        drawPrice(history());
        window.addEventListener('resize', function () {
            drawPrice(history());
            if (showing === 'depth') drawDepth(window.DEX_LAST_BOOK || { bids: [], asks: [] });
        });
        if (window.MutationObserver) {
            new MutationObserver(function () {
                drawPrice(history());
                drawDepth(window.DEX_LAST_BOOK || { bids: [], asks: [] });
            }).observe(document.body, { attributes: true, attributeFilter: ['data-layout-mode'] });
        }
    }

    function setTicker(t, prev) {
        const lastEl = document.getElementById('dex-last');
        const bidEl = document.getElementById('dex-bid');
        const askEl = document.getElementById('dex-ask');
        const spreadEl = document.getElementById('dex-spread');
        const volEl = document.getElementById('dex-vol');
        const chgEl = document.getElementById('dex-chg');
        if (lastEl) lastEl.textContent = t.last_display;
        if (bidEl) bidEl.textContent = t.best_bid_display;
        if (askEl) askEl.textContent = t.best_ask_display;
        if (spreadEl) {
            spreadEl.textContent = t.spread_pct ? (t.spread_display + ' (' + t.spread_pct + '%)') : t.spread_display;
        }
        if (volEl) volEl.textContent = t.book_php + ' PHP';
        let chgClass = '';
        let chgText = '—';
        if (prev && t.last && parseFloat(prev) > 0) {
            const diff = (parseFloat(t.last) - parseFloat(prev)) / parseFloat(prev) * 100;
            if (isFinite(diff) && Math.abs(diff) > 0.0001) {
                chgClass = diff >= 0 ? 'dex-up' : 'dex-down';
                chgText = (diff >= 0 ? '+' : '') + diff.toFixed(2) + '%';
            } else {
                chgText = '0.00%';
            }
        }
        if (chgEl) {
            chgEl.textContent = chgText;
            chgEl.className = 'val ' + chgClass;
        }
        if (lastEl) {
            lastEl.className = 'val ' + (chgClass || (t.last ? 'dex-up' : ''));
        }
        const midEl = document.getElementById('dex-mid-px');
        if (midEl) {
            midEl.textContent = t.last_display;
            midEl.className = 'px ' + (chgClass || '');
        }
        const spEl = document.getElementById('dex-mid-sp');
        if (spEl) spEl.textContent = t.spread_pct ? ('Spread ' + t.spread_pct + '%') : 'Spread —';
    }

    function rowHtml(row, side) {
        const cls = side === 'buy' ? 'dex-up' : 'dex-down';
        const mine = myAddress && row.maker === myAddress;
        const pending = row.pending ? ' opacity-75' : '';
        const take = canTrade && !row.pending && !mine;
        const attrs = take
            ? ' data-dex-take-side="' + escapeHtml(side) + '" data-dex-take-id="' + escapeHtml(row.id) +
              '" data-dex-take-php="' + escapeHtml(row.php) + '" data-dex-take-amount="' + escapeHtml(row.amount) + '"'
            : '';
        return '<div class="dex-book-row' + pending + '"' + attrs + '>' +
            '<div class="bar" style="width:' + Math.round((row.depth || 0) * 100) + '%"></div>' +
            '<div class="' + cls + '">' + escapeHtml(row.price_display) + '</div>' +
            '<div>' + escapeHtml(row.amount) + '</div>' +
            '<div>' + escapeHtml(row.php) + '</div>' +
            '</div>';
    }

    function byPriceAsc(a, b) {
        return (parseFloat(a.price) || 0) - (parseFloat(b.price) || 0);
    }

    function renderBook(book) {
        const asks = (book.asks || []).slice().sort(byPriceAsc);
        const bids = (book.bids || []).slice().sort(byPriceAsc);
        const askBox = document.getElementById('dex-asks');
        const bidBox = document.getElementById('dex-bids');
        if (bidBox) {
            bidBox.innerHTML = bids.length ? bids.map(function (r) { return rowHtml(r, 'buy'); }).join('') : '<div class="dex-empty">No bids</div>';
        }
        if (askBox) {
            askBox.innerHTML = asks.length ? asks.map(function (r) { return rowHtml(r, 'sell'); }).join('') : '<div class="dex-empty">No asks</div>';
        }
    }

    function jobMatchesOffer(job, row) {
        if (!job || !row) return false;
        if (job.sellHash && row.id && job.sellHash === row.id) return true;
        const sameAmt = String(job.tokenAmount || '') === String(row.amount || '');
        const samePhp = String(job.phpAmount || '') === String(row.php || '');
        const jobSide = job.kind === 'sell' ? 'sell' : (job.kind === 'exec' && job.method === 'postBuy' ? 'buy' : '');
        if (!jobSide || row.side !== jobSide) return false;
        return sameAmt && samePhp;
    }

    function renderMyOrders(activity) {
        const body = document.getElementById('dex-orders-body');
        if (!body) return;
        const mine = (activity || []).filter(function (row) {
            return myAddress && row.maker === myAddress;
        });
        const jobs = typeof window.dexPendingJobs === 'function' ? window.dexPendingJobs() : [];
        const rows = [];
        jobs.forEach(function (job) {
            const isResting = job.kind === 'sell' || job.method === 'postBuy' || job.method === 'postSell';
            if (!isResting) return;
            if (job.status === 'posted' && mine.some(function (row) { return jobMatchesOffer(job, row); })) {
                return;
            }
            rows.push(jobRow(job));
        });
        mine.forEach(function (row) {
            rows.push(offerRow(row));
        });
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-3">No open orders yet. Sells and bids you place show up here.</td></tr>';
            return;
        }
        body.innerHTML = rows.join('');
    }

    function jobRow(job) {
        const side = job.kind === 'sell' || job.method === 'postSell' ? 'ASK'
            : (job.method === 'postBuy' ? 'BID' : escapeHtml(job.label || job.method || 'Order'));
        const sideCls = side === 'ASK' ? 'dex-down' : (side === 'BID' ? 'dex-up' : '');
        const price = jobPrice(job);
        const status = jobStatusLabel(job);
        const hash = job.sellHash || job.approveHash || '';
        const hashHtml = hash
            ? '<div class="font-size-12">' + txLink(hash) + '</div>'
            : '';
        let action = '';
        if (job.status === 'needs_signature') {
            action = '<button type="button" class="btn btn-primary btn-sm" data-dex-continue="' + escapeHtml(job.id) + '">Sign</button> ';
        }
        if (job.status === 'posted' || job.status === 'error' || job.status === 'needs_signature') {
            action += '<button type="button" class="btn btn-outline-secondary btn-sm" data-dex-dismiss="' + escapeHtml(job.id) + '">Dismiss</button>';
        }
        return '<tr>' +
            '<td class="' + sideCls + '">' + side + '</td>' +
            '<td>' + price + '</td>' +
            '<td>' + escapeHtml(job.tokenAmount || '—') + '</td>' +
            '<td>' + escapeHtml(job.phpAmount || '—') + '</td>' +
            '<td>' + status + hashHtml + '</td>' +
            '<td class="text-end">' + action + '</td>' +
            '</tr>';
    }

    function offerRow(row) {
        const side = row.side === 'buy' ? 'BID' : 'ASK';
        const sideCls = row.side === 'buy' ? 'dex-up' : 'dex-down';
        const pending = !!row.pending;
        const status = pending
            ? '<span class="dex-status-wait">In mempool</span>'
            : '<span class="dex-status-ok">Open</span>';
        const cancel = (!pending && canTrade && row.id)
            ? '<button type="button" class="btn btn-outline-secondary btn-sm" data-dex-cancel-side="' +
                escapeHtml(row.side) + '" data-dex-cancel-id="' + escapeHtml(row.id) + '">Cancel</button>'
            : '';
        return '<tr>' +
            '<td class="' + sideCls + '">' + side + '</td>' +
            '<td>' + escapeHtml(row.price_display || '—') + '</td>' +
            '<td>' + escapeHtml(row.amount || '—') + '</td>' +
            '<td>' + escapeHtml(row.php || '—') + '</td>' +
            '<td>' + status + '</td>' +
            '<td class="text-end">' + cancel + '</td>' +
            '</tr>';
    }

    function jobPrice(job) {
        const amt = parseFloat(job.tokenAmount);
        const php = parseFloat(job.phpAmount);
        if (!amt || !php || !isFinite(amt) || !isFinite(php)) return '—';
        const p = php / amt;
        if (p >= 1000) return p.toFixed(2);
        if (p >= 1) return p.toFixed(4);
        return String(p);
    }

    function jobStatusLabel(job) {
        if (job.status === 'error') {
            return '<span class="dex-status-err">Failed</span><div class="font-size-12 text-muted">' + escapeHtml(job.error || '') + '</div>';
        }
        if (job.status === 'posted') {
            return '<span class="dex-status-wait">Submitted</span>';
        }
        if (job.status === 'needs_signature') {
            return '<span class="dex-status-wait">Approve confirmed</span><div class="font-size-12 text-muted">Sign to post the order</div>';
        }
        const labels = {
            queued: 'Queued',
            sending_approve: 'Sending approve…',
            waiting_approve: 'Waiting for approve (~60s)',
            sending: 'Sending…'
        };
        return '<span class="dex-status-wait">' + (labels[job.status] || job.status) + '</span>';
    }

    function txLink(hash) {
        if (!hash) return '—';
        const short = hash.length > 16 ? hash.slice(0, 8) + '…' + hash.slice(-6) : hash;
        return '<a href="/apps/explorer/tx.php?id=' + encodeURIComponent(hash) + '">' + short + '</a>';
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    function activityNewestFirst(activity) {
        return (activity || []).slice().sort(function (a, b) {
            const ap = a.pending ? 1 : 0;
            const bp = b.pending ? 1 : 0;
            if (ap !== bp) return bp - ap;
            if (ap) return (parseInt(b.date, 10) || 0) - (parseInt(a.date, 10) || 0);
            const aid = parseInt(a.id, 10);
            const bid = parseInt(b.id, 10);
            if (isFinite(aid) && isFinite(bid) && aid !== bid) return bid - aid;
            return String(b.id || '').localeCompare(String(a.id || ''));
        });
    }

    function renderTape(activity) {
        const box = document.getElementById('dex-tape-body');
        if (!box) return;
        const rows = activityNewestFirst(activity);
        if (!rows.length) {
            box.innerHTML = '<div class="dex-empty">No open orders yet. Be the first bid or ask.</div>';
            return;
        }
        box.innerHTML = rows.map(function (row) {
            const side = row.side === 'buy' ? 'BID' : 'ASK';
            const cls = row.side === 'buy' ? 'side-buy' : 'side-sell';
            return '<div class="dex-tape-row">' +
                '<div class="' + cls + '">' + side + '</div>' +
                '<div>' + escapeHtml(row.price_display) + '</div>' +
                '<div>' + escapeHtml(row.amount) + '</div>' +
                '<div>' + escapeHtml(row.pending ? 'pending' : row.php) + '</div>' +
                '</div>';
        }).join('');
    }

    function drawDepth(book) {
        const canvas = document.getElementById('dex-depth-chart');
        if (!canvas) return;
        const sized = sizeCanvas(canvas);
        if (!sized) return;
        const ctx = sized.ctx;
        const w = sized.w;
        const h = sized.h;
        ctx.fillStyle = themeColors().bg;
        ctx.fillRect(0, 0, w, h);
        const bids = (book.bids || []).slice().reverse();
        const asks = book.asks || [];
        if (!bids.length && !asks.length) {
            ctx.fillStyle = themeColors().muted;
            ctx.font = '13px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Depth appears when bids and asks are posted', w / 2, h / 2);
            return;
        }
        const pts = [];
        let acc = 0;
        bids.forEach(function (row) {
            acc += parseFloat(row.amount) || 0;
            pts.push({ x: parseFloat(row.price), y: acc, side: 'bid' });
        });
        acc = 0;
        const askPts = [];
        asks.forEach(function (row) {
            acc += parseFloat(row.amount) || 0;
            askPts.push({ x: parseFloat(row.price), y: acc, side: 'ask' });
        });
        const allX = pts.concat(askPts).map(function (p) { return p.x; });
        const allY = pts.concat(askPts).map(function (p) { return p.y; });
        const minX = Math.min.apply(null, allX);
        const maxX = Math.max.apply(null, allX);
        const maxY = Math.max.apply(null, allY) || 1;
        const pad = 20;
        function X(v) { return pad + (maxX === minX ? w / 2 : (v - minX) / (maxX - minX) * (w - pad * 2)); }
        function Y(v) { return h - pad - (v / maxY) * (h - pad * 2); }
        function fill(series, color, fill) {
            if (!series.length) return;
            ctx.beginPath();
            ctx.moveTo(X(series[0].x), Y(0));
            series.forEach(function (p) { ctx.lineTo(X(p.x), Y(p.y)); });
            ctx.lineTo(X(series[series.length - 1].x), Y(0));
            ctx.closePath();
            ctx.fillStyle = fill;
            ctx.fill();
            ctx.beginPath();
            series.forEach(function (p, i) {
                if (i === 0) ctx.moveTo(X(p.x), Y(p.y));
                else ctx.lineTo(X(p.x), Y(p.y));
            });
            ctx.strokeStyle = color;
            ctx.lineWidth = 2;
            ctx.stroke();
        }
        fill(pts, themeColors().up, isDark() ? 'rgba(42,181,125,0.22)' : 'rgba(42,181,125,0.18)');
        fill(askPts, themeColors().down, isDark() ? 'rgba(253,98,94,0.22)' : 'rgba(253,98,94,0.16)');
    }

    function apply(data) {
        const ticker = data.ticker || {};
        setTicker(ticker, lastPrice);
        if (ticker.last) {
            const pts = pushPoint(ticker.last);
            drawPrice(pts);
            lastPrice = ticker.last;
        }
        renderBook(data.book || { bids: [], asks: [] });
        renderTape(data.activity || []);
        if (typeof window.dexRenderMyOrders === 'function') {
            window.dexRenderMyOrders();
        } else {
            renderMyOrders(data.activity || []);
        }
        drawDepth(data.book || { bids: [], asks: [] });
        const live = document.getElementById('dex-updated');
        if (live) live.textContent = 'LIVE';
    }

    async function refresh() {
        if (!token) return;
        try {
            const res = await fetch('/api.php?q=getDexMarket&token=' + encodeURIComponent(token));
            const json = await res.json();
            if (json.status === 'ok') apply(json.data);
        } catch (e) {}
    }

    document.querySelectorAll('[data-dex-chart]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-dex-chart]').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            showing = btn.getAttribute('data-dex-chart');
            const price = document.getElementById('dex-price-chart');
            const depth = document.getElementById('dex-depth-chart');
            if (showing === 'depth') {
                if (price) price.style.display = 'none';
                if (depth) depth.style.display = 'block';
                drawDepth(window.DEX_LAST_BOOK || { bids: [], asks: [] });
            } else {
                if (price) price.style.display = 'block';
                if (depth) depth.style.display = 'none';
                drawPrice(history());
            }
        });
    });

    document.addEventListener('click', function (ev) {
        const row = ev.target.closest('[data-dex-take-id]');
        if (!row || typeof window.dexTake !== 'function') return;
        window.dexTake(
            row.getAttribute('data-dex-take-side'),
            row.getAttribute('data-dex-take-id'),
            row.getAttribute('data-dex-take-php'),
            row.getAttribute('data-dex-take-amount')
        );
    });

    const _apply = apply;
    apply = function (data) {
        window.DEX_LAST_BOOK = data.book;
        window.DEX_LAST_ACTIVITY = data.activity || [];
        _apply(data);
        if (showing === 'depth') drawDepth(data.book || { bids: [], asks: [] });
    };

    initChart();
    refresh();
    setInterval(refresh, 3000);
    window.dexRefresh = refresh;
    window.dexRenderMyOrders = function () {
        renderMyOrders((window.DEX_LAST_ACTIVITY) || []);
    };
})();
