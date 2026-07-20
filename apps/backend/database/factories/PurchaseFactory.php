<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Models\Household;
use App\Modules\Purchases\Enums\PurchaseState;
use App\Modules\Purchases\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;
use KeepProof\DeadlineEngine\CountingConvention;

/** @extends Factory<Purchase> */
final class PurchaseFactory extends Factory
{
    /** @var class-string<Purchase> */
    protected $model = Purchase::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'created_by_user_id' => User::factory(),
            'state' => PurchaseState::Active,
            'merchant' => fake()->company(),
            'purchase_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'timezone' => 'UTC',
            'receipt_number' => strtoupper(fake()->bothify('KP-####??')),
            'currency' => 'USD',
            'total_minor' => fake()->numberBetween(1_00, 200_000),
            'country_region' => 'US',
            'return_period_quantity' => 30,
            'return_period_unit' => 'days',
            'return_counting_convention' => CountingConvention::PurchaseDayExclusive,
            'warranty_period_quantity' => 12,
            'warranty_period_unit' => 'months',
            'warranty_counting_convention' => CountingConvention::PurchaseDayExclusive,
            'activated_at' => now(),
        ];
    }
}
