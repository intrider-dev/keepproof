<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Household> */
final class HouseholdFactory extends Factory
{
    /** @var class-string<Household> */
    protected $model = Household::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->lastName().' household',
            'owner_user_id' => User::factory(),
            'is_personal' => false,
        ];
    }
}
