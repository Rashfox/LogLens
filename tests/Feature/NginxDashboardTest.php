<?php

namespace Tests\Feature;

use Tests\TestCase;

class NginxDashboardTest extends TestCase
{
    public function test_dashboard_page_loads_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Nginx Access Log Monitor');
    }

    public function test_kpis_endpoint_returns_json(): void
    {
        $response = $this->getJson('/api/dashboard/kpis');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'totalRequests',
            'rps',
            'peakRps',
            'bandwidth',
            'err4xx',
            'err5xx',
            'errTotal',
            'avgLatency',
            'p95Latency',
            'avgUpstreamLatency',
        ]);
    }

    public function test_top_endpoints_endpoint_returns_json(): void
    {
        $response = $this->getJson('/api/dashboard/top-endpoints');
        $response->assertStatus(200);
    }

    public function test_top_clients_endpoint_returns_json(): void
    {
        $response = $this->getJson('/api/dashboard/top-clients');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'blockedCount',
            'clients',
        ]);
    }

    public function test_toggle_block_ip(): void
    {
        $response = $this->postJson('/api/dashboard/toggle-block-ip', [
            'ip' => '1.2.3.4',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'blocked' => true,
        ]);

        $this->assertDatabaseHas('blocked_ips', ['ip' => '1.2.3.4']);

        // Toggle back (unblock)
        $response2 = $this->postJson('/api/dashboard/toggle-block-ip', [
            'ip' => '1.2.3.4',
        ]);

        $response2->assertStatus(200);
        $response2->assertJson([
            'blocked' => false,
        ]);

        $this->assertDatabaseMissing('blocked_ips', ['ip' => '1.2.3.4']);
    }

    public function test_kpis_includes_visitor_metrics(): void
    {
        $response = $this->getJson('/api/dashboard/kpis');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'totalVisitors',
            'avgVisitorsPerHour',
            'avgVisitorsPerDay',
            'avgVisitorsPerMonth',
        ]);
    }

    public function test_geo_distribution_endpoint_returns_json(): void
    {
        $response = $this->getJson('/api/dashboard/geo-distribution');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'countries',
            'regions',
        ]);
    }
}
