(function () {
    const search = document.getElementById('dex-search');
    const body = document.getElementById('dex-markets-body');
    if (!body) return;

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    function paint(rows) {
        const q = (search && search.value ? search.value : '').trim().toLowerCase();
        const filtered = rows.filter(function (row) {
            if (!q) return true;
            return (row.pair || '').toLowerCase().indexOf(q) >= 0
                || (row.name || '').toLowerCase().indexOf(q) >= 0
                || (row.token || '').toLowerCase().indexOf(q) >= 0
                || (row.symbol || '').toLowerCase().indexOf(q) >= 0;
        });
        if (!filtered.length) {
            body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No markets match</td></tr>';
            return;
        }
        body.innerHTML = filtered.map(function (row) {
            const href = '/apps/dex/market.php?token=' + encodeURIComponent(row.token);
            const last = row.last_display || '—';
            const bid = row.best_bid_display || '—';
            const ask = row.best_ask_display || '—';
            const spread = row.spread_pct ? row.spread_pct + '%' : '—';
            const vol = row.book_php || '0.00000000';
            const live = (parseInt(row.bid_count, 10) + parseInt(row.ask_count, 10)) > 0;
            return '<tr onclick="location.href=\'' + href + '\'">' +
                '<td><div class="dex-pair-name">' + escapeHtml(row.pair) + '<span>' + escapeHtml(row.name) + '</span></div></td>' +
                '<td class="' + (row.last ? 'dex-up' : '') + '">' + escapeHtml(last) + '</td>' +
                '<td class="dex-up">' + escapeHtml(bid) + '</td>' +
                '<td class="dex-down">' + escapeHtml(ask) + '</td>' +
                '<td>' + escapeHtml(spread) + '</td>' +
                '<td>' + escapeHtml(vol) + '</td>' +
                '<td>' + (live ? '<span class="badge rounded-pill bg-success">Book live</span>' : '<span class="badge rounded-pill bg-secondary">Empty</span>') + '</td>' +
                '</tr>';
        }).join('');
    }

    async function refresh() {
        try {
            const res = await fetch('/api.php?q=getDexTickers');
            const json = await res.json();
            if (json.status === 'ok') paint(json.data || []);
        } catch (e) {}
    }

    if (search) search.addEventListener('input', refresh);
    refresh();
    setInterval(refresh, 4000);
})();
