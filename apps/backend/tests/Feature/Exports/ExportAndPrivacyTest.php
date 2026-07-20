<?php

declare(strict_types=1);

namespace Tests\Feature\Exports;

use App\Modules\Accounts\Actions\ExportAccountData;
use App\Modules\Accounts\Models\User;
use App\Modules\Exports\Actions\ExportClaimPackage;
use App\Modules\Households\Enums\HouseholdRole;
use App\Modules\Households\Models\Household;
use App\Modules\Households\Models\HouseholdMember;
use App\Modules\Purchases\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

final class ExportAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('receipts');
        Queue::fake();
        config()->set('receipt_processing.disk', 'receipts');
    }

    public function test_ics_export_is_tenant_scoped_and_contains_a_real_calendar_event(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        [$intruder] = $this->householdWithOwner('Intruder');
        $purchase = $this->createPurchase($owner, $household);
        $deadline = $purchase->deadlines()->where('kind', 'return')->firstOrFail();
        $url = '/api/v1/households/'.$household->id.'/purchases/'.$purchase->id
            .'/deadlines/'.$deadline->id.'/calendar';

        $response = $this->actingAs($owner)->get($url);
        $response->assertOk()
            ->assertHeader('content-type', 'text/calendar; charset=utf-8')
            ->assertHeader('cache-control', 'no-store, private');
        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringContainsString("BEGIN:VCALENDAR\r\n", $content);
        self::assertStringContainsString('UID:'.$deadline->id.'@keepproof.local', $content);
        self::assertStringContainsString('SUMMARY:Return window — North & Pine', $content);
        self::assertStringContainsString('does not provide legal advice', $content);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deadline.calendar_exported']);

        $this->actingAs($intruder)->get($url)->assertNotFound();
    }

    public function test_claim_package_contains_structured_data_and_the_private_original(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $purchase = $this->createPurchase($owner, $household);
        $upload = $this->actingAs($owner)
            ->withHeader('Idempotency-Key', 'claim-upload-0001')
            ->post('/api/v1/households/'.$household->id.'/receipts', [
                'purchase_id' => $purchase->id,
                'receipt' => UploadedFile::fake()->image('proof.png', 80, 80),
            ])
            ->assertAccepted();
        $documentId = $upload->json('data.id');
        self::assertIsString($documentId);

        $path = app(ExportClaimPackage::class)->execute($purchase->refresh());
        try {
            $zip = new ZipArchive;
            self::assertTrue($zip->open($path) === true);
            self::assertNotFalse($zip->locateName('purchase.json'));
            self::assertNotFalse($zip->locateName('extraction-fields.json'));
            self::assertNotFalse($zip->locateName('README.txt'));
            self::assertNotFalse($zip->locateName('receipts/'.$documentId.'.png'));
            $purchaseJson = $zip->getFromName('purchase.json');
            self::assertIsString($purchaseJson);
            self::assertStringContainsString('"total_minor": 8950', $purchaseJson);
            self::assertStringContainsString('North & Pine', $purchaseJson);
            $zip->close();
        } finally {
            @unlink($path);
        }

        $this->actingAs($owner)
            ->get('/api/v1/households/'.$household->id.'/purchases/'.$purchase->id.'/claim-package')
            ->assertOk()
            ->assertHeader('content-disposition');
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase.claim_package_exported']);
    }

    public function test_account_export_requires_recent_reauthentication_and_includes_portable_json(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $this->createPurchase($owner, $household);

        $this->actingAs($owner)->getJson('/api/v1/account/export')
            ->assertForbidden()
            ->assertJsonPath('code', 'password_confirmation_required');

        $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/api/v1/account/export')
            ->assertOk()
            ->assertHeader('content-disposition');
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.data_exported']);

        $path = app(ExportAccountData::class)->execute($owner->refresh());
        try {
            $zip = new ZipArchive;
            self::assertTrue($zip->open($path) === true);
            $json = $zip->getFromName('account.json');
            self::assertIsString($json);
            json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            self::assertStringContainsString('"name": "Alex"', $json);
            self::assertStringContainsString('"total_minor": 8950', $json);
            $zip->close();
        } finally {
            @unlink($path);
        }
    }

    public function test_account_deletion_removes_owned_database_rows_and_private_objects(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $upload = $this->actingAs($owner)
            ->withHeader('Idempotency-Key', 'delete-upload-001')
            ->post('/api/v1/households/'.$household->id.'/receipts', [
                'receipt' => UploadedFile::fake()->image('delete-me.jpg', 60, 60),
            ])
            ->assertAccepted();
        $documentId = $upload->json('data.id');
        self::assertIsString($documentId);
        $storageKey = $household->documents()->whereKey($documentId)->value('storage_key');
        self::assertIsString($storageKey);
        Storage::disk('receipts')->assertExists($storageKey);

        $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->deleteJson('/api/v1/account')
            ->assertNoContent();

        Storage::disk('receipts')->assertMissing($storageKey);
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('households', ['id' => $household->id]);
        $this->assertDatabaseMissing('receipt_documents', ['id' => $documentId]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.deleted',
            'subject_id' => $owner->id,
            'actor_user_id' => null,
        ]);
    }

    private function createPurchase(User $owner, Household $household): Purchase
    {
        $response = $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/purchases', [
                'merchant' => 'North & Pine',
                'purchase_date' => '2026-07-20',
                'timezone' => 'America/New_York',
                'receipt_number' => 'NP-EXPORT',
                'currency' => 'USD',
                'total_minor' => 8950,
                'country_region' => 'US',
                'return_period_quantity' => 30,
                'return_period_unit' => 'days',
                'warranty_period_quantity' => 12,
                'warranty_period_unit' => 'months',
                'items' => [[
                    'name' => 'Pour-over electric kettle',
                    'quantity' => 1,
                    'unit_price_minor' => 8950,
                ]],
            ])
            ->assertCreated();
        $purchaseId = $response->json('data.id');
        self::assertIsString($purchaseId);

        return Purchase::query()->findOrFail($purchaseId);
    }

    /** @return array{User, Household} */
    private function householdWithOwner(string $name): array
    {
        $owner = User::factory()->create(['name' => $name, 'timezone' => 'America/New_York']);
        $household = Household::factory()->create([
            'name' => $name.' household',
            'owner_user_id' => $owner->id,
            'is_personal' => true,
        ]);
        HouseholdMember::query()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'role' => HouseholdRole::Owner,
            'joined_at' => now(),
        ]);

        return [$owner, $household];
    }
}
