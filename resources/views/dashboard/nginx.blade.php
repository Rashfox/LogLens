<!DOCTYPE html>
<html lang="id" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>LogLens | Monitoring Access Logs</title>
  <meta name="description" content="LogLens — Dashboard monitoring Nginx access log real-time untuk analisis trafik HTTP, latensi upstream, distribusi geografis, dan deteksi anomali IP." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet" />

  {{-- Inisialisasi tema sebelum render agar tidak berkedip --}}
  <script>
    (function() {
      const savedTheme = localStorage.getItem('loglens-theme');
      if (savedTheme === 'light') {
        document.documentElement.classList.remove('dark');
      } else {
        document.documentElement.classList.add('dark');
      }
    })();
  </script>

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'system-ui', 'sans-serif'],
            mono: ['JetBrains Mono', 'monospace'],
          },
          colors: {
            slate: { 850: '#162032', 900: '#0f172a', 950: '#090e1a' }
          }
        }
      }
    };
  </script>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

  <style>
    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    html.dark ::-webkit-scrollbar-track { background: #0f172a; }
    html:not(.dark) ::-webkit-scrollbar-track { background: #f1f5f9; }
    html.dark ::-webkit-scrollbar-thumb { background: #334155; border-radius: 9999px; }
    html:not(.dark) ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
    ::-webkit-scrollbar-thumb:hover { background: #64748b; }

    /* Hide scrollbar for clean horizontal button lists */
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* Flash animations for incoming logs */
    @keyframes rowFlash { 0% { background-color: rgba(16, 185, 129, 0.22); } 100% { background-color: transparent; } }
    @keyframes rowFlashError { 0% { background-color: rgba(244, 63, 94, 0.28); } 100% { background-color: rgba(244, 63, 94, 0.05); } }
    .log-row-new { animation: rowFlash 1.4s ease-out forwards; }
    .log-row-new-error { animation: rowFlashError 1.8s ease-out forwards; }

    /* Drawer Inspector transitions */
    .drawer-backdrop { transition: opacity 0.25s ease; }
    .drawer-panel { transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
  </style>
</head>
<body class="bg-slate-50 dark:bg-[#0f172a] text-slate-800 dark:text-slate-200 font-sans antialiased selection:bg-emerald-500/30 selection:text-emerald-900 dark:selection:text-emerald-200 min-h-screen flex flex-col transition-colors duration-200">

  {{-- ==================== TOP NAVIGATION BAR ==================== --}}
  <header class="sticky top-0 z-30 bg-white/95 dark:bg-[#0f172a]/95 backdrop-blur-md border-b border-slate-200 dark:border-[#334155] transition-colors">
    <div class="max-w-[1600px] mx-auto px-3.5 sm:px-6 py-2.5 sm:py-3 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
      
      {{-- Brand & Status --}}
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 sm:gap-3">
          <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center shadow-inner shrink-0">
            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polygon points="12 2 21 7 21 17 12 22 3 17 3 7 12 2"></polygon>
              <polyline points="8.5 15.5 8.5 8.5 15.5 15.5 15.5 8.5"></polyline>
            </svg>
          </div>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <h1 class="text-sm sm:text-base lg:text-lg font-bold tracking-tight text-slate-900 dark:text-white leading-tight">Nginx Access Log Monitor</h1>
              <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-mono font-medium bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/25">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
                Active
              </span>
            </div>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 hidden sm:block">Reverse Proxy &amp; HTTP Access Telemetry</p>
          </div>
        </div>
      </div>

      {{-- Controls Toolbar --}}
      <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
        
        {{-- Node Selector --}}
        <div class="relative flex-1 sm:flex-initial min-w-[160px] sm:min-w-[190px]">
          <label for="nodeSelector" class="sr-only">Pilih Server Node</label>
          <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-500 dark:text-slate-400">
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <rect x="2" y="3" width="20" height="6" rx="1.5" stroke-width="1.8"></rect>
              <rect x="2" y="15" width="20" height="6" rx="1.5" stroke-width="1.8"></rect>
              <circle cx="6" cy="6" r="1" fill="currentColor"></circle>
              <circle cx="6" cy="18" r="1" fill="currentColor"></circle>
            </svg>
          </div>
          <select id="nodeSelector" class="w-full bg-slate-100 dark:bg-[#1e293b] hover:bg-slate-200/70 dark:hover:bg-slate-800 text-slate-800 dark:text-slate-100 text-xs font-mono pl-8 pr-7 py-2 rounded-lg border border-slate-300 dark:border-[#334155] focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition cursor-pointer">
            <option value="all-nodes">all-nodes (Cluster)</option>
          </select>
        </div>

        {{-- Time Range Selector --}}
        <div class="inline-flex rounded-lg bg-slate-100 dark:bg-[#1e293b] p-0.5 border border-slate-300 dark:border-[#334155] shrink-0" role="group" aria-label="Rentang Waktu">
          <button type="button" data-range="15m" class="time-range-btn px-2 sm:px-2.5 py-1.5 text-xs font-mono font-medium rounded-md text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer">15m</button>
          <button type="button" data-range="1j" class="time-range-btn px-2 sm:px-2.5 py-1.5 text-xs font-mono font-medium rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm transition cursor-pointer">1j</button>
          <button type="button" data-range="24j" class="time-range-btn px-2 sm:px-2.5 py-1.5 text-xs font-mono font-medium rounded-md text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer">24j</button>
          <button type="button" data-range="7h" class="time-range-btn px-2 sm:px-2.5 py-1.5 text-xs font-mono font-medium rounded-md text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer">7h</button>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
          {{-- Theme Toggle Button --}}
          <button id="themeToggleBtn" type="button" class="inline-flex items-center justify-center p-2 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600 transition active:scale-95 cursor-pointer" title="Ubah Tema Gelap / Terang" aria-label="Ubah Tema">
            {{-- Icon Matahari (muncul saat mode gelap untuk pindah ke terang) --}}
            <svg id="themeIconSun" class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="5" stroke-width="2"></circle>
              <path stroke-linecap="round" stroke-width="2" d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path>
            </svg>
            {{-- Icon Bulan (muncul saat mode terang untuk pindah ke gelap) --}}
            <svg id="themeIconMoon" class="w-4 h-4 text-slate-700 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path>
            </svg>
          </button>

          {{-- Sync Log Button --}}
          <button id="syncLogsBtn" type="button" class="inline-flex items-center gap-1 px-2.5 sm:px-3 py-2 rounded-lg text-xs font-mono font-medium bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600 transition active:scale-95 cursor-pointer" title="Sinkronkan entri terbaru dari file access.log Nginx">
            <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            <span id="syncLogsText" class="hidden xs:inline sm:inline">Sync</span>
          </button>

          {{-- Simulate 5xx --}}
          <button id="simulate5xxBtn" type="button" class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-2 rounded-lg text-xs font-medium bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30 transition active:scale-95 cursor-pointer" title="Simulasikan lonjakan HTTP 502/504 untuk menguji alert">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            <span class="hidden sm:inline">Simulasi 5xx</span>
          </button>

          {{-- Live Stream Toggle --}}
          <button id="liveStreamBtn" type="button" class="inline-flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-2 rounded-lg text-xs font-mono font-semibold bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-700 dark:text-emerald-300 border border-emerald-500/40 transition cursor-pointer">
            <span class="relative flex h-2 w-2 sm:h-2.5 sm:w-2.5">
              <span id="livePulsePing" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-500 dark:bg-emerald-400 opacity-75"></span>
              <span id="livePulseDot" class="relative inline-flex rounded-full h-2 w-2 sm:h-2.5 sm:w-2.5 bg-emerald-500 dark:bg-emerald-400"></span>
            </span>
            <span id="liveStreamLabel">Live: ON</span>
          </button>
        </div>

      </div>
    </div>

    {{-- Alert Banner Dinamis --}}
    <div id="spikeAlertBanner" class="hidden bg-rose-100 dark:bg-rose-950/90 border-t border-rose-400 dark:border-rose-500/40 px-3.5 sm:px-6 py-2">
      <div class="max-w-[1600px] mx-auto flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
        <div class="flex items-center gap-2 text-rose-800 dark:text-rose-200">
          <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-rose-500/25 text-rose-700 dark:text-rose-300 font-bold animate-pulse">!</span>
          <span>
            <strong class="font-semibold text-rose-950 dark:text-rose-100">[ALERT KRITIS]</strong> Terdeteksi lonjakan <code class="font-mono bg-rose-200 dark:bg-rose-900/70 px-1 py-0.5 rounded text-rose-900 dark:text-rose-200">502 Bad Gateway</code> &amp; <code class="font-mono bg-rose-200 dark:bg-rose-900/70 px-1 py-0.5 rounded text-rose-900 dark:text-rose-200">504 Gateway Timeout</code>.
          </span>
        </div>
        <button id="dismissAlertBtn" type="button" class="self-start sm:self-auto text-rose-700 dark:text-rose-300 hover:text-rose-950 dark:hover:text-white underline font-medium cursor-pointer">Redakan Alert</button>
      </div>
    </div>
  </header>

  {{-- ==================== MAIN DASHBOARD ==================== --}}
  <main class="flex-1 max-w-[1600px] w-full mx-auto px-3.5 sm:px-6 py-4 sm:py-6 space-y-4 sm:space-y-6">

    {{-- 1. KPI SUMMARY CARDS --}}
    <section aria-label="Ringkasan Metrik Utama" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
      
      {{-- Card 1: Total Requests --}}
      <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-[#334155] rounded-xl p-3.5 sm:p-5 shadow-sm hover:border-slate-300 dark:hover:border-slate-600 transition">
        <div class="flex items-center justify-between mb-1.5 sm:mb-2">
          <span class="text-[11px] sm:text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Requests</span>
          <span class="p-1 sm:p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-emerald-600 dark:text-emerald-400">
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
          </span>
        </div>
        <div class="flex items-baseline justify-between gap-2 mt-1">
          <div id="kpiTotalRequests" class="text-xl sm:text-2xl lg:text-3xl font-bold font-mono text-slate-900 dark:text-white tracking-tight">—</div>
        </div>
        <p id="kpiTotalSubtext" class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-2 truncate">Akumulasi HTTP hits pada <span class="font-mono text-slate-700 dark:text-slate-300">all-nodes</span></p>
      </div>

      {{-- Card 2: Throughput / RPS --}}
      <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-[#334155] rounded-xl p-3.5 sm:p-5 shadow-sm hover:border-slate-300 dark:hover:border-slate-600 transition">
        <div class="flex items-center justify-between mb-1.5 sm:mb-2">
          <span class="text-[11px] sm:text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Throughput / RPS</span>
          <span class="p-1 sm:p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sky-600 dark:text-sky-400">
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
          </span>
        </div>
        <div class="flex items-baseline justify-between gap-2 mt-1">
          <div class="flex items-baseline gap-1.5">
            <span id="kpiRps" class="text-xl sm:text-2xl lg:text-3xl font-bold font-mono text-slate-900 dark:text-white tracking-tight">—</span>
            <span class="text-[11px] sm:text-xs font-mono text-slate-500 dark:text-slate-400">req/s</span>
          </div>
          <span id="kpiPeakRps" class="text-[10px] sm:text-xs font-mono px-1.5 sm:px-2 py-0.5 rounded-md bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">Peak: —/s</span>
        </div>
        <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-2 truncate">Bandwidth keluar rata-rata: <span id="kpiBandwidth" class="font-mono text-slate-700 dark:text-slate-300">—</span></p>
      </div>

      {{-- Card 3: Error Rate --}}
      <div id="kpiErrorCard" class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-[#334155] rounded-xl p-3.5 sm:p-5 shadow-sm hover:border-slate-300 dark:hover:border-slate-600 transition">
        <div class="flex items-center justify-between mb-1.5 sm:mb-2">
          <span class="text-[11px] sm:text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Error Rate (4xx / 5xx)</span>
          <span class="p-1 sm:p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-amber-600 dark:text-amber-400">
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
          </span>
        </div>
        <div class="flex items-baseline justify-between gap-2 mt-1">
          <div id="kpiErrorRate" class="text-xl sm:text-2xl lg:text-3xl font-bold font-mono text-slate-900 dark:text-white tracking-tight">—</div>
          <div class="flex items-center gap-1 text-[10px] sm:text-xs font-mono">
            <span id="kpi4xxBadge" class="px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">4xx: —</span>
            <span id="kpi5xxBadge" class="px-1.5 py-0.5 rounded bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30">5xx: —</span>
          </div>
        </div>
        <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-2 truncate">Ambang batas SLA error 5xx: <span class="font-mono text-slate-700 dark:text-slate-300">&lt; 1.00%</span></p>
      </div>

      {{-- Card 4: Avg Latency --}}
      <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-[#334155] rounded-xl p-3.5 sm:p-5 shadow-sm hover:border-slate-300 dark:hover:border-slate-600 transition">
        <div class="flex items-center justify-between mb-1.5 sm:mb-2">
          <span class="text-[11px] sm:text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Avg Latency (Request Time)</span>
          <span class="p-1 sm:p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-purple-600 dark:text-purple-400">
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"></circle><polyline points="12 7 12 12 15 15" stroke-width="2"></polyline></svg>
          </span>
        </div>
        <div class="flex items-baseline justify-between gap-2 mt-1">
          <div class="flex items-baseline gap-1.5">
            <span id="kpiLatency" class="text-xl sm:text-2xl lg:text-3xl font-bold font-mono text-slate-900 dark:text-white tracking-tight">—</span>
            <span class="text-[11px] sm:text-xs font-mono text-slate-500 dark:text-slate-400">ms</span>
          </div>
          <span id="kpiP95Latency" class="text-[10px] sm:text-xs font-mono px-1.5 sm:px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">P95: — ms</span>
        </div>
        <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-2 truncate">Avg Upstream Latency: <span id="kpiUpstreamLatency" class="font-mono text-emerald-600 dark:text-emerald-400">— ms</span></p>
      </div>

    </section>

    {{-- 1b. VISITOR VELOCITY ROW --}}
    <section aria-label="Metrik Pengunjung Unik" class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">

      {{-- Visitors Total --}}
      <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-[#334155] rounded-xl p-3 sm:p-4 shadow-sm hover:border-slate-300 dark:hover:border-slate-600 transition">
        <div class="flex items-center justify-between mb-1">
          <span class="text-[10px] sm:text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Unique Visitors</span>
          <span class="p-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-teal-600 dark:text-teal-400">
            <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          </span>
        </div>
        <div id="kpiTotalVisitors" class="text-lg sm:text-2xl font-bold font-mono text-slate-900 dark:text-white tracking-tight">—</div>
        <p class="text-[10px] text-slate-500 mt-1">IP unik pada rentang terpilih</p>
      </div>

      {{-- Avg / Jam --}}
      <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-[#334155] rounded-xl p-3 sm:p-4 shadow-sm hover:border-slate-300 dark:hover:border-slate-600 transition">
        <div class="flex items-center justify-between mb-1">
          <span class="text-[10px] sm:text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Rata-rata / Jam</span>
          <span class="p-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-indigo-600 dark:text-indigo-400">
            <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/><polyline points="12 7 12 12 15 15" stroke-width="2"/></svg>
          </span>
        </div>
        <div id="kpiAvgPerHour" class="text-lg sm:text-2xl font-bold font-mono text-slate-900 dark:text-white tracking-tight">—</div>
        <p class="text-[10px] text-slate-500 mt-1">visitor unik rata-rata per jam</p>
      </div>

      {{-- Avg / Hari --}}
      <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-[#334155] rounded-xl p-3 sm:p-4 shadow-sm hover:border-slate-300 dark:hover:border-slate-600 transition">
        <div class="flex items-center justify-between mb-1">
          <span class="text-[10px] sm:text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Rata-rata / Hari</span>
          <span class="p-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-violet-600 dark:text-violet-400">
            <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke-width="2"/><line x1="16" y1="2" x2="16" y2="6" stroke-width="2"/><line x1="8" y1="2" x2="8" y2="6" stroke-width="2"/><line x1="3" y1="10" x2="21" y2="10" stroke-width="2"/></svg>
          </span>
        </div>
        <div id="kpiAvgPerDay" class="text-lg sm:text-2xl font-bold font-mono text-slate-900 dark:text-white tracking-tight">—</div>
        <p class="text-[10px] text-slate-500 mt-1">visitor unik rata-rata per hari</p>
      </div>

      {{-- Proyeksi / Bulan --}}
      <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-[#334155] rounded-xl p-3 sm:p-4 shadow-sm hover:border-slate-300 dark:hover:border-slate-600 transition">
        <div class="flex items-center justify-between mb-1">
          <span class="text-[10px] sm:text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Proyeksi / Bulan</span>
          <span class="p-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-fuchsia-600 dark:text-fuchsia-400">
            <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
          </span>
        </div>
        <div id="kpiAvgPerMonth" class="text-lg sm:text-2xl font-bold font-mono text-slate-900 dark:text-white tracking-tight">—</div>
        <p class="text-[10px] text-slate-500 mt-1">estimasi visitor unik 30 hari</p>
      </div>

    </section>

    {{-- 2. CHARTS ROW --}}
    <section aria-label="Visualisasi Distribusi HTTP" class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 sm:gap-4">
      
      {{-- Timeline Chart (8 cols on desktop) --}}
      <div class="lg:col-span-8 bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-[#334155] rounded-xl p-3.5 sm:p-5 flex flex-col shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3 sm:mb-4">
          <div>
            <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white">Traffic &amp; Distribusi Status HTTP</h2>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400">Volume request per interval waktu berdasarkan kode respon HTTP</p>
          </div>
          <div class="flex flex-wrap items-center gap-2 sm:gap-3 text-[11px] sm:text-xs font-mono">
            <span class="inline-flex items-center gap-1.5 text-slate-700 dark:text-slate-300"><span class="w-2.5 h-2.5 rounded-sm bg-[#10b981]"></span>2xx OK</span>
            <span class="inline-flex items-center gap-1.5 text-slate-700 dark:text-slate-300"><span class="w-2.5 h-2.5 rounded-sm bg-[#38bdf8]"></span>3xx Redirect</span>
            <span class="inline-flex items-center gap-1.5 text-slate-700 dark:text-slate-300"><span class="w-2.5 h-2.5 rounded-sm bg-[#f59e0b]"></span>4xx Client Err</span>
            <span class="inline-flex items-center gap-1.5 text-slate-700 dark:text-slate-300"><span class="w-2.5 h-2.5 rounded-sm bg-[#f43f5e]"></span>5xx Server Err</span>
          </div>
        </div>
        <div class="relative flex-1 min-h-[220px] sm:min-h-[260px] lg:min-h-[280px] w-full">
          <canvas id="trafficTimelineChart"></canvas>
        </div>
      </div>

      {{-- Doughnut Chart (4 cols on desktop) --}}
      <div class="lg:col-span-4 bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-[#334155] rounded-xl p-3.5 sm:p-5 flex flex-col shadow-sm">
        <div class="mb-2 sm:mb-3">
          <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white">Breakdown Kode Status HTTP</h2>
          <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400">Proporsi kode status spesifik pada rentang terpilih</p>
        </div>
        <div class="relative h-[160px] sm:h-[180px] w-full flex items-center justify-center my-1">
          <canvas id="statusDoughnutChart"></canvas>
          <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
            <span class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400">2xx Ratio</span>
            <span id="doughnutCenterRatio" class="text-base sm:text-lg font-bold font-mono text-emerald-600 dark:text-emerald-400">—%</span>
          </div>
        </div>
        <div id="statusLegendList" class="mt-2.5 sm:mt-3 space-y-1.5 text-[11px] sm:text-xs border-t border-slate-200 dark:border-slate-700/80 pt-2.5 sm:pt-3 max-h-[160px] overflow-y-auto"></div>
      </div>

    </section>

    {{-- 3. ANALYTICAL TABLES --}}
    <section aria-label="Analisis Endpoint dan Klien" class="grid grid-cols-1 xl:grid-cols-2 gap-3.5 sm:gap-4">
      
      {{-- Top Requested Endpoints --}}
      <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-[#334155] rounded-xl overflow-hidden flex flex-col shadow-sm">
        <div class="px-3.5 sm:px-5 py-3 sm:py-4 border-b border-slate-200 dark:border-[#334155] flex items-center justify-between gap-2">
          <div>
            <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white">Top Requested Endpoints</h2>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400">URI paling sering diakses beserta rata-rata latensi</p>
          </div>
          <span class="text-[11px] font-mono px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">Request URI</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse min-w-[500px]">
            <thead>
              <tr class="border-b border-slate-200 dark:border-[#334155] bg-slate-50 dark:bg-slate-900/40 text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                <th class="py-2.5 px-3.5 sm:px-4">Method &amp; Endpoint Path</th>
                <th class="py-2.5 px-3 text-right">Hits</th>
                <th class="py-2.5 px-3 text-right">Avg Latency</th>
                <th class="py-2.5 px-3.5 sm:px-4 text-right">Bandwidth</th>
              </tr>
            </thead>
            <tbody id="topEndpointsBody" class="divide-y divide-slate-200 dark:divide-slate-800 text-xs font-mono"></tbody>
          </table>
        </div>
      </div>

      {{-- Top Client IPs --}}
      <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-[#334155] rounded-xl overflow-hidden flex flex-col shadow-sm">
        <div class="px-3.5 sm:px-5 py-3 sm:py-4 border-b border-slate-200 dark:border-[#334155] flex items-center justify-between gap-2">
          <div>
            <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white">Top Client IPs &amp; Deteksi Anomali</h2>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400">Mitigasi cepat WAF / Nginx deny untuk IP mencurigakan</p>
          </div>
          <span id="blockedIpCounter" class="text-[11px] font-mono px-2 py-0.5 rounded-md bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/25">0 IP Diblokir</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse min-w-[500px]">
            <thead>
              <tr class="border-b border-slate-200 dark:border-[#334155] bg-slate-50 dark:bg-slate-900/40 text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                <th class="py-2.5 px-3.5 sm:px-4">Client IP &amp; Geo / ISP</th>
                <th class="py-2.5 px-3 text-right">Requests</th>
                <th class="py-2.5 px-3 text-right">Error Rate</th>
                <th class="py-2.5 px-3.5 sm:px-4 text-right">Aksi Mitigasi</th>
              </tr>
            </thead>
            <tbody id="topIpsBody" class="divide-y divide-slate-200 dark:divide-slate-800 text-xs font-mono"></tbody>
          </table>
        </div>
      </div>

    </section>

    {{-- 3b. GEO DISTRIBUTION --}}
    <section aria-label="Distribusi Geografis" class="grid grid-cols-1 xl:grid-cols-2 gap-3.5 sm:gap-4">

      {{-- Country Distribution --}}
      <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-[#334155] rounded-xl overflow-hidden flex flex-col shadow-sm">
        <div class="px-3.5 sm:px-5 py-3 sm:py-4 border-b border-slate-200 dark:border-[#334155] flex items-center justify-between gap-2">
          <div>
            <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white">Distribusi Negara Pengunjung</h2>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400">Top negara asal berdasarkan IP address</p>
          </div>
          <span class="text-[11px] font-mono px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">Country</span>
        </div>
        <div id="geoCountriesBody" class="p-3.5 sm:p-5 space-y-3 flex-1">
          <div class="text-xs text-slate-500 font-mono text-center py-6">Memuat data geo...</div>
        </div>
      </div>

      {{-- Region Distribution --}}
      <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-[#334155] rounded-xl overflow-hidden flex flex-col shadow-sm">
        <div class="px-3.5 sm:px-5 py-3 sm:py-4 border-b border-slate-200 dark:border-[#334155] flex items-center justify-between gap-2">
          <div>
            <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white">Distribusi Region / Provinsi</h2>
            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400">Top region asal berdasarkan IP address</p>
          </div>
          <span class="text-[11px] font-mono px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">Region</span>
        </div>
        <div id="geoRegionsBody" class="p-3.5 sm:p-5 space-y-3 flex-1">
          <div class="text-xs text-slate-500 font-mono text-center py-6">Memuat data geo...</div>
        </div>
      </div>

    </section>

    {{-- 4. LIVE NGINX ACCESS LOG STREAM --}}
    <section aria-label="Live Nginx Access Log Stream" class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-[#334155] rounded-xl overflow-hidden shadow-sm">
      
      {{-- Header & Controls --}}
      <div class="p-3.5 sm:p-5 border-b border-slate-200 dark:border-[#334155] flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white">Live Nginx Access Log Stream</h2>
            <span id="logCountBadge" class="text-[11px] sm:text-xs font-mono px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">0 entri</span>
          </div>
          <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-0.5">Klik baris log mana pun untuk membuka detail inspektor log</p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-2.5">
          {{-- Search Input --}}
          <div class="relative flex-1 sm:flex-initial">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
              <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <input id="logSearchInput" type="text" placeholder="Filter URI, IP, atau Agent..." class="w-full sm:w-60 bg-slate-50 dark:bg-[#0f172a] text-slate-800 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 text-xs font-mono pl-8 sm:pl-9 pr-7 py-2 rounded-lg border border-slate-300 dark:border-[#334155] focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500 transition" />
            <button id="clearSearchBtn" type="button" class="hidden absolute inset-y-0 right-0 pr-2.5 items-center text-slate-400 hover:text-slate-700 dark:hover:text-white cursor-pointer">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>

          {{-- Status Filter Pills --}}
          <div class="inline-flex items-center gap-1 bg-slate-100 dark:bg-[#0f172a] p-1 rounded-lg border border-slate-300 dark:border-[#334155] overflow-x-auto no-scrollbar shrink-0" role="group" aria-label="Filter Kode Status">
            <button type="button" data-status-filter="all" class="status-filter-btn px-2 sm:px-2.5 py-1 rounded-md text-xs font-mono font-medium bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs transition cursor-pointer shrink-0">Semua</button>
            <button type="button" data-status-filter="2xx" class="status-filter-btn px-2 sm:px-2.5 py-1 rounded-md text-xs font-mono font-medium text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/10 transition cursor-pointer shrink-0">2xx</button>
            <button type="button" data-status-filter="3xx" class="status-filter-btn px-2 sm:px-2.5 py-1 rounded-md text-xs font-mono font-medium text-sky-600 dark:text-sky-400 hover:bg-sky-500/10 transition cursor-pointer shrink-0">3xx</button>
            <button type="button" data-status-filter="4xx" class="status-filter-btn px-2 sm:px-2.5 py-1 rounded-md text-xs font-mono font-medium text-amber-600 dark:text-amber-400 hover:bg-amber-500/10 transition cursor-pointer shrink-0">4xx</button>
            <button type="button" data-status-filter="5xx" class="status-filter-btn px-2 sm:px-2.5 py-1 rounded-md text-xs font-mono font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-500/10 transition cursor-pointer shrink-0">5xx</button>
          </div>
        </div>
      </div>

      {{-- Table --}}
      <div class="overflow-x-auto max-h-[500px] overflow-y-auto">
        <table class="w-full text-left border-collapse min-w-[780px]">
          <thead class="sticky top-0 z-10 bg-slate-100 dark:bg-[#0f172a] border-b border-slate-200 dark:border-[#334155] text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            <tr>
              <th class="py-2.5 px-3 sm:px-4 whitespace-nowrap">Waktu</th>
              <th class="py-2.5 px-3 whitespace-nowrap">Client IP</th>
              <th class="py-2.5 px-3 whitespace-nowrap">Method</th>
              <th class="py-2.5 px-3 whitespace-nowrap">Request URI</th>
              <th class="py-2.5 px-3 whitespace-nowrap">Status</th>
              <th class="py-2.5 px-3 text-right whitespace-nowrap">Size</th>
              <th class="py-2.5 px-3 text-right whitespace-nowrap">Latency</th>
              <th class="py-2.5 px-3 sm:px-4 whitespace-nowrap">User-Agent</th>
            </tr>
          </thead>
          <tbody id="accessLogTableBody" class="divide-y divide-slate-200/80 dark:divide-slate-800/80 text-xs font-mono"></tbody>
        </table>
      </div>

      <div id="emptyLogState" class="hidden py-12 px-4 text-center">
        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Tidak ada entri log yang cocok dengan filter saat ini.</p>
        <p class="text-xs text-slate-500 mt-1">Coba ubah kata kunci pencarian atau pilih filter status "Semua".</p>
      </div>

    </section>

  </main>

  {{-- ==================== SLIDE-OVER LOG INSPECTOR DRAWER ==================== --}}
  <div id="logInspectorModal" class="fixed inset-0 z-50 hidden" aria-labelledby="inspectorTitle" role="dialog" aria-modal="true">
    <div id="inspectorBackdrop" class="drawer-backdrop fixed inset-0 bg-slate-950/60 dark:bg-slate-950/75 backdrop-blur-xs opacity-0"></div>
    <div class="fixed inset-y-0 right-0 flex max-w-full pl-0 sm:pl-10">
      <div id="inspectorPanel" class="drawer-panel translate-x-full w-screen max-w-full sm:max-w-xl md:max-w-2xl bg-white dark:bg-[#1e293b] border-l border-slate-200 dark:border-[#334155] shadow-2xl flex flex-col h-full">
        
        {{-- Drawer Header --}}
        <div class="px-4 sm:px-5 py-3.5 sm:py-4 bg-slate-50 dark:bg-[#0f172a] border-b border-slate-200 dark:border-[#334155] flex items-center justify-between gap-3">
          <div class="flex items-center gap-2 sm:gap-2.5">
            <span id="inspectorStatusPill" class="px-2 sm:px-2.5 py-0.5 sm:py-1 rounded-md text-xs font-mono font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">200 OK</span>
            <div>
              <h3 id="inspectorTitle" class="text-xs sm:text-sm font-semibold text-slate-900 dark:text-white">Inspektor Detail Access Log</h3>
              <p id="inspectorSubId" class="text-[10px] sm:text-[11px] font-mono text-slate-500 dark:text-slate-400 truncate max-w-[200px] sm:max-w-none">Request ID: —</p>
            </div>
          </div>
          <button id="closeInspectorBtn" type="button" class="p-2 rounded-lg text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-200 dark:hover:bg-slate-800 transition cursor-pointer" aria-label="Tutup Inspektor">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        {{-- Drawer Body --}}
        <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4 sm:space-y-5">
          {{-- Raw Log Box --}}
          <div>
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Raw Nginx Access Log</span>
              <button id="copyRawLogBtn" type="button" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 transition cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2" stroke-width="2"></rect><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" stroke-width="2"></path></svg>
                <span id="copyRawLogText">Copy Raw Log</span>
              </button>
            </div>
            <pre id="inspectorRawLog" class="p-3 sm:p-3.5 rounded-lg bg-slate-900 dark:bg-[#0f172a] border border-slate-700 dark:border-[#334155] text-xs font-mono text-emerald-300 whitespace-pre-wrap break-all leading-relaxed select-all"></pre>
          </div>

          {{-- Parsed Directives Grid --}}
          <div>
            <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2.5">Variabel Nginx Terurai (Parsed Directives)</h4>
            <div id="inspectorParsedGrid" class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-2.5"></div>
          </div>

          {{-- Quick Mitigation Box --}}
          <div class="p-3.5 sm:p-4 rounded-xl bg-slate-50 dark:bg-[#0f172a] border border-slate-200 dark:border-[#334155] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <div class="text-xs font-semibold text-slate-900 dark:text-white">Mitigasi Keamanan IP Klien</div>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Terapkan aturan <code class="font-mono text-slate-700 dark:text-slate-300">deny <span id="inspectorActionIp">0.0.0.0</span>;</code> pada blok server Nginx.</p>
            </div>
            <button id="inspectorBlockIpBtn" type="button" class="px-3 py-2 rounded-lg text-xs font-mono font-semibold bg-rose-500/15 hover:bg-rose-500/25 text-rose-600 dark:text-rose-400 border border-rose-500/30 transition cursor-pointer shrink-0">Block IP</button>
          </div>
        </div>

      </div>
    </div>
  </div>

  {{-- TOAST CONTAINER --}}
  <div id="toastContainer" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none px-4 sm:px-0"></div>

  {{-- ==================== JAVASCRIPT LOGIC ==================== --}}
  <script>
  (function () {
    'use strict';

    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const API = '/api/dashboard';

    /* ---- STATE ---- */
    const state = {
      selectedNode: 'all-nodes',
      selectedRange: '1j',
      isLiveStream: true,
      isSpikeActive: false,
      statusFilter: 'all',
      searchQuery: '',
      logs: [],
      inspectedLog: null,
      liveTimer: null,
    };

    let timelineChart = null;
    let doughnutChart = null;

    /* ---- THEME MANAGEMENT ---- */
    function isDarkMode() {
      return document.documentElement.classList.contains('dark');
    }

    function updateThemeIcons() {
      const dark = isDarkMode();
      document.getElementById('themeIconSun').classList.toggle('hidden', !dark);
      document.getElementById('themeIconMoon').classList.toggle('hidden', dark);
    }

    function updateChartsTheme() {
      const dark = isDarkMode();
      const tickColor = dark ? '#94a3b8' : '#64748b';
      const gridColor = dark ? 'rgba(51,65,85,0.45)' : 'rgba(203,213,225,0.7)';
      const borderColor = dark ? '#1e293b' : '#ffffff';

      if (timelineChart) {
        timelineChart.options.scales.x.ticks.color = tickColor;
        timelineChart.options.scales.y.ticks.color = tickColor;
        timelineChart.options.scales.y.grid.color = gridColor;
        timelineChart.update('none');
      }

      if (doughnutChart) {
        doughnutChart.data.datasets[0].borderColor = borderColor;
        doughnutChart.update('none');
      }
    }

    function toggleTheme() {
      const html = document.documentElement;
      const willBeDark = !html.classList.contains('dark');
      html.classList.toggle('dark', willBeDark);
      localStorage.setItem('loglens-theme', willBeDark ? 'dark' : 'light');
      updateThemeIcons();
      updateChartsTheme();
    }

    /* ---- UTILS ---- */
    function formatNumber(n) { return Math.round(n).toLocaleString('en-US'); }
    function formatBytes(b) {
      if (b < 1024) return b + ' B';
      if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB';
      return (b / (1024 * 1024)).toFixed(2) + ' MB';
    }
    function getStatusText(c) {
      const m = { 200:'200 OK',201:'201 Created',204:'204 No Content',301:'301 Moved',302:'302 Found',304:'304 Not Modified',400:'400 Bad Request',401:'401 Unauthorized',403:'403 Forbidden',404:'404 Not Found',405:'405 Method Not Allowed',429:'429 Rate Limit',499:'499 Client Closed',500:'500 Internal Error',502:'502 Bad Gateway',504:'504 Gateway Timeout' };
      return m[c] || String(c);
    }
    function getStatusGroup(c) { if (c>=500) return '5xx'; if (c>=400) return '4xx'; if (c>=300) return '3xx'; return '2xx'; }
    function statusBadgeCls(c) {
      const g = getStatusGroup(c);
      if (g==='2xx') return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30';
      if (g==='3xx') return 'bg-sky-500/15 text-sky-700 dark:text-sky-400 border-sky-500/30';
      if (g==='4xx') return 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30';
      return 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30';
    }
    function methodBadgeCls(m) {
      if (m==='GET') return 'bg-sky-500/15 text-sky-700 dark:text-sky-400 border-sky-500/30';
      if (m==='POST') return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30';
      if (m==='PUT') return 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30';
      if (m==='DELETE') return 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30';
      return 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600';
    }
    function esc(s) { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

    function showToast(msg, type='info') {
      const c = document.getElementById('toastContainer');
      const colors = {
        success:'border-emerald-500/50 text-emerald-700 dark:text-emerald-300',
        danger:'border-rose-500/50 text-rose-600 dark:text-rose-300',
        warning:'border-amber-500/50 text-amber-700 dark:text-amber-300',
        info:'border-sky-500/50 text-sky-700 dark:text-sky-300'
      };
      const t = document.createElement('div');
      t.className = `pointer-events-auto flex items-center justify-between gap-3 px-4 py-3 rounded-xl border shadow-xl text-xs font-medium transition-all duration-300 transform translate-y-2 opacity-0 bg-white dark:bg-[#1e293b] ${colors[type]||colors.info}`;
      t.innerHTML = `<span>${msg}</span><button type="button" class="text-slate-400 hover:text-slate-700 dark:hover:text-white ml-2 cursor-pointer">&times;</button>`;
      c.appendChild(t);
      requestAnimationFrame(()=>t.classList.remove('translate-y-2','opacity-0'));
      const rm=()=>{ t.classList.add('opacity-0','translate-y-2'); setTimeout(()=>t.remove(),250); };
      t.querySelector('button').addEventListener('click',rm);
      setTimeout(rm,3500);
    }

    async function api(path, opts={}) {
      const url = `${API}/${path}`;
      const res = await fetch(url, { headers: { 'Accept':'application/json', 'X-CSRF-TOKEN': CSRF, ...opts.headers }, ...opts });
      if (!res.ok) throw new Error(`API ${res.status}`);
      return res.json();
    }

    /* ---- DYNAMIC NODES ---- */
    async function fetchNodes() {
      try {
        const nodes = await api('nodes');
        const select = document.getElementById('nodeSelector');
        const current = state.selectedNode;
        select.innerHTML = '<option value="all-nodes">all-nodes (Cluster)</option>';

        const nodeList = Array.from(new Set([...nodes, 'log.rashfox.web.id']));

        nodeList.forEach(n => {
          const opt = document.createElement('option');
          opt.value = n;
          opt.textContent = n === 'log.rashfox.web.id' ? `${n} (Self Log)` : n;
          if (n === current) opt.selected = true;
          select.appendChild(opt);
        });
      } catch (e) { console.error('Nodes fetch error:', e); }
    }

    /* ---- KPIs ---- */
    async function fetchKpis() {
      try {
        const d = await api(`kpis?node=${encodeURIComponent(state.selectedNode)}&range=${state.selectedRange}`);
        document.getElementById('kpiTotalRequests').textContent = formatNumber(d.totalRequests);
        document.getElementById('kpiTotalSubtext').innerHTML = `Akumulasi HTTP hits pada <span class="font-mono text-slate-700 dark:text-slate-300">${esc(state.selectedNode)}</span> (${esc(state.selectedRange)})`;
        document.getElementById('kpiRps').textContent = d.rps;
        document.getElementById('kpiPeakRps').textContent = `Peak: ${d.peakRps}/s`;
        document.getElementById('kpiBandwidth').textContent = d.bandwidth;
        document.getElementById('kpiErrorRate').textContent = d.errTotal + '%';
        document.getElementById('kpi4xxBadge').textContent = `4xx: ${d.err4xx}%`;
        document.getElementById('kpi5xxBadge').textContent = `5xx: ${d.err5xx}%`;
        document.getElementById('kpiLatency').textContent = d.avgLatency;
        document.getElementById('kpiP95Latency').textContent = `P95: ${d.p95Latency} ms`;
        document.getElementById('kpiUpstreamLatency').textContent = `${d.avgUpstreamLatency} ms`;

        // Visitor Velocity KPI row
        document.getElementById('kpiTotalVisitors').textContent = formatNumber(d.totalVisitors ?? 0);
        document.getElementById('kpiAvgPerHour').textContent   = (d.avgVisitorsPerHour ?? 0).toLocaleString('en-US', {minimumFractionDigits:1, maximumFractionDigits:1});
        document.getElementById('kpiAvgPerDay').textContent    = (d.avgVisitorsPerDay  ?? 0).toLocaleString('en-US', {minimumFractionDigits:1, maximumFractionDigits:1});
        document.getElementById('kpiAvgPerMonth').textContent  = formatNumber(d.avgVisitorsPerMonth ?? 0);

        const ec = document.getElementById('kpiErrorCard');
        if (d.err5xx > 1) {
          ec.classList.add('border-rose-500/70','bg-rose-50','dark:bg-rose-950/20');
        } else {
          ec.classList.remove('border-rose-500/70','bg-rose-50','dark:bg-rose-950/20');
        }
      } catch (e) { console.error('KPI fetch error:', e); }
    }

    /* ---- CHARTS ---- */
    async function fetchTimeline() {
      try {
        const d = await api(`timeline?node=${encodeURIComponent(state.selectedNode)}&range=${state.selectedRange}`);
        const ctx = document.getElementById('trafficTimelineChart').getContext('2d');
        const dark = isDarkMode();
        const tickColor = dark ? '#94a3b8' : '#64748b';
        const gridColor = dark ? 'rgba(51,65,85,0.45)' : 'rgba(203,213,225,0.7)';

        if (timelineChart) {
          timelineChart.data.labels = d.labels;
          timelineChart.data.datasets[0].data = d.data2xx;
          timelineChart.data.datasets[1].data = d.data3xx;
          timelineChart.data.datasets[2].data = d.data4xx;
          timelineChart.data.datasets[3].data = d.data5xx;
          timelineChart.update('none');
        } else {
          timelineChart = new Chart(ctx, {
            type: 'bar',
            data: {
              labels: d.labels,
              datasets: [
                { label:'2xx', data:d.data2xx, backgroundColor:'#10b981', borderRadius:2, barPercentage:0.72 },
                { label:'3xx', data:d.data3xx, backgroundColor:'#38bdf8', borderRadius:2, barPercentage:0.72 },
                { label:'4xx', data:d.data4xx, backgroundColor:'#f59e0b', borderRadius:2, barPercentage:0.72 },
                { label:'5xx', data:d.data5xx, backgroundColor:'#f43f5e', borderRadius:2, barPercentage:0.72 },
              ]
            },
            options: {
              responsive:true, maintainAspectRatio:false,
              interaction:{ mode:'index', intersect:false },
              plugins:{ legend:{display:false}, tooltip:{ backgroundColor:'#0f172a', borderColor:'#334155', borderWidth:1, titleFont:{family:'JetBrains Mono',size:11}, bodyFont:{family:'JetBrains Mono',size:10}, padding:8 } },
              scales: {
                x: { stacked:true, grid:{display:false}, ticks:{color:tickColor,font:{family:'JetBrains Mono',size:10}, maxRotation:0} },
                y: { stacked:true, border:{display:false}, grid:{color:gridColor}, ticks:{color:tickColor,font:{family:'JetBrains Mono',size:10},callback:v=>v>=1000?`${(v/1000).toFixed(1)}k`:v} }
              }
            }
          });
        }
      } catch (e) { console.error('Timeline fetch error:', e); }
    }

    async function fetchDoughnut() {
      try {
        const d = await api(`status-breakdown?node=${encodeURIComponent(state.selectedNode)}&range=${state.selectedRange}`);
        document.getElementById('doughnutCenterRatio').textContent = d.ratio2xx + '%';

        const colors = ['#10b981','#38bdf8','#f59e0b','#fb923c','#eab308','#f43f5e','#a78bfa','#6ee7b7','#fbbf24','#fb7185'];
        const ctx = document.getElementById('statusDoughnutChart').getContext('2d');
        const sliceBorder = isDarkMode() ? '#1e293b' : '#ffffff';

        if (doughnutChart) {
          doughnutChart.data.labels = d.labels;
          doughnutChart.data.datasets[0].data = d.values;
          doughnutChart.data.datasets[0].backgroundColor = colors.slice(0, d.values.length);
          doughnutChart.data.datasets[0].borderColor = sliceBorder;
          doughnutChart.update('none');
        } else {
          doughnutChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
              labels: d.labels,
              datasets: [{ data:d.values, backgroundColor:colors.slice(0,d.values.length), borderColor:sliceBorder, borderWidth:2, hoverOffset:4 }]
            },
            options: { responsive:true, maintainAspectRatio:false, cutout:'70%', plugins:{ legend:{display:false}, tooltip:{ backgroundColor:'#0f172a', borderColor:'#334155', borderWidth:1, bodyFont:{family:'JetBrains Mono',size:11} } } }
          });
        }

        const total = d.values.reduce((a,b)=>a+b,0) || 1;
        document.getElementById('statusLegendList').innerHTML = d.labels.map((l,i)=>{
          const v=d.values[i], pct=((v/total)*100).toFixed(2);
          return `<div class="flex items-center justify-between font-mono text-[11px]"><div class="flex items-center gap-2 truncate max-w-[170px] sm:max-w-[210px]"><span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color:${colors[i]}"></span><span class="text-slate-700 dark:text-slate-300 truncate">${esc(l)}</span></div><div class="flex items-center gap-2 shrink-0"><span class="text-slate-500 dark:text-slate-400">${formatNumber(v)}</span><span class="text-slate-900 dark:text-slate-200 font-semibold w-11 text-right">${pct}%</span></div></div>`;
        }).join('');
      } catch (e) { console.error('Doughnut fetch error:', e); }
    }

    /* ---- ANALYTICAL TABLES ---- */
    async function fetchTopEndpoints() {
      try {
        const d = await api(`top-endpoints?node=${encodeURIComponent(state.selectedNode)}&range=${state.selectedRange}`);
        document.getElementById('topEndpointsBody').innerHTML = d.map(item=>{
          const susp = item.suspicious ? `<span class="ml-1.5 px-1 py-0.2 text-[9px] font-sans font-semibold rounded bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30">Probe</span>` : '';
          const latC = item.latency > 100 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400';
          return `<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition"><td class="py-2.5 px-3.5 sm:px-4"><div class="flex items-center flex-wrap gap-1.5"><span class="px-1.5 py-0.5 text-[10px] font-bold rounded border ${methodBadgeCls(item.method)}">${esc(item.method)}</span><span class="text-slate-800 dark:text-slate-200 truncate max-w-[180px] sm:max-w-[280px]" title="${esc(item.path)}">${esc(item.path)}</span>${susp}</div></td><td class="py-2.5 px-3 text-right text-slate-700 dark:text-slate-300">${formatNumber(item.hits)}</td><td class="py-2.5 px-3 text-right ${latC}">${item.latency.toFixed(1)} ms</td><td class="py-2.5 px-3.5 sm:px-4 text-right text-slate-500 dark:text-slate-400">${esc(item.bandwidth)}</td></tr>`;
        }).join('');
      } catch (e) { console.error('Endpoints fetch error:', e); }
    }

    async function fetchTopClients() {
      try {
        const d = await api(`top-clients?node=${encodeURIComponent(state.selectedNode)}&range=${state.selectedRange}`);
        document.getElementById('blockedIpCounter').textContent = `${d.blockedCount} IP Diblokir`;
        document.getElementById('topIpsBody').innerHTML = d.clients.map(c=>{
          const errCls = c.errorRate > 20 ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-slate-500 dark:text-slate-400';
          const btn = c.blocked
            ? `<button type="button" data-toggle-ip="${esc(c.ip)}" class="px-2 py-1 rounded-md text-[10px] font-semibold bg-rose-500/20 text-rose-700 dark:text-rose-300 border border-rose-500/40 hover:bg-rose-500/30 transition cursor-pointer">Blocked (403)</button>`
            : `<button type="button" data-toggle-ip="${esc(c.ip)}" class="px-2 py-1 rounded-md text-[10px] font-medium bg-slate-100 dark:bg-slate-800 hover:bg-rose-500/15 text-slate-700 dark:text-slate-300 hover:text-rose-600 dark:hover:text-rose-300 border border-slate-300 dark:border-slate-700 hover:border-rose-500/40 transition cursor-pointer">Block IP</button>`;
          return `<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition ${c.blocked?'bg-rose-50/70 dark:bg-rose-950/15':''}"><td class="py-2.5 px-3.5 sm:px-4"><div class="flex items-center gap-1.5"><span class="text-slate-900 dark:text-slate-100 font-medium">${esc(c.ip)}</span>${c.suspicious?'<span class="w-1.5 h-1.5 rounded-full bg-rose-500 shrink-0" title="Anomali Terdeteksi"></span>':''}</div><div class="text-[10px] sm:text-[11px] font-sans text-slate-500 dark:text-slate-400 mt-0.5 truncate max-w-[200px] sm:max-w-none">${esc(c.geo)}</div></td><td class="py-2.5 px-3 text-right text-slate-700 dark:text-slate-300">${formatNumber(c.requests)}</td><td class="py-2.5 px-3 text-right ${errCls}">${c.errorRate.toFixed(1)}%</td><td class="py-2.5 px-3.5 sm:px-4 text-right">${btn}</td></tr>`;
        }).join('');
      } catch (e) { console.error('Clients fetch error:', e); }
    }

    async function toggleIpBlock(ip) {
      try {
        const d = await api('toggle-block-ip', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ip}) });
        showToast(d.message, d.blocked ? 'danger' : 'info');
        fetchTopClients();
        fetchLogs();
      } catch (e) { showToast('Gagal mengubah status blokir IP','danger'); }
    }

    /* ---- LOG STREAM TABLE ---- */
    async function fetchLogs() {
      try {
        const params = new URLSearchParams({ node:state.selectedNode, status:state.statusFilter });
        if (state.searchQuery) params.set('q', state.searchQuery);
        const d = await api(`logs?${params}`);
        state.logs = d;
        renderLogTable(d);
      } catch (e) { console.error('Logs fetch error:', e); }
    }

    function applyFilterAndRender() {
      let filtered = state.logs;
      if (state.statusFilter !== 'all') {
        filtered = filtered.filter(l=>getStatusGroup(l.status)===state.statusFilter);
      }
      if (state.searchQuery) {
        const q = state.searchQuery.toLowerCase();
        filtered = filtered.filter(l=>`${l.clientIp} ${l.method} ${l.uri} ${l.status} ${l.userAgent}`.toLowerCase().includes(q));
      }
      renderLogTable(filtered);
    }

    function renderLogTable(logs) {
      const tbody = document.getElementById('accessLogTableBody');
      const empty = document.getElementById('emptyLogState');
      document.getElementById('logCountBadge').textContent = `${logs.length} entri`;

      if (!logs.length) { tbody.innerHTML=''; empty.classList.remove('hidden'); return; }
      empty.classList.add('hidden');

      tbody.innerHTML = logs.map(log=>{
        const is5xx = log.status >= 500;
        const flash = log.isNew ? (is5xx ? 'log-row-new-error':'log-row-new') : '';
        const latC = log.rtMs>=500?'text-rose-600 dark:text-rose-400 font-semibold':(log.rtMs>=150?'text-amber-600 dark:text-amber-400':'text-slate-700 dark:text-slate-300');
        const blocked = log.isBlocked ? `<span class="ml-1 px-1 py-0.2 text-[8px] rounded bg-rose-500/20 text-rose-700 dark:text-rose-300 border border-rose-500/30">BLOCKED</span>` : '';
        return `<tr data-log-id="${esc(log.id)}" class="hover:bg-slate-50 dark:hover:bg-slate-800/75 transition cursor-pointer group ${flash} ${is5xx?'bg-rose-50/70 dark:bg-rose-950/10':''}"><td class="py-2.5 px-3 sm:px-4 text-slate-500 dark:text-slate-400 whitespace-nowrap">${esc(log.timeShort)}</td><td class="py-2.5 px-3 text-slate-800 dark:text-slate-200 whitespace-nowrap">${esc(log.clientIp)}${blocked}</td><td class="py-2.5 px-3 whitespace-nowrap"><span class="px-1.5 py-0.5 text-[10px] font-bold rounded border ${methodBadgeCls(log.method)}">${esc(log.method)}</span></td><td class="py-2.5 px-3 text-slate-800 dark:text-slate-200 max-w-[200px] sm:max-w-[300px] truncate group-hover:text-emerald-600 dark:group-hover:text-emerald-300 transition" title="${esc(log.uri)}">${esc(log.uri)}</td><td class="py-2.5 px-3 whitespace-nowrap"><span class="px-2 py-0.5 text-[10px] sm:text-[11px] font-semibold rounded-md border ${statusBadgeCls(log.status)}">${getStatusText(log.status)}</span></td><td class="py-2.5 px-3 text-right text-slate-500 dark:text-slate-400 whitespace-nowrap">${formatBytes(log.bytes)}</td><td class="py-2.5 px-3 text-right ${latC} whitespace-nowrap">${log.rtMs} ms</td><td class="py-2.5 px-3 sm:px-4 text-slate-500 dark:text-slate-400 max-w-[160px] sm:max-w-[220px] truncate" title="${esc(log.userAgent)}">${esc(log.userAgent)}</td></tr>`;
      }).join('');
    }

    /* ---- LOG INSPECTOR ---- */
    function openInspector(logId) {
      const log = state.logs.find(l=>l.id===logId);
      if (!log) return;
      state.inspectedLog = log;

      const pill = document.getElementById('inspectorStatusPill');
      pill.textContent = getStatusText(log.status);
      pill.className = `px-2.5 py-1 rounded-md text-xs font-mono font-bold border ${statusBadgeCls(log.status)}`;
      document.getElementById('inspectorSubId').textContent = `Request ID: ${log.id} • Node: ${log.node}`;
      document.getElementById('inspectorRawLog').textContent = log.rawLine || '—';
      document.getElementById('inspectorActionIp').textContent = log.clientIp;

      const dirs = [
        {key:'Remote Address',val:log.clientIp},
        {key:'Local Time',val:log.timeClf},
        {key:'Request Method',val:log.method},
        {key:'Status Code',val:getStatusText(log.status)},
        {key:'Request URI',val:log.uri,full:true},
        {key:'Body Bytes Sent',val:`${log.bytes} bytes (${formatBytes(log.bytes)})`},
        {key:'Request Time',val:`${log.rtSec} s (${log.rtMs} ms)`},
        {key:'Upstream Response Time',val:log.urtSec==='-'?'- (Direct / Edge)':`${log.urtSec} s`},
        {key:'Upstream Address',val:log.upstreamAddr},
        {key:'Server Node',val:log.node},
        {key:'Referer',val:log.referer,full:true},
        {key:'User-Agent',val:log.userAgent,full:true},
      ];
      document.getElementById('inspectorParsedGrid').innerHTML = dirs.map(d=>`<div class="p-2 sm:p-2.5 rounded-lg bg-slate-50 dark:bg-[#0f172a] border border-slate-200 dark:border-[#334155] ${d.full?'sm:col-span-2':''}"><div class="text-[10px] sm:text-[11px] font-mono text-slate-500 dark:text-slate-400">${esc(d.key)}</div><div class="text-xs font-mono text-slate-900 dark:text-slate-100 mt-0.5 break-all">${esc(d.val)}</div></div>`).join('');

      const modal = document.getElementById('logInspectorModal');
      const backdrop = document.getElementById('inspectorBackdrop');
      const panel = document.getElementById('inspectorPanel');
      modal.classList.remove('hidden');
      requestAnimationFrame(()=>{ backdrop.classList.remove('opacity-0'); panel.classList.remove('translate-x-full'); });
    }

    function closeInspector() {
      document.getElementById('inspectorBackdrop').classList.add('opacity-0');
      document.getElementById('inspectorPanel').classList.add('translate-x-full');
      setTimeout(()=>{ document.getElementById('logInspectorModal').classList.add('hidden'); state.inspectedLog=null; },280);
    }

    /* ---- LIVE STREAM AUTO-SYNC ---- */
    function startLiveStream() {
      if (state.liveTimer) clearInterval(state.liveTimer);
      state.liveTimer = setInterval(async ()=>{
        if (!state.isLiveStream) return;
        try {
          const res = await api(`generate-live?node=${encodeURIComponent(state.selectedNode)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: '{}'
          });
          if (res.hasNew && res.logs && res.logs.length > 0) {
            for (let i = res.logs.length - 1; i >= 0; i--) {
              state.logs.unshift(res.logs[i]);
            }
            if (state.logs.length > 80) state.logs.length = 80;
            applyFilterAndRender();
            fetchKpis();
          }
        } catch (e) { console.error('Live sync error:', e); }
      }, 2500);
    }

    function toggleLiveStream() {
      state.isLiveStream = !state.isLiveStream;
      const label = document.getElementById('liveStreamLabel');
      const ping = document.getElementById('livePulsePing');
      const dot = document.getElementById('livePulseDot');
      const btn = document.getElementById('liveStreamBtn');
      if (state.isLiveStream) {
        label.textContent='Live: ON';
        btn.className='inline-flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-2 rounded-lg text-xs font-mono font-semibold bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-700 dark:text-emerald-300 border border-emerald-500/40 transition cursor-pointer';
        ping.classList.remove('hidden'); dot.classList.replace('bg-slate-400','bg-emerald-500');
        showToast('Live Stream aktif (interval 2.5 detik)','success');
      } else {
        label.textContent='Live: OFF';
        btn.className='inline-flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-2 rounded-lg text-xs font-mono font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-600 transition cursor-pointer';
        ping.classList.add('hidden'); dot.classList.replace('bg-emerald-500','bg-slate-400');
        showToast('Live Stream dijeda sementara','warning');
      }
    }

    async function triggerManualSync() {
      const text = document.getElementById('syncLogsText');
      text.textContent = '...';
      try {
        const d = await api('sync', { method:'POST', headers:{'Content-Type':'application/json'}, body:'{}' });
        showToast(d.message, 'success');
        await refreshAll();
      } catch (e) {
        showToast('Gagal sinkronisasi log Nginx', 'danger');
      } finally {
        text.textContent = 'Sync';
      }
    }

    async function trigger5xxSpike() {
      state.isSpikeActive = true;
      document.getElementById('spikeAlertBanner').classList.remove('hidden');
      try {
        await api('simulate-5xx', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({count:6}) });
        showToast('Simulasi lonjakan HTTP 502/504 dipicu!','danger');
        refreshAll();
      } catch (e) { showToast('Gagal memicu simulasi 5xx','danger'); }
    }

    function dismiss5xxSpike() {
      state.isSpikeActive = false;
      document.getElementById('spikeAlertBanner').classList.add('hidden');
      showToast('Status upstream kembali normal','success');
      refreshAll();
    }

    /* ---- GEO DISTRIBUTION ---- */
    async function fetchGeoDistribution() {
      try {
        const d = await api(`geo-distribution?node=${encodeURIComponent(state.selectedNode)}&range=${state.selectedRange}`);

        const cBody = document.getElementById('geoCountriesBody');
        if (!d.countries || !d.countries.length) {
          cBody.innerHTML = '<div class="text-xs text-slate-500 font-mono text-center py-6">Belum ada data geo — jalankan ip resolver.</div>';
        } else {
          cBody.innerHTML = d.countries.map((c, i) => {
            const flagColors = ['bg-teal-500','bg-sky-500','bg-indigo-500','bg-violet-500','bg-fuchsia-500','bg-rose-500','bg-amber-500'];
            const barColor   = ['bg-teal-500/80','bg-sky-500/80','bg-indigo-500/80','bg-violet-500/80','bg-fuchsia-500/80','bg-rose-500/80','bg-amber-500/80'];
            return `<div>
              <div class="flex items-center justify-between mb-1 text-xs font-mono">
                <div class="flex items-center gap-2 truncate max-w-[65%]">
                  <span class="w-2 h-2 rounded-full shrink-0 ${flagColors[i % flagColors.length]}"></span>
                  <span class="text-slate-800 dark:text-slate-200 font-medium truncate">${esc(c.country)}</span>
                </div>
                <div class="flex items-center gap-2 shrink-0 text-[11px]">
                  <span class="text-slate-500 dark:text-slate-400">${formatNumber(c.visitors)} visitor</span>
                  <span class="text-slate-900 dark:text-slate-200 font-semibold w-9 text-right">${c.percentage}%</span>
                </div>
              </div>
              <div class="w-full h-1.5 bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500 ${barColor[i % barColor.length]}" style="width:${Math.max(c.percentage, 1)}%"></div>
              </div>
            </div>`;
          }).join('');
        }

        const rBody = document.getElementById('geoRegionsBody');
        if (!d.regions || !d.regions.length) {
          rBody.innerHTML = '<div class="text-xs text-slate-500 font-mono text-center py-6">Belum ada data region — jalankan ip resolver.</div>';
        } else {
          rBody.innerHTML = d.regions.map((r, i) => {
            const flagColors = ['bg-emerald-500','bg-cyan-500','bg-blue-500','bg-purple-500','bg-pink-500','bg-orange-500','bg-yellow-500'];
            const barColor   = ['bg-emerald-500/80','bg-cyan-500/80','bg-blue-500/80','bg-purple-500/80','bg-pink-500/80','bg-orange-500/80','bg-yellow-500/80'];
            return `<div>
              <div class="flex items-center justify-between mb-1 text-xs font-mono">
                <div class="flex items-center gap-2 truncate max-w-[65%]">
                  <span class="w-2 h-2 rounded-full shrink-0 ${flagColors[i % flagColors.length]}"></span>
                  <span class="text-slate-800 dark:text-slate-200 font-medium truncate">${esc(r.region)}</span>
                  <span class="text-slate-400 dark:text-slate-500 text-[10px] shrink-0">${esc(r.country)}</span>
                </div>
                <div class="flex items-center gap-2 shrink-0 text-[11px]">
                  <span class="text-slate-500 dark:text-slate-400">${formatNumber(r.visitors)} visitor</span>
                  <span class="text-slate-900 dark:text-slate-200 font-semibold w-9 text-right">${r.percentage}%</span>
                </div>
              </div>
              <div class="w-full h-1.5 bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500 ${barColor[i % barColor.length]}" style="width:${Math.max(r.percentage, 1)}%"></div>
              </div>
            </div>`;
          }).join('');
        }
      } catch (e) { console.error('Geo distribution fetch error:', e); }
    }

    /* ---- REFRESH ALL ---- */
    async function refreshAll() {
      await Promise.all([fetchNodes(), fetchKpis(), fetchTimeline(), fetchDoughnut(), fetchTopEndpoints(), fetchTopClients(), fetchLogs(), fetchGeoDistribution()]);
    }

    /* ---- EVENT BINDINGS ---- */
    function bindEvents() {
      document.getElementById('themeToggleBtn').addEventListener('click', toggleTheme);
      document.getElementById('nodeSelector').addEventListener('change',e=>{ state.selectedNode=e.target.value; refreshAll(); showToast(`Beralih ke node: ${state.selectedNode}`,'info'); });
      document.getElementById('syncLogsBtn').addEventListener('click', triggerManualSync);

      document.querySelectorAll('.time-range-btn').forEach(btn=>{
        btn.addEventListener('click',()=>{
          state.selectedRange = btn.dataset.range;
          document.querySelectorAll('.time-range-btn').forEach(b=>b.className='time-range-btn px-2 sm:px-2.5 py-1.5 text-xs font-mono font-medium rounded-md text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer');
          btn.className='time-range-btn px-2 sm:px-2.5 py-1.5 text-xs font-mono font-medium rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm transition cursor-pointer';
          refreshAll();
        });
      });

      document.getElementById('liveStreamBtn').addEventListener('click', toggleLiveStream);
      document.getElementById('simulate5xxBtn').addEventListener('click', trigger5xxSpike);
      document.getElementById('dismissAlertBtn').addEventListener('click', dismiss5xxSpike);

      document.querySelectorAll('.status-filter-btn').forEach(btn=>{
        btn.addEventListener('click',()=>{
          state.statusFilter = btn.dataset.statusFilter;
          document.querySelectorAll('.status-filter-btn').forEach(b=>b.classList.remove('bg-white','dark:bg-slate-700','text-slate-900','dark:text-white','shadow-xs'));
          btn.classList.add('bg-white','dark:bg-slate-700','text-slate-900','dark:text-white','shadow-xs');
          fetchLogs();
        });
      });

      const searchInput = document.getElementById('logSearchInput');
      const clearSearch = document.getElementById('clearSearchBtn');
      searchInput.addEventListener('input',e=>{
        state.searchQuery = e.target.value;
        if (state.searchQuery.length>0) { clearSearch.classList.remove('hidden'); clearSearch.classList.add('flex'); }
        else { clearSearch.classList.add('hidden'); clearSearch.classList.remove('flex'); }
        fetchLogs();
      });
      clearSearch.addEventListener('click',()=>{ state.searchQuery=''; searchInput.value=''; clearSearch.classList.add('hidden'); clearSearch.classList.remove('flex'); fetchLogs(); });

      document.getElementById('topIpsBody').addEventListener('click',e=>{ const btn=e.target.closest('[data-toggle-ip]'); if(btn) toggleIpBlock(btn.dataset.toggleIp); });
      document.getElementById('accessLogTableBody').addEventListener('click',e=>{ const row=e.target.closest('tr[data-log-id]'); if(row) openInspector(row.dataset.logId); });
      document.getElementById('closeInspectorBtn').addEventListener('click', closeInspector);
      document.getElementById('inspectorBackdrop').addEventListener('click', closeInspector);
      document.addEventListener('keydown',e=>{ if(e.key==='Escape' && state.inspectedLog) closeInspector(); });
      document.getElementById('inspectorBlockIpBtn').addEventListener('click',()=>{ if(state.inspectedLog) toggleIpBlock(state.inspectedLog.clientIp); });

      document.getElementById('copyRawLogBtn').addEventListener('click',()=>{
        const raw = document.getElementById('inspectorRawLog').textContent;
        const ta = document.createElement('textarea'); ta.value=raw; ta.style.position='fixed'; ta.style.opacity='0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); document.getElementById('copyRawLogText').textContent='Tersalin!'; showToast('Raw log disalin ke clipboard','success'); setTimeout(()=>document.getElementById('copyRawLogText').textContent='Copy Raw Log',2000); }
        catch(e) { showToast('Gagal menyalin log','danger'); }
        finally { document.body.removeChild(ta); }
      });
    }

    /* ---- INIT ---- */
    window.addEventListener('DOMContentLoaded',()=>{
      updateThemeIcons();
      bindEvents();
      refreshAll();
      startLiveStream();
    });
  })();
  </script>
</body>
</html>