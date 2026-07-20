<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function test_correlation_id_is_preserved_only_when_it_is_a_valid_uuid(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('.keepproof/ready', "ready\n");
        $valid = 'F47AC10B-58CC-4372-A567-0E02B2C3D479';

        $this->withHeader('X-Correlation-ID', $valid)
            ->getJson('/health/ready')
            ->assertOk()
            ->assertHeader('X-Correlation-ID', strtolower($valid));

        $generated = $this->withHeader('X-Correlation-ID', 'not-a-uuid')
            ->getJson('/health/ready')
            ->assertOk()
            ->headers->get('X-Correlation-ID');
        self::assertIsString($generated);
        self::assertTrue(Str::isUuid($generated));
        self::assertNotSame($valid, $generated);
    }
}
