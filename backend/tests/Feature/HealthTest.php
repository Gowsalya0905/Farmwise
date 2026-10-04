<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_expected_json(): void
    {
        $this->getJson('/api/health')->assertOk()->assertExactJson([
            'status' => 'ok', 'application' => 'Farmwise',
        ]);
    }

    public function test_local_frontend_origin_is_allowed(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:5173'])
            ->getJson('/api/health')->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_untrusted_origin_is_not_allowed(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://untrusted.example'])->getJson('/api/health');
        $response->assertOk();
        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }
}
