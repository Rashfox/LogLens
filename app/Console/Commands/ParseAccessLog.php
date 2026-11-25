<?php

namespace App\Console\Commands;

use App\Models\AccessLog;
use App\Models\Visitor;
use App\Services\NginxLogParserService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('app:parse-access-log {--reset : Rebuild visitor counts and access logs from the beginning}')]
#[Description('Parse real Nginx access log files and sync into database')]
class ParseAccessLog extends Command
{
    public function handle(NginxLogParserService $parser): int
    {
        $lock = Cache::lock('nginx-access-log-parser', 60);
        if (!$lock->get()) {
            $this->warn('Parser sedang berjalan, proses dilewati.');
            return self::SUCCESS;
        }

        try {
            $this->info('Memulai parsing file log Nginx...');
            $reset = (bool) $this->option('reset');

            if ($reset) {
                $this->warn('Reset diaktifkan: membersihkan tabel access_logs dan visitors...');
                Visitor::truncate();
                $count = $parser->importAll(true);
            } else {
                // Jika tanpa --reset, cukup baca baris baru secara inkremental
                $newRecords = $parser->syncNewLines();
                $count = count($newRecords);
            }

            // Sinkronkan agregasi tabel visitors berdasarkan log_source (access_log & self_log)
            $this->syncVisitorsFromLogs();

            $this->info("Log Nginx berhasil diproses: {$count} entri log dimuat & data visitors diperbarui.");
            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    /**
     * Sinkronkan ringkasan visitor unik per IP dan log_source ke tabel visitors.
     */
    private function syncVisitorsFromLogs(): void
    {
        
        $aggregated = AccessLog::query()
            ->select([
                'client_ip',
                'log_source',
                DB::raw('MAX(server_node) as server_node'),
                DB::raw('MAX(country) as country'),
                DB::raw('MAX(region) as region'),
                DB::raw('MAX(city) as city'),
                DB::raw('MAX(user_agent) as user_agent'),
                DB::raw('COUNT(*) as hit_count'),
                DB::raw('MIN(logged_at) as first_seen'),
                DB::raw('MAX(logged_at) as last_seen'),
            ])
            ->groupBy('client_ip', 'log_source')
            ->get();

        foreach ($aggregated as $row) {
            $isScanner = str_starts_with((string) $row->server_node, 'scanner:');
            $trafficType = match (true) {
                $isScanner => 'scanner',
                str_starts_with((string) $row->server_node, 'crawler') => 'crawler',
                str_starts_with((string) $row->server_node, 'automated:') => 'automated',
                default => 'organic',
            };

            $latestLog = AccessLog::where('client_ip', $row->client_ip)
                ->where('log_source', $row->log_source)
                ->orderByDesc('logged_at')
                ->first(['server_node', 'method', 'uri', 'status_code']);

            Visitor::updateOrCreate(
                [
                    'ip' => $row->client_ip,
                    'log_source' => $row->log_source,
                ],
                [
                    'server_node' => $row->server_node,
                    'country' => $row->country,
                    'region' => $row->region,
                    'city' => $row->city,
                    'traffic_type' => $trafficType,
                    'is_scanner' => $isScanner,
                    'scanner_name' => $isScanner ? trim(str_replace('scanner:', '', (string) $row->server_node)) : null,
                    'method' => $latestLog?->method,
                    'url' => $latestLog?->uri ? Str::limit($latestLog->uri, 500) : null,
                    'status_code' => $latestLog?->status_code,
                    'hit_count' => (int) $row->hit_count,
                    'first_seen' => $row->first_seen,
                    'last_seen' => $row->last_seen,
                ]
            );
        }
    }
}