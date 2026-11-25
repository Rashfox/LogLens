<?php

namespace App\Services;

use App\Models\AccessLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NginxLogParserService
{
    protected string $logFile;
    protected string $logLogs;
    protected string $stateFile;

    public function __construct()
    {
        $this->logFile = env('NGINX_ACCESS_LOG', '/var/log/nginx/access.log');
        $this->logLogs = env('LOG_SELF_PATH', '/var/log/nginx/log.access.log');
        $this->stateFile = sys_get_temp_dir() . '/nginx-log-position.json';
    }

    /**
     * Get list of access log files to monitor.
     */
    public function getLogFiles(): array
    {
        $files = [$this->logFile];

        if ($this->logLogs && file_exists($this->logLogs)) {
            $files[] = $this->logLogs;
        }

        return array_values(array_unique($files));
    }

    /**
     * Parse and import all lines from all log files.
     */
    public function importAll(bool $truncate = true): int
    {
        if ($truncate) {
            AccessLog::truncate();
            if (is_file($this->stateFile)) {
                @unlink($this->stateFile);
            }
        }

        $totalProcessed = 0;
        foreach ($this->getLogFiles() as $file) {
            $totalProcessed += $this->parseFile($file, true);
        }

        return $totalProcessed;
    }

    /**
     * Incrementally sync new lines from the files since the last position.
     * Returns an array of newly parsed AccessLog models.
     */
    public function syncNewLines(): array
    {
        $newRecords = [];
        $lock = Cache::lock('nginx-live-sync-lock', 5);
        if (!$lock->get()) {
            return [];
        }

        try {
            foreach ($this->getLogFiles() as $file) {
                $this->parseFile($file, false, function ($record) use (&$newRecords) {
                    $newRecords[] = $record;
                });
            }
        } finally {
            $lock->release();
        }

        return $newRecords;
    }

    /**
     * Core parser loop for a specific log file.
     */
    public function parseFile(?string $file = null, bool $fromBeginning = false, ?callable $onNewRecord = null): int
    {
        $file = $file ?: $this->logFile;

        if (!file_exists($file)) {
            return 0;
        }

        $isSelfLog = ($file === $this->logLogs) || str_contains(basename($file), 'log.access.log');
        $logSource = $isSelfLog ? 'self_log' : 'access_log';

        clearstatcache(true, $file);
        $logSize = filesize($file);
        if ($logSize === 0) {
            return 0;
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return 0;
        }

        $firstLine = fgets($handle);
        $fileSignature = $firstLine !== false ? md5($firstLine) : null;
        rewind($handle);

        $stateKey = md5($file);
        $stateAll = is_file($this->stateFile) ? json_decode(@file_get_contents($this->stateFile), true) : [];
        if (!is_array($stateAll)) {
            $stateAll = [];
        }

        $position = 0;
        if (!$fromBeginning && isset($stateAll[$stateKey])) {
            $state = $stateAll[$stateKey];
            if (
                is_array($state)
                && $fileSignature !== null
                && ($state['signature'] ?? null) === $fileSignature
                && ($state['position'] ?? 0) <= $logSize
            ) {
                $position = (int) $state['position'];
            }
        }

        fseek($handle, $position);

        $batch = [];
        $processed = 0;

        // Regex untuk format combined standar maupun custom_vhost ($host di depan)
        $pattern = '/^(?:(\S+)\s+)?(\S+)\s+\S+\s+\S+\s+\[([^\]]+)\]\s+"([^"]*)"\s+(\d+)\s+(\d+)\s+"([^"]*)"\s+"([^"]*)"(.*)$/';

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (!preg_match($pattern, $line, $matches)) {
                continue;
            }

            $vhostToken = $matches[1] && !filter_var($matches[1], FILTER_VALIDATE_IP) ? $matches[1] : null;
            $ip = $vhostToken ? $matches[2] : ($matches[1] ?: $matches[2]);
            $rawTimestamp = $matches[3];
            $requestLine = $matches[4];
            $statusCode = (int) $matches[5];
            $bodyBytes = (int) $matches[6];
            $referer = $matches[7] === '-' ? null : $matches[7];
            $userAgent = $matches[8] === '-' ? null : $matches[8];
            $extra = $matches[9] ?? '';

            $reqParts = explode(' ', $requestLine, 3);
            $method = $reqParts[0] ?? 'GET';
            $uri = $reqParts[1] ?? '/';
            $protocol = $reqParts[2] ?? 'HTTP/1.1';

            $loggedAt = $this->parseTimestamp($rawTimestamp) ?? now();

            $requestTime = 0.025;
            if (preg_match('/rt=([0-9.]+)/', $extra, $rtMatches)) {
                $requestTime = (float) $rtMatches[1];
            }

            $node = $this->resolveServerNode($vhostToken, $referer, $uri, $userAgent);
            if ($isSelfLog && $node === 'direct-access') {
                $node = 'log.rashfox.web.id';
            }

            $loc = $this->resolveGeoLocation($ip);
            $geo = "{$loc['country']} • {$loc['region']}";
            $reqId = 'req-' . Str::random(8);

            $data = [
                'request_id' => $reqId,
                'log_source' => $logSource,
                'client_ip' => $ip,
                'method' => $method,
                'uri' => $uri,
                'protocol' => $protocol,
                'status_code' => $statusCode,
                'body_bytes' => $bodyBytes,
                'request_time' => $requestTime,
                'upstream_response_time' => $requestTime > 0.05 ? round($requestTime * 0.9, 3) : null,
                'upstream_addr' => null,
                'server_node' => $node,
                'referer' => $referer,
                'user_agent' => $userAgent,
                'geo_label' => $geo,
                'country' => $loc['country'],
                'region' => $loc['region'],
                'city' => $loc['city'],
                'is_blocked' => false,
                'raw_line' => $line,
                'logged_at' => $loggedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($onNewRecord !== null) {
                $created = AccessLog::create($data);
                $onNewRecord($created);
            } else {
                $batch[] = $data;
                if (count($batch) >= 500) {
                    AccessLog::insert($batch);
                    $batch = [];
                }
            }

            $processed++;
        }

        if (!empty($batch)) {
            AccessLog::insert($batch);
        }

        $newPosition = (int) ftell($handle);
        fclose($handle);

        $stateAll[$stateKey] = [
            'signature' => $fileSignature,
            'position' => $newPosition,
        ];

        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($this->stateFile, json_encode($stateAll));

        return $processed;
    }

    private function parseTimestamp(string $ts): ?Carbon
    {
        try {
            return Carbon::createFromFormat('d/M/Y:H:i:s O', $ts);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolves server_node directly from the log's $host token, with fallback for legacy lines.
     */
    private function resolveServerNode(?string $vhostToken, ?string $referer, string $uri, ?string $userAgent): string
    {
        // 1. Langsung gunakan $host dari log jika valid (semua subdomain otomatis tersimpan)
        if ($vhostToken) {
            $host = strtolower(trim($vhostToken));
            $host = preg_replace('/:\d+$/', '', $host);

            if ($host !== '' && $host !== '_' && !filter_var($host, FILTER_VALIDATE_IP)) {
                return $host;
            }
        }

        // 2. Fallback jika baris log lama tidak memiliki kolom $host
        $scanner = $this->detectScanner($userAgent);
        if ($scanner) {
            return "scanner: {$scanner}";
        }

        if ($this->isProbingPath($uri)) {
            return 'scanner: path-probe';
        }

        if ($userAgent) {
            if (stripos($userAgent, 'curl') !== false) return 'automated: curl';
            if (stripos($userAgent, 'python') !== false) return 'automated: python';
            if (stripos($userAgent, 'wget') !== false) return 'automated: wget';
            if (stripos($userAgent, 'bot') !== false || stripos($userAgent, 'spider') !== false || stripos($userAgent, 'crawl') !== false) {
                return 'crawler / bot';
            }
        }

        if ($referer && $referer !== '-') {
            $refHost = parse_url($referer, PHP_URL_HOST);
            if ($refHost && !filter_var($refHost, FILTER_VALIDATE_IP)) {
                return strtolower($refHost);
            }
        }

        return 'direct-access';
    }

    private function detectScanner(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        $known = [
            'gobuster' => 'gobuster',
            'dirbuster' => 'dirbuster',
            'ffuf' => 'ffuf',
            'sqlmap' => 'sqlmap',
            'nikto' => 'nikto',
            'nmap' => 'nmap',
            'masscan' => 'masscan',
            'wpscan' => 'wpscan',
            'nuclei' => 'nuclei',
            'acunetix' => 'acunetix',
            'nessus' => 'nessus',
            'zgrab' => 'zgrab',
            'censys' => 'censys',
            'shodan' => 'shodan',
        ];

        foreach ($known as $needle => $name) {
            if (stripos($userAgent, $needle) !== false) {
                return $name;
            }
        }

        return null;
    }

    private function isProbingPath(string $uri): bool
    {
        $needles = [
            '.env', 'wp-login', 'wp-admin', 'xmlrpc', 'phpmyadmin', 'pma',
            '.git', 'setup.php', 'actuator', 'swagger', 'config.php', 'shell',
            'eval-stdin', 'solr', 'vendor', 'composer.json', '.aws',
        ];

        foreach ($needles as $n) {
            if (stripos($uri, $n) !== false) {
                return true;
            }
        }

        return false;
    }

    public function resolveGeoLocation(string $ip): array
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return ['country' => 'Localhost', 'region' => 'Internal Proxy', 'city' => 'Localhost'];
        }
        if (str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.') || str_starts_with($ip, '172.')) {
            return ['country' => 'Private Network', 'region' => 'LAN / Docker', 'city' => 'LAN'];
        }

        $cacheKey = "geoip_{$ip}";
        return Cache::remember($cacheKey, 86400 * 30, function () use ($ip) {
            $ctx = stream_context_create(['http' => ['timeout' => 1.5]]);
            $raw = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,regionName,city", false, $ctx);
            if ($raw) {
                $data = json_decode($raw, true);
                if (($data['status'] ?? '') === 'success') {
                    return [
                        'country' => $data['country'] ?: 'Unknown',
                        'region' => $data['regionName'] ?: 'Unknown',
                        'city' => $data['city'] ?: 'Unknown',
                    ];
                }
            }

            return ['country' => 'Public IP', 'region' => 'Unknown Region', 'city' => 'Unknown'];
        });
    }
}