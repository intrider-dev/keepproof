<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Policies;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Models\Household;
use App\Modules\Purchases\Models\Purchase;

final class PurchasePolicy
{
    public function view(User $user, Purchase $purchase): bool
    {
        return $this->memberOf($user, $purchase->household_id);
    }

    public function create(User $user, Household $household): bool
    {
        return $this->memberOf($user, $household->id);
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return $this->view($user, $purchase);
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return $this->view($user, $purchase);
    }

    private function memberOf(User $user, string $householdId): bool
    {
        return $user->householdMemberships()->where('household_id', $householdId)->exists();
    }
}
