<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $region = trim((string) $request->query('region', ''));
        $trafficType = trim((string) $request->query('traffic', ''));
        $sort = $request->query('sort', 'hits');
        $logSource = in_array($request->query('source'), ['access_log', 'self_log'], true)
            ? $request->query('source')
            : 'access_log';

        $baseVisitor = fn () => Visitor::query()->forLogSource($logSource);

        $totalVisitors = $baseVisitor()->count();
        $totalHits = $baseVisitor()->sum('hit_count');
        $activeVisitors = $baseVisitor()->where('last_seen', '>=', now()->subMinutes(5))->count();
        $scannerVisitors = $baseVisitor()->where('is_scanner', true)->count();
        $scannerHits = $baseVisitor()->where('is_scanner', true)->sum('hit_count');

        $visitors = $baseVisitor()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('ip', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%")
                        ->orWhere('region', 'like', "%{$search}%")
                        ->orWhere('scanner_name', 'like', "%{$search}%");
                });
            })
            ->when($region !== '', fn ($query) => $query->where('region', $region))
            ->when($trafficType !== '', fn ($query) => $query->where('traffic_type', $trafficType))
            ->when($sort === 'ip', fn ($query) => $query->orderBy('ip'))
            ->when($sort === 'recent', fn ($query) => $query->orderByDesc('last_seen'))
            ->when($sort === 'oldest', fn ($query) => $query->orderBy('last_seen'))
            ->when(! in_array($sort, ['ip', 'recent', 'oldest'], true), fn ($query) => $query->orderByDesc('hit_count'))
            ->paginate(15)
            ->withQueryString();

        $regions = $baseVisitor()
            ->selectRaw("COALESCE(region, 'Unknown') as region")
            ->selectRaw('COUNT(*) as visitors')
            ->selectRaw('SUM(hit_count) as hits')
            ->groupByRaw("COALESCE(region, 'Unknown')")
            ->orderByDesc('hits')
            ->get();

        $hourlyHits = collect();
        for ($hour = 23; $hour >= 0; $hour--) {
            $start = now()->subHours($hour)->startOfHour();
            $end = $start->copy()->endOfHour();
            $hourlyHits->push([
                'label' => $start->format('H:i'),
                'hits' => $baseVisitor()->whereBetween('last_seen', [$start, $end])->sum('hit_count'),
            ]);
        }

        if ($request->boolean('ajax')) {
            return response()->json([
                'logSource' => $logSource,
                'totalVisitors' => $totalVisitors,
                'totalHits' => $totalHits,
                'activeVisitors' => $activeVisitors,
                'scannerVisitors' => $scannerVisitors,
                'scannerHits' => $scannerHits,
                'hourlyHits' => $hourlyHits,
                'regions' => $regions->take(8)->values(),
                'visitors' => $visitors->through(fn ($v) => [
                    'ip' => $v->ip,
                    'country' => $v->country ?: 'Unknown',
                    'region_city' => collect([$v->region, $v->city])->filter()->join(' / ') ?: 'Unknown',
                    'source' => match ($v->traffic_type) {
                        'scanner' => $v->scanner_name ?: 'Scanner',
                        'crawler' => $v->bot_name ?: 'Crawler',
                        'automated' => 'Automated',
                        default => 'Organic',
                    },
                    'last_request' => $v->method ? "{$v->method} {$v->url} [{$v->status_code}]" : '—',
                    'hit_count' => number_format($v->hit_count),
                    'last_seen' => optional($v->last_seen)->diffForHumans() ?? '—',
                ]),
            ]);
        }

        $regionOptions = $baseVisitor()
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region');

        $topIps = $baseVisitor()->orderByDesc('hit_count')->limit(10)->get();
        $trafficCounts = $baseVisitor()
            ->selectRaw('traffic_type')
            ->selectRaw('COUNT(*) as visitors')
            ->selectRaw('SUM(hit_count) as hits')
            ->groupBy('traffic_type')
            ->pluck('hits', 'traffic_type');

        return view('analytics.index', compact(
            'logSource',
            'totalVisitors',
            'totalHits',
            'activeVisitors',
            'scannerVisitors',
            'scannerHits',
            'visitors',
            'regions',
            'regionOptions',
            'topIps',
            'hourlyHits',
            'search',
            'region',
            'trafficType',
            'trafficCounts',
            'sort'
        ));
    }
}