<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PreparePrivateStorageTest extends TestCase
{
    public function test_it_installs_a_private_readiness_sentinel_on_the_receipt_disk(): void
    {
        Storage::fake('local');
        config()->set('receipt_processing.disk', 'local');

        $command = $this->artisan('keepproof:storage:prepare');
        if (is_int($command)) {
            self::fail('The storage preparation command did not return a pending command.');
        }

        $command->expectsOutputToContain('Private receipt storage "local" is ready.')
            ->assertSuccessful()
            ->run();

        Storage::disk('local')->assertExists('.keepproof/ready');
    }
}
