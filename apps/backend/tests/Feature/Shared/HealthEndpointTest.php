<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_and_readiness_endpoints_are_available_without_authentication(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('.keepproof/ready', "ready\n");

        $this->get('/health/live')->assertOk();
        $this->getJson('/health/ready')
            ->assertOk()
            ->assertJson([
                'status' => 'ready',
                'checks' => [
                    'database' => 'ok',
                    'storage' => 'ok',
                    'redis' => 'not_required',
                ],
            ])
            ->assertHeader('X-Correlation-ID');
    }

    public function test_readiness_fails_closed_when_private_storage_is_not_prepared(): void
    {
        Storage::fake('local');

        $this->get('/health/live')->assertOk();
        $this->getJson('/health/ready')
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'unavailable')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.storage', 'failed');
    }
}
