<?php

declare(strict_types=1);

namespace App\Modules\Documents\Policies;

use App\Modules\Accounts\Models\User;
use App\Modules\Documents\Models\ReceiptDocument;
use App\Modules\Households\Models\Household;

final class ReceiptDocumentPolicy
{
    public function view(User $user, ReceiptDocument $document): bool
    {
        return $this->memberOf($user, $document->household_id);
    }

    public function create(User $user, Household $household): bool
    {
        return $this->memberOf($user, $household->id);
    }

    public function update(User $user, ReceiptDocument $document): bool
    {
        return $this->view($user, $document);
    }

    public function delete(User $user, ReceiptDocument $document): bool
    {
        return $this->view($user, $document);
    }

    private function memberOf(User $user, string $householdId): bool
    {
        return $user->householdMemberships()->where('household_id', $householdId)->exists();
    }
}
