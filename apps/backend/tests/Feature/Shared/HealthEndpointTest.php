<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_and_readiness_endpoints_are_available_without_authentication(): void
    {
        $this->get('/health/live')->assertOk();
        $this->getJson('/health/ready')
            ->assertOk()
            ->assertJson([
                'status' => 'ready',
                'checks' => ['database' => 'ok'],
            ])
            ->assertHeader('X-Correlation-ID');
    }
}
