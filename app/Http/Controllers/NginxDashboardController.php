<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\BlockedIp;
use App\Services\NginxLogParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NginxDashboardController extends Controller
{
    /**
     * Render the main observability dashboard view.
     */
    public function index()
    {
        return view('dashboard.nginx');
    }

    /**
     * API: Get available nodes from real log data.
     */
    public function nodes(): JsonResponse
    {
        $nodes = AccessLog::query()
            ->whereNotNull('server_node')
            ->where('server_node', '!=', '')
            ->distinct()
            ->pluck('server_node');

        return response()->json($nodes);
    }

    /**
     * API: Trigger a manual sync from the real Nginx log file.
     */
    public function sync(NginxLogParserService $parser): JsonResponse
    {
        $newRecords = $parser->syncNewLines();
        $count = count($newRecords);

        return response()->json([
            'synced' => $count,
            'total' => AccessLog::count(),
            'message' => "Disinkronkan {$count} entri baru dari file log Nginx",
        ]);
    }

    /**
     * API: Return KPI summary metrics.
     */
    public function kpis(Request $request, NginxLogParserService $parser): JsonResponse
    {
        $parser->syncNewLines();

        $node = $request->query('node', 'all-nodes');
        $range = $this->resolveRange($request->query('range', '1j'));

        $query = AccessLog::query()
            ->forNode($node)
            ->where('logged_at', '>=', $range);

        $total = $query->count();
        $totalBytes = $query->sum('body_bytes');
        $avgLatency = round((float) $query->avg(DB::raw('request_time * 1000')), 1);
        $p95Latency = $this->computeP95($node, $range);
        $avgUpstream = round((float) $query->whereNotNull('upstream_response_time')->avg(DB::raw('upstream_response_time * 1000')), 1);

        $baseQuery = AccessLog::query()->forNode($node)->where('logged_at', '>=', $range);
        $totalVisitors = (clone $baseQuery)->distinct('client_ip')->count('client_ip');
        $activeHours = max(1, (clone $baseQuery)->distinct()->selectRaw("DATE_FORMAT(logged_at, '%Y-%m-%d %H') as h")->get()->count());
        $activeDays = max(1, (clone $baseQuery)->distinct()->selectRaw("DATE(logged_at) as d")->get()->count());

        $avgVisitorsPerHour = round($totalVisitors / $activeHours, 1);
        $avgVisitorsPerDay = round($totalVisitors / $activeDays, 1);
        $avgVisitorsPerMonth = (int) round($avgVisitorsPerDay * 30);

        $count4xx = (clone $baseQuery)->statusGroup('4xx')->count();
        $count5xx = (clone $baseQuery)->statusGroup('5xx')->count();

        $totalForRate = max($total, 1);
        $err4xx = round(($count4xx / $totalForRate) * 100, 2);
        $err5xx = round(($count5xx / $totalForRate) * 100, 2);

        $rangeSeconds = max(1, now()->diffInSeconds($range));
        $rps = round($total / $rangeSeconds, 1);
        $peakRps = $this->computePeakRps($node, $range);
        $bandwidth = $this->formatBytes($totalBytes / max($rangeSeconds, 1)) . '/s';

        return response()->json([
            'totalRequests' => $total,
            'totalVisitors' => $totalVisitors,
            'avgVisitorsPerHour' => $avgVisitorsPerHour,
            'avgVisitorsPerDay' => $avgVisitorsPerDay,
            'avgVisitorsPerMonth' => $avgVisitorsPerMonth,
            'rps' => $rps,
            'peakRps' => $peakRps,
            'bandwidth' => $bandwidth,
            'err4xx' => $err4xx,
            'err5xx' => $err5xx,
            'errTotal' => round($err4xx + $err5xx, 2),
            'avgLatency' => $avgLatency,
            'p95Latency' => $p95Latency,
            'avgUpstreamLatency' => $avgUpstream,
        ]);
    }

    /**
     * API: Return country and region distribution.
     */
    public function geoDistribution(Request $request): JsonResponse
    {
        $node = $request->query('node', 'all-nodes');
        $range = $this->resolveRange($request->query('range', '1j'));

        $base = AccessLog::query()->forNode($node)->where('logged_at', '>=', $range);
        $totalHits = max(1, (clone $base)->count());

        $countries = (clone $base)
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->selectRaw("country, COUNT(DISTINCT client_ip) as visitors, COUNT(*) as hits")
            ->groupBy('country')
            ->orderByDesc('hits')
            ->limit(7)
            ->get()
            ->map(fn ($c) => [
                'country' => $c->country,
                'visitors' => (int) $c->visitors,
                'hits' => (int) $c->hits,
                'percentage' => round(($c->hits / $totalHits) * 100, 1),
            ]);

        $regions = (clone $base)
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->selectRaw("region, country, COUNT(DISTINCT client_ip) as visitors, COUNT(*) as hits")
            ->groupBy('region', 'country')
            ->orderByDesc('hits')
            ->limit(7)
            ->get()
            ->map(fn ($r) => [
                'region' => $r->region,
                'country' => $r->country,
                'visitors' => (int) $r->visitors,
                'hits' => (int) $r->hits,
                'percentage' => round(($r->hits / $totalHits) * 100, 1),
            ]);

        return response()->json([
            'countries' => $countries,
            'regions' => $regions,
        ]);
    }

    /**
     * API: Return timeline chart data (stacked bar).
     */
    public function timeline(Request $request): JsonResponse
    {
        $node = $request->query('node', 'all-nodes');
        $range = $this->resolveRange($request->query('range', '1j'));

        $interval = $this->getTimelineInterval($request->query('range', '1j'));

        $rawFormat = match ($interval) {
            'minute' => "DATE_FORMAT(logged_at, '%Y-%m-%d %H:%i')",
            'hour' => "DATE_FORMAT(logged_at, '%Y-%m-%d %H')",
            'day' => "DATE(logged_at)",
        };

        $rows = AccessLog::query()
            ->forNode($node)
            ->where('logged_at', '>=', $range)
            ->selectRaw("$rawFormat as bucket")
            ->selectRaw("SUM(CASE WHEN status_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) as s2xx")
            ->selectRaw("SUM(CASE WHEN status_code BETWEEN 300 AND 399 THEN 1 ELSE 0 END) as s3xx")
            ->selectRaw("SUM(CASE WHEN status_code BETWEEN 400 AND 499 THEN 1 ELSE 0 END) as s4xx")
            ->selectRaw("SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) as s5xx")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $labels = $rows->pluck('bucket')->map(function ($b) use ($interval) {
            if ($interval === 'minute') {
                return substr($b, 11);
            }
            if ($interval === 'hour') {
                return substr($b, 11) . ':00';
            }

            return $b;
        });

        return response()->json([
            'labels' => $labels,
            'data2xx' => $rows->pluck('s2xx')->map(fn ($v) => (int) $v),
            'data3xx' => $rows->pluck('s3xx')->map(fn ($v) => (int) $v),
            'data4xx' => $rows->pluck('s4xx')->map(fn ($v) => (int) $v),
            'data5xx' => $rows->pluck('s5xx')->map(fn ($v) => (int) $v),
        ]);
    }

    /**
     * API: Return status code breakdown for doughnut chart.
     */
    public function statusBreakdown(Request $request): JsonResponse
    {
        $node = $request->query('node', 'all-nodes');
        $range = $this->resolveRange($request->query('range', '1j'));

        $rows = AccessLog::query()
            ->forNode($node)
            ->where('logged_at', '>=', $range)
            ->selectRaw('status_code, COUNT(*) as cnt')
            ->groupBy('status_code')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        $statusTextMap = [
            200 => '200 OK', 201 => '201 Created', 204 => '204 No Content',
            301 => '301 Moved', 302 => '302 Found', 304 => '304 Not Modified',
            400 => '400 Bad Request', 401 => '401 Unauthorized', 403 => '403 Forbidden',
            404 => '404 Not Found', 405 => '405 Method Not Allowed', 429 => '429 Rate Limit',
            500 => '500 Internal Error', 502 => '502 Bad Gateway', 504 => '504 Gateway Timeout',
        ];

        $total = max($rows->sum('cnt'), 1);
        $count2xx = $rows->filter(fn ($r) => $r->status_code >= 200 && $r->status_code < 300)->sum('cnt');

        return response()->json([
            'labels' => $rows->map(fn ($r) => $statusTextMap[$r->status_code] ?? (string) $r->status_code),
            'values' => $rows->pluck('cnt')->map(fn ($v) => (int) $v),
            'ratio2xx' => round(($count2xx / $total) * 100, 1),
        ]);
    }

    /**
     * API: Return top requested endpoints.
     */
    public function topEndpoints(Request $request): JsonResponse
    {
        $node = $request->query('node', 'all-nodes');
        $range = $this->resolveRange($request->query('range', '1j'));

        $suspiciousPaths = ['wp-login', '.env', 'phpmyadmin', 'xmlrpc', 'wp-admin', '.git', 'config.php', 'shell'];

        $rows = AccessLog::query()
            ->forNode($node)
            ->where('logged_at', '>=', $range)
            ->selectRaw("method, SUBSTRING_INDEX(uri, '?', 1) as path")
            ->selectRaw('COUNT(*) as hits')
            ->selectRaw('ROUND(AVG(request_time * 1000), 1) as avg_latency')
            ->selectRaw('SUM(body_bytes) as total_bytes')
            ->groupBy('method', 'path')
            ->orderByDesc('hits')
            ->limit(10)
            ->get();

        return response()->json($rows->map(function ($row) use ($suspiciousPaths) {
            $suspicious = false;
            foreach ($suspiciousPaths as $p) {
                if (stripos($row->path, $p) !== false) {
                    $suspicious = true;
                    break;
                }
            }

            return [
                'method' => $row->method,
                'path' => $row->path,
                'hits' => (int) $row->hits,
                'latency' => (float) $row->avg_latency,
                'bandwidth' => $this->formatBytes((int) $row->total_bytes),
                'suspicious' => $suspicious,
            ];
        }));
    }

    /**
     * API: Return top client IPs with anomaly detection.
     */
    public function topClients(Request $request): JsonResponse
    {
        $node = $request->query('node', 'all-nodes');
        $range = $this->resolveRange($request->query('range', '1j'));

        $blockedIps = BlockedIp::pluck('ip')->toArray();

        $rows = AccessLog::query()
            ->forNode($node)
            ->where('logged_at', '>=', $range)
            ->selectRaw('client_ip, geo_label')
            ->selectRaw('COUNT(*) as requests')
            ->selectRaw('ROUND(SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as error_rate')
            ->groupBy('client_ip', 'geo_label')
            ->orderByDesc('requests')
            ->limit(10)
            ->get();

        return response()->json([
            'blockedCount' => count($blockedIps),
            'clients' => $rows->map(fn ($r) => [
                'ip' => $r->client_ip,
                'geo' => $r->geo_label ?? 'Unknown Client',
                'requests' => (int) $r->requests,
                'errorRate' => (float) $r->error_rate,
                'suspicious' => $r->error_rate > 20,
                'blocked' => in_array($r->client_ip, $blockedIps),
            ]),
        ]);
    }

    /**
     * API: Return recent access log entries for the live stream table.
     */
    public function logs(Request $request, NginxLogParserService $parser): JsonResponse
    {
        $parser->syncNewLines();

        $node = $request->query('node', 'all-nodes');
        $statusFilter = $request->query('status', 'all');
        $search = $request->query('q', '');

        $blockedIps = BlockedIp::pluck('ip')->toArray();

        $query = AccessLog::query()
            ->forNode($node)
            ->orderByDesc('logged_at')
            ->limit(80);

        if ($statusFilter !== 'all') {
            $query->statusGroup($statusFilter);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('client_ip', 'like', "%{$search}%")
                    ->orWhere('uri', 'like', "%{$search}%")
                    ->orWhere('user_agent', 'like', "%{$search}%");
            });
        }

        $logs = $query->get();

        return response()->json($logs->map(fn ($log) => [
            'id' => $log->request_id,
            'timeShort' => $log->logged_at->format('H:i:s.v'),
            'timeClf' => $log->logged_at->format('d/M/Y:H:i:s O'),
            'clientIp' => $log->client_ip,
            'method' => $log->method,
            'uri' => $log->uri,
            'protocol' => $log->protocol,
            'status' => $log->status_code,
            'bytes' => $log->body_bytes,
            'rtMs' => (int) round($log->request_time * 1000),
            'rtSec' => number_format($log->request_time, 3),
            'urtSec' => $log->upstream_response_time !== null ? number_format($log->upstream_response_time, 3) : '-',
            'upstreamAddr' => $log->upstream_addr ?? '-',
            'referer' => $log->referer ?? '-',
            'userAgent' => $log->user_agent ?? '-',
            'node' => $log->server_node ?? '-',
            'rawLine' => $log->raw_line,
            'isBlocked' => in_array($log->client_ip, $blockedIps),
        ]));
    }

    /**
     * API: Block or unblock an IP address.
     */
    public function toggleBlockIp(Request $request): JsonResponse
    {
        $request->validate(['ip' => 'required|ip']);
        $ip = $request->input('ip');

        $existing = BlockedIp::where('ip', $ip)->first();

        if ($existing) {
            $existing->delete();

            return response()->json([
                'blocked' => false,
                'message' => "Aturan blokir dicabut untuk IP {$ip}",
            ]);
        }

        BlockedIp::create(['ip' => $ip, 'reason' => 'Dashboard block']);

        return response()->json([
            'blocked' => true,
            'message' => "IP {$ip} diblokir (Nginx mengembalikan HTTP 403)",
        ]);
    }

    /**
     * API: Inject simulated 5xx error log entries (for spike simulation).
     */
    public function simulate5xx(Request $request): JsonResponse
    {
        $errorScenarios = [
            ['method' => 'POST', 'uri' => '/api/v1/orders/checkout', 'status' => 502, 'bytes' => 552, 'rt' => 1.482],
            ['method' => 'GET', 'uri' => '/api/v1/products?sort=popular', 'status' => 504, 'bytes' => 564, 'rt' => 5.002],
            ['method' => 'POST', 'uri' => '/api/v1/payments/webhook', 'status' => 500, 'bytes' => 488, 'rt' => 0.642],
        ];

        $nodes = AccessLog::where('log_source', 'access_log')
            ->distinct()
            ->pluck('server_node')
            ->filter()
            ->values()
            ->toArray();

        if (empty($nodes)) {
            $nodes = ['rash.net'];
        }

        $ips = ['103.175.230.48', '127.0.0.1'];
        $count = $request->integer('count', 6);
        $injected = 0;

        for ($i = 0; $i < $count; $i++) {
            $scenario = $errorScenarios[array_rand($errorScenarios)];
            $node = $nodes[array_rand($nodes)];
            $ip = $ips[array_rand($ips)];
            $rt = round($scenario['rt'] * (0.8 + (mt_rand() / mt_getrandmax()) * 0.45), 3);
            $urt = round($rt * 0.91, 3);
            $reqId = 'req-' . Str::random(8);
            $loggedAt = now()->subSeconds(rand(0, 30));

            $rawLine = sprintf(
                '%s - - [%s] "%s %s HTTP/1.1" %d %d "-" "Mozilla/5.0" rt=%.3f urt="%.3f" uaddr="10.0.4.21:8080" node="%s" req_id="%s"',
                $ip, $loggedAt->format('d/M/Y:H:i:s O'),
                $scenario['method'], $scenario['uri'], $scenario['status'], $scenario['bytes'],
                $rt, $urt, $node, $reqId,
            );

            AccessLog::create([
                'request_id' => $reqId,
                'log_source' => 'access_log',
                'client_ip' => $ip,
                'method' => $scenario['method'],
                'uri' => $scenario['uri'],
                'status_code' => $scenario['status'],
                'body_bytes' => $scenario['bytes'],
                'request_time' => $rt,
                'upstream_response_time' => $urt,
                'upstream_addr' => '10.0.4.21:8080',
                'server_node' => $node,
                'user_agent' => 'Mozilla/5.0',
                'geo_label' => 'Simulated Alert',
                'raw_line' => $rawLine,
                'logged_at' => $loggedAt,
            ]);

            $injected++;
        }

        return response()->json([
            'injected' => $injected,
            'message' => "Disuntikkan {$injected} log error 5xx untuk simulasi",
        ]);
    }

    /**
     * API: Check for REAL newly written log lines from Nginx access log file.
     */
    public function generateLive(Request $request, NginxLogParserService $parser): JsonResponse
    {
        $node = $request->query('node', 'all-nodes');
        $newRecords = $parser->syncNewLines();

        $filteredRecords = array_values(array_filter($newRecords, function ($log) use ($node) {
            if ($node === 'log.rashfox.web.id' || $node === 'self_log') {
                return $log->log_source === 'self_log';
            }

            if ($log->log_source !== 'access_log') {
                return false;
            }

            if ($node && $node !== 'all-nodes') {
                return $log->server_node === $node;
            }

            return true;
        }));

        if (empty($filteredRecords)) {
            return response()->json([
                'hasNew' => false,
                'logs' => [],
            ]);
        }

        $blockedIps = BlockedIp::pluck('ip')->toArray();

        $formatted = array_map(fn ($log) => [
            'id' => $log->request_id,
            'timeShort' => $log->logged_at->format('H:i:s.v'),
            'timeClf' => $log->logged_at->format('d/M/Y:H:i:s O'),
            'clientIp' => $log->client_ip,
            'method' => $log->method,
            'uri' => $log->uri,
            'protocol' => $log->protocol,
            'status' => $log->status_code,
            'bytes' => $log->body_bytes,
            'rtMs' => (int) round($log->request_time * 1000),
            'rtSec' => number_format($log->request_time, 3),
            'urtSec' => $log->upstream_response_time !== null ? number_format($log->upstream_response_time, 3) : '-',
            'upstreamAddr' => $log->upstream_addr ?? '-',
            'referer' => $log->referer ?? '-',
            'userAgent' => $log->user_agent ?? '-',
            'node' => $log->server_node ?? '-',
            'rawLine' => $log->raw_line,
            'isBlocked' => in_array($log->client_ip, $blockedIps),
            'isNew' => true,
        ], $filteredRecords);

        return response()->json([
            'hasNew' => true,
            'logs' => $formatted,
        ]);
    }

    // --- Helper methods ---

    private function resolveRange(string $range): \Carbon\Carbon
    {
        return match ($range) {
            '15m' => now()->subMinutes(15),
            '1j' => now()->subHour(),
            '24j' => now()->subDay(),
            '7h' => now()->subDays(7),
            default => now()->subHour(),
        };
    }

    private function getTimelineInterval(string $range): string
    {
        return match ($range) {
            '15m' => 'minute',
            '1j' => 'minute',
            '24j' => 'hour',
            '7h' => 'day',
            default => 'minute',
        };
    }

    private function computeP95(string $node, $range): int
    {
        $count = AccessLog::query()->forNode($node)->where('logged_at', '>=', $range)->count();
        if ($count === 0) {
            return 0;
        }

        $offset = max(0, (int) floor($count * 0.95) - 1);

        $val = AccessLog::query()
            ->forNode($node)
            ->where('logged_at', '>=', $range)
            ->orderBy('request_time')
            ->offset($offset)
            ->limit(1)
            ->value('request_time');

        return (int) round(($val ?? 0) * 1000);
    }

    private function computePeakRps(string $node, $range): float
    {
        $peak = AccessLog::query()
            ->forNode($node)
            ->where('logged_at', '>=', $range)
            ->selectRaw("DATE_FORMAT(logged_at, '%Y-%m-%d %H:%i') as minute_bucket, COUNT(*) as cnt")
            ->groupBy('minute_bucket')
            ->orderByDesc('cnt')
            ->limit(1)
            ->value('cnt');

        return round(($peak ?? 0) / 60, 1);
    }

    private function formatBytes(int|float $bytes): string
    {
        $bytes = (int) $bytes;
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }
}
