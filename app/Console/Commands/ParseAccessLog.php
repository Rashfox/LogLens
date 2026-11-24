<?php

namespace App\Console\Commands;

use App\Models\Visitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

#[Signature('app:parse-access-log {--reset : Rebuild visitor counts from the access log from the beginning}')]
#[Description('Command description')]
class ParseAccessLog extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lock = Cache::lock('nginx-access-log-parser', 30);
        if (! $lock->get()) {
            $this->warn('Parser sedang berjalan, proses dilewati.');
            return self::SUCCESS;
        }

        try {
            return $this->processLog();
        } finally {
            $lock->release();
        }
    }

    private function processLog(): int
    {
        $logFile = env(
            'NGINX_ACCESS_LOG',
            '/var/log/nginx/access.log'
        );

        if (!file_exists($logFile)) {

            $this->error(
                "Log file tidak ditemukan: {$logFile}"
            );

            return self::FAILURE;
        }

        $stateFile = storage_path('framework/cache/nginx-log-position.json');
        if ($this->option('reset')) {
            Visitor::query()->update([
                'hit_count' => 0,
                'is_scanner' => false,
                'scanner_name' => null,
                'traffic_type' => 'organic',
                'bot_name' => null,
            ]);
            if (is_file($stateFile)) {
                unlink($stateFile);
            }
        }
        $position = 0;
        clearstatcache(true, $logFile);
        $logSize = filesize($logFile);

        $handle = fopen($logFile, 'rb');
        if ($handle === false) {
            $this->error("Log file tidak dapat dibaca: {$logFile}");
            return self::FAILURE;
        }

        $firstLine = fgets($handle);
        $fileSignature = $firstLine !== false ? md5($firstLine) : null;
        rewind($handle);

        $state = is_file($stateFile)
            ? json_decode(file_get_contents($stateFile), true)
            : null;

        if (
            is_array($state)
            && $fileSignature !== null
            && ($state['signature'] ?? null) === $fileSignature
            && ($state['position'] ?? 0) <= $logSize
        ) {
            $position = (int) $state['position'];
        }

        fseek($handle, $position);
        $processed = 0;
        while (($line = fgets($handle)) !== false) {

            preg_match(
                '/^(.*?) .*? \[(.*?)\] "(.*?) (.*?) HTTP\/.*?" (\d+) (\d+) "(.*?)" "(.*?)"$/',
                $line,
                $matches
            );

            if (empty($matches)) {
                continue;
            }

            $ip = $matches[1];
            $timestamp = trim($matches[2]);
            $method = trim($matches[3]);
            $url = trim($matches[4]);
            $statusCode = (int) $matches[5];
            $referer = $matches[7];
            $userAgent = $matches[8];
            $scanner = $this->detectScanner($userAgent);
            $trafficType = $this->detectTrafficType($userAgent, $scanner);
            $botName = $this->detectBotName($userAgent);

            $seenAt = $this->parseTimestamp($timestamp) ?? now();
            $visitor = Visitor::where('ip', $ip)->first();

            if ($visitor) {
                $visitor->increment('hit_count');
                $visitor->update([
                    'last_seen' => $seenAt,
                    'referer' => $referer,
                    'user_agent' => $userAgent,
                    'url' => $url,
                    'method' => $method,
                    'status_code' => $statusCode,
                    'is_scanner' => $visitor->is_scanner || $scanner,
                    'scanner_name' => $scanner ?: $visitor->scanner_name,
                    'traffic_type' => $trafficType,
                    'bot_name' => $botName ?: $visitor->bot_name,
                ]);

            } else {
                Visitor::create([
                    'ip' => $ip,
                    'referer' => $referer,
                    'user_agent' => $userAgent,
                    'url' => $url,
                    'method' => $method,
                    'status_code' => $statusCode,
                    'is_scanner' => (bool) $scanner,
                    'scanner_name' => $scanner,
                    'traffic_type' => $trafficType,
                    'bot_name' => $botName,
                    'hit_count' => 1,
                    'first_seen' => $seenAt,
                    'last_seen' => $seenAt,
                ]);
            }

            $processed++;
        }

        $position = (int) ftell($handle);
        fclose($handle);

        $directory = dirname($stateFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($stateFile, json_encode([
            'signature' => $fileSignature,
            'position' => $position,
        ]));

        $this->info("Access log diproses: {$processed} request baru.");

        return self::SUCCESS;
    }

    private function detectScanner(string $userAgent): ?string
    {
        $known = [
            'gobuster' => 'Gobuster',
            'sqlmap' => 'SQLMap',
            'nikto' => 'Nikto',
            'nmap' => 'Nmap',
            'masscan' => 'Masscan',
            'wpscan' => 'WPScan',
            'acunetix' => 'Acunetix',
            'nessus' => 'Nessus',
        ];

        foreach ($known as $needle => $name) {
            if (stripos($userAgent, $needle) !== false) {
                return $name;
            }
        }

        return null;
    }

    private function parseTimestamp(string $timestamp): ?Carbon
    {
        $parsed = Carbon::createFromFormat('d/M/Y:H:i:s O', $timestamp);
        return $parsed ?: null;
    }

    private function detectTrafficType(string $userAgent, ?string $scanner): string
    {
        if ($scanner) {
            return 'scanner';
        }

        $crawlerPatterns = [
            'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
            'yandexbot', 'facebookexternalhit', 'twitterbot', 'linkedinbot',
            'crawler', 'spider', 'bot',
        ];

        foreach ($crawlerPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return 'crawler';
            }
        }

        if (stripos($userAgent, 'curl') !== false
            || stripos($userAgent, 'wget') !== false
            || stripos($userAgent, 'python') !== false
            || stripos($userAgent, 'go-http-client') !== false
            || stripos($userAgent, 'postman') !== false) {
            return 'automated';
        }

        return 'organic';
    }

    private function detectBotName(string $userAgent): ?string
    {
        $bots = [
            'Googlebot' => 'googlebot',
            'Bingbot' => 'bingbot',
            'DuckDuckBot' => 'duckduckbot',
            'Baiduspider' => 'baiduspider',
            'YandexBot' => 'yandexbot',
            'Facebook External Hit' => 'facebookexternalhit',
            'Twitterbot' => 'twitterbot',
            'LinkedInBot' => 'linkedinbot',
            'Slurp' => 'slurp',
        ];

        foreach ($bots as $name => $needle) {
            if (stripos($userAgent, $needle) !== false) {
                return $name;
            }
        }

        return null;
    }
}
