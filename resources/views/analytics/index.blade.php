<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LogLens Monitor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#09111f; --panel:#101d30; --panel-2:#14243a; --line:#20344f; --text:#edf4ff; --muted:#8fa3be; --cyan:#53d9f3; --green:#55dfa2; --orange:#ffb86b; --blue:#729cff; }
        * { box-sizing:border-box; }
        body { margin:0; background:radial-gradient(circle at 80% 0%, #172e4b 0, var(--bg) 28rem); color:var(--text); font-family:"DM Sans",sans-serif; }
        .shell { max-width:1440px; margin:auto; padding:24px; }
        header { display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; }
        .brand { display:flex; align-items:center; gap:11px; }
        .brand-mark { width:38px; height:38px; border-radius:10px; display:grid; place-items:center; background:linear-gradient(135deg,var(--cyan),var(--blue)); color:#07111f; font-weight:800; box-shadow:0 8px 30px #53d9f333; }
        .brand h1 { margin:0; font-size:20px; }
        .live { display:flex; align-items:center; gap:8px; color:var(--green); font-size:13px; font-weight:700; }
        .live-dot { width:8px; height:8px; border-radius:50%; background:var(--green); box-shadow:0 0 14px var(--green); animation:pulse 1.8s infinite; }
        @keyframes pulse { 50% { opacity:.35; } }
        .metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
        .card { background:linear-gradient(145deg,var(--panel),#0d1929); border:1px solid var(--line); border-radius:14px; }
        .metric { padding:18px; min-height:118px; position:relative; overflow:hidden; }
        .metric:after { content:""; position:absolute; width:90px; height:90px; right:-35px; bottom:-50px; border-radius:50%; background:#53d9f31a; }
        .metric-label { color:var(--muted); font-size:13px; }
        .metric-value { font-size:31px; line-height:1; margin-top:12px; font-weight:700; }
        .metric-note { color:var(--muted); font-size:12px; margin-top:10px; }
        .grid { display:grid; grid-template-columns:minmax(0,1.8fr) minmax(280px,.7fr); gap:14px; margin-top:14px; }
        .panel { padding:18px; }
        .panel-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
        .panel h2 { margin:0; font-size:15px; }
        .muted { color:var(--muted); font-size:12px; }
        .chart { height:180px; display:flex; align-items:flex-end; gap:5px; border-bottom:1px solid var(--line); padding:14px 4px 0; }
        .bar-wrap { height:100%; flex:1; display:flex; align-items:flex-end; }
        .bar { width:100%; min-height:3px; border-radius:4px 4px 0 0; background:linear-gradient(180deg,var(--cyan),#3d77dc); box-shadow:0 0 12px #53d9f322; }
        .region-list { display:flex; flex-direction:column; gap:10px; }
        .region-row { display:grid; grid-template-columns:1fr auto; gap:8px; }
        .region-name { display:flex; justify-content:space-between; font-size:13px; }
        .track { grid-column:1/-1; height:5px; background:#1d3048; border-radius:99px; overflow:hidden; }
        .fill { height:100%; border-radius:99px; background:linear-gradient(90deg,var(--blue),var(--cyan)); }
        .table-card { margin-top:14px; overflow:hidden; }
        .filters { display:flex; gap:8px; padding:14px; border-bottom:1px solid var(--line); flex-wrap:wrap; }
        input, select { background:#0b1728; color:var(--text); border:1px solid var(--line); border-radius:8px; height:38px; padding:0 11px; font-family:inherit; outline:none; }
        input { width:min(260px,100%); }
        select { min-width:140px; }
        button { height:38px; border:0; border-radius:8px; padding:0 14px; background:var(--cyan); color:#07111f; font-weight:700; cursor:pointer; }
        table { width:100%; border-collapse:collapse; }
        th { color:var(--muted); text-align:left; font-size:11px; letter-spacing:.05em; text-transform:uppercase; background:#0d1929; }
        th, td { padding:13px 14px; border-bottom:1px solid var(--line); }
        td { font-size:13px; }
        .ip { font-weight:700; }
        .badge { display:inline-flex; border-radius:99px; padding:4px 8px; background:#1b3a56; color:#85dcf0; font-size:11px; }
        .pagination { display:flex; justify-content:flex-end; gap:6px; padding:12px 14px; }
        .pagination nav { width:100%; }
        .pagination a, .pagination span { display:inline-flex; min-width:32px; height:32px; align-items:center; justify-content:center; border-radius:7px; border:1px solid var(--line); color:var(--muted); text-decoration:none; padding:0 8px; }
        .pagination [aria-current="page"] span { background:var(--cyan); color:#07111f; border-color:var(--cyan); }
        @media (max-width:900px) { .metrics { grid-template-columns:repeat(2,1fr); } .grid { grid-template-columns:1fr; } .table-wrap { overflow-x:auto; } table { min-width:760px; } }
        @media (max-width:560px) { .shell { padding:14px; } .metrics { grid-template-columns:1fr 1fr; } .metric { min-height:105px; } .metric-value { font-size:25px; } header { align-items:flex-start; } }
    </style>
</head>
<body>
<div class="shell">
    <header>
        <div class="brand">
            <div class="brand-mark">LL</div>
            <div><h1>LogLens</h1><div class="muted">Realtime traffic monitor</div></div>
        </div>
        <div class="live"><span class="live-dot"></span><span id="clock">Live</span></div>
    </header>

    <section class="metrics">
        <div class="card metric"><div class="metric-label">Total Visitors</div><div class="metric-value" id="totalVisitors">{{ number_format($totalVisitors) }}</div><div class="metric-note">Unique IP + user agent</div></div>
        <div class="card metric"><div class="metric-label">Total Hits</div><div class="metric-value" id="totalHits">{{ number_format($totalHits ?? 0) }}</div><div class="metric-note">All recorded requests</div></div>
        <div class="card metric"><div class="metric-label">Active Now</div><div class="metric-value" id="activeVisitors">{{ number_format($activeVisitors) }}</div><div class="metric-note">Seen in the last 5 minutes</div></div>
        <div class="card metric"><div class="metric-label">Scanner Sources</div><div class="metric-value" id="scannerVisitors">{{ number_format($scannerVisitors) }}</div><div class="metric-note" id="scannerHits">{{ number_format($scannerHits) }} scanner hits</div></div>
    </section>

    <section class="grid">
        <div class="card panel">
            <div class="panel-head"><h2>Traffic — 24 Hours</h2><span class="muted">Hourly activity</span></div>
            <div class="chart" id="trafficChart">
                @php $maxHits = max((int) $hourlyHits->max('hits'), 1); @endphp
                @foreach($hourlyHits as $point)
                    <div class="bar-wrap" title="{{ $point['label'] }}: {{ $point['hits'] }} hits">
                        <div class="bar" style="height: {{ max(2, ($point['hits'] / $maxHits) * 100) }}%"></div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card panel">
            <div class="panel-head"><h2>Traffic by Region</h2><span class="muted">{{ $regions->count() }} groups</span></div>
            <div class="region-list" id="regionList">
                @php $regionMax = max((int) $regions->max('hits'), 1); @endphp
                @forelse($regions->take(8) as $item)
                    <div class="region-row">
                        <div class="region-name"><span>{{ $item->region }}</span><span class="muted">{{ number_format($item->hits) }} hits</span></div>
                        <span class="badge">{{ $item->visitors }} IP</span>
                        <div class="track"><div class="fill" style="width: {{ ($item->hits / $regionMax) * 100 }}%"></div></div>
                    </div>
                @empty
                    <div class="muted">No region data yet.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="card table-card">
        <form class="filters" method="GET">
            <input type="search" name="search" value="{{ $search }}" placeholder="Search IP, region, scanner">
            <select name="region" aria-label="Filter region">
                <option value="">All regions</option>
                @foreach($regionOptions as $option)
                    <option value="{{ $option }}" @selected($region === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <select name="traffic" aria-label="Filter traffic type">
                <option value="">All traffic</option>
                <option value="organic" @selected($trafficType === 'organic')>Organic user</option>
                <option value="crawler" @selected($trafficType === 'crawler')>Crawler bot</option>
                <option value="scanner" @selected($trafficType === 'scanner')>Security scanner</option>
                <option value="automated" @selected($trafficType === 'automated')>Automated client</option>
            </select>
            <select name="sort" aria-label="Sort visitors">
                <option value="hits" @selected($sort === 'hits')>Most hits</option>
                <option value="recent" @selected($sort === 'recent')>Last seen</option>
                <option value="oldest" @selected($sort === 'oldest')>Oldest seen</option>
                <option value="ip" @selected($sort === 'ip')>IP address</option>
            </select>
            <select name="source" aria-label="Filter sumber log">
                <option value="access_log" @selected(($logSource ?? 'access_log') === 'access_log')>Main Apps (access.log)</option>
                <option value="self_log" @selected(($logSource ?? 'access_log') === 'self_log')>Web Log (log.access.log)</option>
            </select>
            <button type="submit">Apply filter</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Country</th>
                        <th>Region / City</th>
                        <th>Source</th>
                        <th>Last Request</th>
                        <th>Hits</th>
                        <th>Last Seen</th>
                    </tr>
                </thead>
                <tbody id="visitorTableBody">
                
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $visitors->links() }}</div>
    </section>
</div>
<script>
    function updateClock() {
        document.getElementById('clock').textContent = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', second:'2-digit'});
    }

    function formatNumber(value) {
        return new Intl.NumberFormat().format(Number(value || 0));
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    async function refreshLiveData() {
        try {
            const params = new URLSearchParams(window.location.search);
            params.set('ajax', '1');

            const response = await fetch('/?' + params.toString(), {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });

            if (!response.ok) throw new Error('Live data unavailable');

            const data = await response.json();

            document.getElementById('totalVisitors').textContent = formatNumber(data.totalVisitors);
            document.getElementById('totalHits').textContent = formatNumber(data.totalHits);
            document.getElementById('activeVisitors').textContent = formatNumber(data.activeVisitors);
            document.getElementById('scannerVisitors').textContent = formatNumber(data.scannerVisitors);
            document.getElementById('scannerHits').textContent = formatNumber(data.scannerHits) + ' scanner hits';

            const points = data.hourlyHits || [];
            const maxHits = Math.max(...points.map(point => Number(point.hits)), 1);
            document.getElementById('trafficChart').innerHTML = points.map(point => {
                const height = Math.max(2, (Number(point.hits) / maxHits) * 100);
                return '<div class="bar-wrap" title="' + escapeHtml(point.label) + ': ' + formatNumber(point.hits) + ' hits">' +
                    '<div class="bar" style="height:' + height + '%"></div></div>';
            }).join('');

            const regions = data.regions || [];
            const regionMax = Math.max(...regions.map(region => Number(region.hits)), 1);
            document.getElementById('regionList').innerHTML = regions.length ? regions.map(region => {
                const width = (Number(region.hits) / regionMax) * 100;
                return '<div class="region-row">' +
                    '<div class="region-name"><span>' + escapeHtml(region.region || 'Unknown') + '</span><span class="muted">' + formatNumber(region.hits) + ' hits</span></div>' +
                    '<span class="badge">' + formatNumber(region.visitors) + ' IP</span>' +
                    '<div class="track"><div class="fill" style="width:' + width + '%"></div></div></div>';
            }).join('') : '<div class="muted">No region data yet.</div>';

            const rows = data.visitors?.data || [];
            const tbody = document.getElementById('visitorTableBody');
            tbody.innerHTML = rows.length ? rows.map(v => `
                <tr>
                    <td class="ip">${escapeHtml(v.ip)}</td>
                    <td>${escapeHtml(v.country)}</td>
                    <td>${escapeHtml(v.region_city)}</td>
                    <td><span class="badge">${escapeHtml(v.source)}</span></td>
                    <td class="muted">${escapeHtml(v.last_request)}</td>
                    <td><span class="badge">${escapeHtml(v.hit_count)}</span></td>
                    <td class="muted">${escapeHtml(v.last_seen)}</td>
                </tr>
            `).join('') : '<tr><td colspan="7" class="muted" style="text-align:center;padding:32px">No visitor data matches the filter.</td></tr>';
        } catch (error) {
            document.getElementById('clock').textContent = 'Reconnect';
        }
    }

    updateClock();
    setInterval(updateClock, 1000);
    refreshLiveData();
    setInterval(refreshLiveData, 3000);
</script>
</body>
</html>
