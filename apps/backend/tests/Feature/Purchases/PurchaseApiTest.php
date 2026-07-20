<?php

declare(strict_types=1);

namespace Tests\Feature\Purchases;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Enums\HouseholdRole;
use App\Modules\Households\Models\Household;
use App\Modules\Households\Models\HouseholdMember;
use App\Modules\Purchases\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PurchaseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_purchase_creates_explainable_return_and_warranty_deadlines(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');

        $response = $this->actingAs($owner)->postJson(
            '/api/v1/households/'.$household->id.'/purchases',
            $this->payload(),
        );

        $response->assertCreated()
            ->assertJsonPath('data.merchant', 'North & Pine')
            ->assertJsonPath('data.total_minor', 8950)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.items.0.name', 'Pour-over electric kettle')
            ->assertJsonCount(2, 'data.deadlines')
            ->assertJsonFragment([
                'kind' => 'return',
                'due_on' => '2024-03-01',
                'source' => 'calculated',
            ])
            ->assertJsonFragment([
                'kind' => 'warranty',
                'due_on' => '2025-01-31',
                'source' => 'calculated',
            ]);

        $this->assertDatabaseHas('purchases', [
            'household_id' => $household->id,
            'merchant' => 'North & Pine',
            'total_minor' => 8950,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase.created']);
    }

    public function test_manual_override_keeps_the_calculation_trace_and_becomes_effective(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $payload = $this->payload();
        $payload['manual_return_deadline'] = '2024-03-15';
        $payload['manual_return_reason'] = 'Merchant confirmed a written extension.';

        $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/purchases', $payload)
            ->assertCreated()
            ->assertJsonFragment([
                'kind' => 'return',
                'due_on' => '2024-03-15',
                'source' => 'manual_override',
            ])
            ->assertJsonPath('data.manual_return_reason', 'Merchant confirmed a written extension.');
    }

    public function test_nested_binding_hides_foreign_purchase_and_household_identifiers(): void
    {
        [$owner, $household] = $this->householdWithOwner('Owner');
        [$intruder, $foreignHousehold] = $this->householdWithOwner('Intruder');

        $created = $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/purchases', $this->payload())
            ->assertCreated();
        $purchaseId = $created->json('data.id');
        self::assertIsString($purchaseId);

        $foreign = $this->actingAs($intruder)
            ->getJson('/api/v1/households/'.$household->id.'/purchases/'.$purchaseId);
        $wrongParent = $this->actingAs($intruder)
            ->getJson('/api/v1/households/'.$foreignHousehold->id.'/purchases/'.$purchaseId);
        $missing = $this->actingAs($intruder)
            ->getJson('/api/v1/households/'.$foreignHousehold->id.'/purchases/01J00000000000000000000000');

        $foreign->assertNotFound();
        $wrongParent->assertNotFound();
        $missing->assertNotFound();
        $this->assertSame($missing->json('detail'), $wrongParent->json('detail'));
    }

    public function test_search_filters_with_pagination_without_crossing_households(): void
    {
        [$owner, $household] = $this->householdWithOwner('Owner');
        [, $foreignHousehold] = $this->householdWithOwner('Other');

        Purchase::factory()->create([
            'household_id' => $household->id,
            'created_by_user_id' => $owner->id,
            'merchant' => 'North & Pine',
            'purchase_date' => '2026-07-02',
        ]);
        Purchase::factory()->create([
            'household_id' => $household->id,
            'created_by_user_id' => $owner->id,
            'merchant' => 'Westfield Electronics',
            'purchase_date' => '2026-06-02',
        ]);
        Purchase::factory()->create([
            'household_id' => $foreignHousehold->id,
            'merchant' => 'North & Pine Foreign',
        ]);

        $this->actingAs($owner)
            ->getJson('/api/v1/households/'.$household->id.'/purchases?search=north&date_from=2026-07-01&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.merchant', 'North & Pine')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_money_must_be_submitted_as_integer_minor_units(): void
    {
        [$owner, $household] = $this->householdWithOwner('Owner');
        $payload = $this->payload();
        $payload['total_minor'] = 89.50;

        $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/purchases', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.total_minor.0', 'The total minor field must be an integer.');

        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_unvalidated_ownership_and_state_fields_cannot_be_mass_assigned(): void
    {
        [$owner, $household] = $this->householdWithOwner('Owner');
        [$foreignUser, $foreignHousehold] = $this->householdWithOwner('Foreign');
        $payload = $this->payload();
        $payload['household_id'] = $foreignHousehold->id;
        $payload['created_by_user_id'] = $foreignUser->id;
        $payload['state'] = 'archived';
        $payload['activated_at'] = '2000-01-01T00:00:00Z';

        $response = $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/purchases', $payload)
            ->assertCreated()
            ->assertJsonPath('data.household_id', $household->id)
            ->assertJsonPath('data.state', 'active');
        $purchaseId = $response->json('data.id');
        self::assertIsString($purchaseId);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'household_id' => $household->id,
            'created_by_user_id' => $owner->id,
            'state' => 'active',
        ]);
        $this->assertDatabaseMissing('purchases', [
            'id' => $purchaseId,
            'household_id' => $foreignHousehold->id,
        ]);
    }

    public function test_stored_xss_payload_is_serialized_as_inert_json_data(): void
    {
        [$owner, $household] = $this->householdWithOwner('Owner');
        $payload = $this->payload();
        $payload['notes'] = '<img src=x onerror="alert(1)"><script>alert(2)</script>';

        $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/purchases', $payload)
            ->assertCreated()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('data.notes', $payload['notes']);
    }

    public function test_purchase_payload_exposes_safe_document_metadata_without_storage_keys(): void
    {
        [$owner, $household] = $this->householdWithOwner('Owner');
        $purchase = Purchase::factory()->create([
            'household_id' => $household->id,
            'created_by_user_id' => $owner->id,
        ]);
        $document = $purchase->documents()->create([
            'household_id' => $household->id,
            'uploaded_by_user_id' => $owner->id,
            'state' => 'ready',
            'storage_disk' => 's3',
            'storage_key' => 'opaque/private/key.png',
            'original_filename' => 'receipt.png',
            'mime_type' => 'image/png',
            'size_bytes' => 8809,
            'sha256' => hash('sha256', 'synthetic'),
            'correlation_id' => fake()->uuid(),
            'retain_original' => true,
        ]);

        $this->actingAs($owner)
            ->getJson('/api/v1/households/'.$household->id.'/purchases/'.$purchase->id)
            ->assertOk()
            ->assertJsonPath('data.documents.0.id', $document->id)
            ->assertJsonPath('data.documents.0.filename', 'receipt.png')
            ->assertJsonMissingPath('data.documents.0.storage_key')
            ->assertJsonMissing(['opaque/private/key.png']);
    }

    public function test_delete_cascades_items_and_deadlines_and_records_an_audit_event(): void
    {
        [$owner, $household] = $this->householdWithOwner('Owner');
        $created = $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/purchases', $this->payload())
            ->assertCreated();
        $purchaseId = $created->json('data.id');
        self::assertIsString($purchaseId);

        $this->actingAs($owner)
            ->deleteJson('/api/v1/households/'.$household->id.'/purchases/'.$purchaseId)
            ->assertNoContent();

        $this->assertDatabaseMissing('purchases', ['id' => $purchaseId]);
        $this->assertDatabaseMissing('purchase_items', ['purchase_id' => $purchaseId]);
        $this->assertDatabaseMissing('purchase_deadlines', ['purchase_id' => $purchaseId]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'purchase.deleted',
            'subject_id' => $purchaseId,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'merchant' => 'North & Pine',
            'purchase_date' => '2024-01-31',
            'timezone' => 'America/New_York',
            'receipt_number' => 'NP-10042',
            'currency' => 'usd',
            'total_minor' => 8950,
            'country_region' => 'US',
            'return_period_quantity' => 30,
            'return_period_unit' => 'days',
            'return_counting_convention' => 'purchase_day_exclusive',
            'warranty_period_quantity' => 12,
            'warranty_period_unit' => 'months',
            'warranty_counting_convention' => 'purchase_day_exclusive',
            'notes' => 'Gift receipt is in the package.',
            'items' => [[
                'name' => 'Pour-over electric kettle',
                'quantity' => 1,
                'unit_price_minor' => 8950,
                'serial_number' => null,
            ]],
        ];
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
