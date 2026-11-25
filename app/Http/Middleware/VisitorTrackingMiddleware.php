<?php

namespace App\Http\Middleware;

use App\Services\NginxLogParserService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VisitorTrackingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
    public function terminate(Request $request, Response $response): void
    {
        // Hindari loop saat endpoint live polling dipanggil
        if ($request->is('api/dashboard/*')) {
            return;
        }

        if (Cache::add('nginx_auto_sync_throttle', true, 10)) {
            app(NginxLogParserService::class)->syncNewLines();
        }
    }
}
