<?php

declare(strict_types=1);

namespace App\Modules\Households\Policies;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Enums\HouseholdRole;
use App\Modules\Households\Models\Household;

final class HouseholdPolicy
{
    public function view(User $user, Household $household): bool
    {
        return $household->members()->whereKey($user->getKey())->exists();
    }

    public function update(User $user, Household $household): bool
    {
        return $this->isOwner($user, $household);
    }

    public function viewMembers(User $user, Household $household): bool
    {
        return $this->view($user, $household);
    }

    public function manageMembers(User $user, Household $household): bool
    {
        return $this->isOwner($user, $household);
    }

    private function isOwner(User $user, Household $household): bool
    {
        return $household->memberships()
            ->where('user_id', $user->getKey())
            ->where('role', HouseholdRole::Owner->value)
            ->exists();
    }
}
