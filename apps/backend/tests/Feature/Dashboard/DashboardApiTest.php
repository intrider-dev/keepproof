<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Enums\HouseholdRole;
use App\Modules\Households\Models\Household;
use App\Modules\Households\Models\HouseholdMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-20 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_returns_only_the_selected_households_eager_loaded_actions(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        [$other, $foreignHousehold] = $this->householdWithOwner('Other');
        $this->createPurchase($owner, $household, 'North & Pine');
        $this->createPurchase($other, $foreignHousehold, 'Foreign Merchant');

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/households/'.$household->id.'/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.counts.upcoming_returns', 1)
            ->assertJsonPath('data.counts.active_warranties', 1)
            ->assertJsonPath('data.counts.needs_review', 0)
            ->assertJsonPath('data.upcoming_deadlines.0.purchase.merchant', 'North & Pine')
            ->assertJsonMissing(['merchant' => 'Foreign Merchant'])
            ->assertJsonCount(2, 'data.upcoming_deadlines');

        $this->actingAs($other)
            ->getJson('/api/v1/households/'.$household->id.'/dashboard')
            ->assertNotFound();
    }

    private function createPurchase(User $owner, Household $household, string $merchant): void
    {
        $this->actingAs($owner)->postJson('/api/v1/households/'.$household->id.'/purchases', [
            'merchant' => $merchant,
            'purchase_date' => '2026-07-20',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'total_minor' => 10000,
            'country_region' => 'US',
            'return_period_quantity' => 30,
            'return_period_unit' => 'days',
            'warranty_period_quantity' => 12,
            'warranty_period_unit' => 'months',
            'items' => [[
                'name' => 'Synthetic item',
                'quantity' => 1,
                'unit_price_minor' => 10000,
            ]],
        ])->assertCreated();
    }

    /** @return array{User, Household} */
    private function householdWithOwner(string $name): array
    {
        $owner = User::factory()->create(['name' => $name, 'timezone' => 'UTC']);
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
