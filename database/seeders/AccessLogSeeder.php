<?php

namespace Database\Seeders;

use App\Models\AccessLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AccessLogSeeder extends Seeder
{
    public function run(): void
    {
        $clients = [
            ['ip' => '185.220.101.44', 'geo' => 'DE • Tor Exit / Scanner'],
            ['ip' => '103.147.22.19', 'geo' => 'ID • Telkom Indonesia'],
            ['ip' => '45.155.205.92', 'geo' => 'RU • Bulletproof ASN'],
            ['ip' => '114.124.188.5', 'geo' => 'ID • Telkomsel Mobile'],
            ['ip' => '54.251.118.204', 'geo' => 'SG • AWS ap-southeast-1'],
            ['ip' => '172.68.144.81', 'geo' => 'US • Cloudflare Edge'],
            ['ip' => '36.72.114.90', 'geo' => 'ID • Indosat Ooredoo'],
            ['ip' => '180.244.132.77', 'geo' => 'ID • Telkom Fiber'],
            ['ip' => '118.99.84.12', 'geo' => 'ID • Biznet Fiber'],
        ];

        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148',
            'okhttp/4.12.0 (Android 14; Tokopedia-Partner-SDK)',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Firefox/132.0',
            'python-requests/2.32.3 (Security-Scanner-Bot)',
            'curl/8.7.1',
        ];

        $scenarios = [
            ['method' => 'GET', 'uri' => '/api/v1/products?category=elektronik&page=1', 'status' => 200, 'bytes' => 18420, 'rt' => 0.032],
            ['method' => 'POST', 'uri' => '/api/v1/auth/login', 'status' => 200, 'bytes' => 1240, 'rt' => 0.084],
            ['method' => 'GET', 'uri' => '/assets/app.bundle.js', 'status' => 304, 'bytes' => 0, 'rt' => 0.004],
            ['method' => 'POST', 'uri' => '/api/v1/orders/checkout', 'status' => 201, 'bytes' => 2890, 'rt' => 0.142],
            ['method' => 'GET', 'uri' => '/api/v1/users/profile', 'status' => 200, 'bytes' => 4310, 'rt' => 0.027],
            ['method' => 'GET', 'uri' => '/api/v1/products/item-99482', 'status' => 404, 'bytes' => 512, 'rt' => 0.015],
            ['method' => 'POST', 'uri' => '/wp-login.php', 'status' => 403, 'bytes' => 162, 'rt' => 0.002],
            ['method' => 'GET', 'uri' => '/api/v1/search?q=promo', 'status' => 429, 'bytes' => 320, 'rt' => 0.006],
            ['method' => 'GET', 'uri' => '/api/v1/products?sort=popular', 'status' => 200, 'bytes' => 22100, 'rt' => 0.045],
            ['method' => 'POST', 'uri' => '/api/v1/payments/webhook', 'status' => 200, 'bytes' => 488, 'rt' => 0.064],
            ['method' => 'GET', 'uri' => '/.env.production', 'status' => 403, 'bytes' => 162, 'rt' => 0.003],
            ['method' => 'POST', 'uri' => '/api/v1/orders/checkout', 'status' => 502, 'bytes' => 552, 'rt' => 1.482],
            ['method' => 'GET', 'uri' => '/api/v1/products?sort=popular', 'status' => 504, 'bytes' => 564, 'rt' => 5.002],
        ];

        $upstreams = ['10.0.4.21:8080', '10.0.4.22:8080', '10.0.5.14:3000'];
        $nodes = ['prod-web-01', 'prod-web-02', 'api-gateway-01'];
        $referers = [
            'https://app.tokokita.id/katalog',
            'https://app.tokokita.id/checkout',
            'https://www.google.com/',
            '-',
            'https://admin.tokokita.id/dashboard',
        ];

        $rows = [];
        $now = now();

        // Generate ~500 log entries spread over the last 24 hours
        for ($i = 0; $i < 500; $i++) {
            $scenario = $scenarios[array_rand($scenarios)];
            $client = $clients[array_rand($clients)];
            $ua = $userAgents[array_rand($userAgents)];
            $node = $nodes[array_rand($nodes)];
            $upstream = $upstreams[array_rand($upstreams)];
            $referer = $referers[array_rand($referers)];

            $loggedAt = $now->copy()->subSeconds(rand(0, 86400));
            $rt = round($scenario['rt'] * (0.8 + (mt_rand() / mt_getrandmax()) * 0.45), 3);
            $urt = $scenario['status'] >= 300 && $scenario['status'] < 400 ? null : round($rt * 0.91, 3);
            $bytes = max(0, (int) round($scenario['bytes'] * (0.85 + (mt_rand() / mt_getrandmax()) * 0.3)));
            $reqId = 'req-' . Str::random(8);

            $rawLine = sprintf(
                '%s - - [%s] "%s %s HTTP/1.1" %d %d "%s" "%s" rt=%.3f urt="%s" uaddr="%s" node="%s" req_id="%s"',
                $client['ip'],
                $loggedAt->format('d/M/Y:H:i:s O'),
                $scenario['method'],
                $scenario['uri'],
                $scenario['status'],
                $bytes,
                $referer,
                $ua,
                $rt,
                $urt !== null ? number_format($urt, 3) : '-',
                $urt !== null ? $upstream : '-',
                $node,
                $reqId,
            );

            $rows[] = [
                'request_id' => $reqId,
                'client_ip' => $client['ip'],
                'method' => $scenario['method'],
                'uri' => $scenario['uri'],
                'protocol' => 'HTTP/1.1',
                'status_code' => $scenario['status'],
                'body_bytes' => $bytes,
                'request_time' => $rt,
                'upstream_response_time' => $urt,
                'upstream_addr' => $urt !== null ? $upstream : null,
                'server_node' => $node,
                'referer' => $referer,
                'user_agent' => $ua,
                'geo_label' => $client['geo'],
                'is_blocked' => false,
                'raw_line' => $rawLine,
                'logged_at' => $loggedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Bulk insert in chunks
        foreach (array_chunk($rows, 100) as $chunk) {
            AccessLog::insert($chunk);
        }
    }
}
