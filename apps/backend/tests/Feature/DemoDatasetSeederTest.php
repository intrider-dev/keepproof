<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Documents\Enums\DocumentState;
use App\Modules\Documents\Models\ReceiptDocument;
use App\Modules\Notifications\Models\Reminder;
use App\Modules\Purchases\Enums\PurchaseState;
use App\Modules\Purchases\Models\Purchase;
use Database\Seeders\DemoDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DemoDatasetSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_dataset_is_complete_synthetic_and_repeatable(): void
    {
        Storage::fake('local');
        config()->set('receipt_processing.disk', 'local');

        $this->seed(DemoDatasetSeeder::class);

        $demo = User::query()->where('email', DemoDatasetSeeder::EMAIL)->firstOrFail();
        self::assertTrue($demo->hasVerifiedEmail());
        self::assertTrue(password_verify(DemoDatasetSeeder::PASSWORD, $demo->password));
        $this->assertDatabaseHas('households', ['owner_user_id' => $demo->id, 'name' => 'Morgan household']);
        $this->assertDatabaseHas('users', ['email' => 'jamie@keepproof.local']);
        self::assertSame(4, Purchase::query()->whereHas('household', fn ($query) => $query->where('owner_user_id', $demo->id))->count());
        self::assertSame(3, Purchase::query()->where('state', PurchaseState::Active)->count());
        self::assertSame(1, Purchase::query()->where('state', PurchaseState::NeedsReview)->count());
        self::assertSame(1, ReceiptDocument::query()->where('state', DocumentState::Ready)->count());
        self::assertSame(1, ReceiptDocument::query()->where('state', DocumentState::NeedsReview)->count());
        self::assertGreaterThan(0, Reminder::query()->where('user_id', $demo->id)->count());
        self::assertCount(1, $demo->notifications);

        foreach (ReceiptDocument::query()->get() as $document) {
            Storage::disk($document->storage_disk)->assertExists($document->storage_key);
            self::assertStringNotContainsString($document->original_filename, $document->storage_key);
            self::assertGreaterThanOrEqual(4, $document->transitions()->count());
        }

        $this->seed(DemoDatasetSeeder::class);

        self::assertSame(1, User::query()->where('email', DemoDatasetSeeder::EMAIL)->count());
        self::assertSame(4, Purchase::query()->whereHas('household', fn ($query) => $query->whereHas('owner', fn ($owner) => $owner->where('email', DemoDatasetSeeder::EMAIL)))->count());
        self::assertSame(2, ReceiptDocument::query()->count());
    }
}
