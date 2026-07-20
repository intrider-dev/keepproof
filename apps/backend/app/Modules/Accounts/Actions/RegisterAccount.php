<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Enums\HouseholdRole;
use App\Modules\Households\Models\Household;
use App\Modules\Households\Models\HouseholdMember;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RegisterAccount
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(
        string $name,
        string $email,
        string $password,
        string $timezone,
        string $locale,
    ): User {
        return DB::transaction(function () use ($name, $email, $password, $timezone, $locale): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'timezone' => $timezone,
                'locale' => $locale,
            ]);

            $household = Household::query()->create([
                'name' => sprintf("%s's household", $name),
                'owner_user_id' => $user->id,
                'is_personal' => true,
            ]);

            HouseholdMember::query()->create([
                'household_id' => $household->id,
                'user_id' => $user->id,
                'role' => HouseholdRole::Owner,
                'joined_at' => now(),
            ]);

            $this->audit->record('account.registered', $user, $household, $user);

            return $user;
        });
    }
}
