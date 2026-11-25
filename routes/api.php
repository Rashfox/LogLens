<?php

use App\Http\Controllers\NginxDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/dashboard')->group(function () {
    Route::get('kpis', [NginxDashboardController::class, 'kpis']);
    Route::get('timeline', [NginxDashboardController::class, 'timeline']);
    Route::get('status-breakdown', [NginxDashboardController::class, 'statusBreakdown']);
    Route::get('top-endpoints', [NginxDashboardController::class, 'topEndpoints']);
    Route::get('top-clients', [NginxDashboardController::class, 'topClients']);
    Route::get('logs', [NginxDashboardController::class, 'logs']);
    Route::post('toggle-block-ip', [NginxDashboardController::class, 'toggleBlockIp']);
    Route::post('simulate-5xx', [NginxDashboardController::class, 'simulate5xx']);
    Route::post('generate-live', [NginxDashboardController::class, 'generateLive']);
});
