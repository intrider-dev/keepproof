<?php

declare(strict_types=1);

namespace App\Modules\Households\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Enums\HouseholdRole;
use App\Modules\Households\Models\Household;
use App\Modules\Households\Models\HouseholdMember;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransferHouseholdOwnership
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(User $actor, Household $household, string $newOwnerUserId): Household
    {
        DB::transaction(function () use ($actor, $household, $newOwnerUserId): void {
            $lockedHousehold = Household::query()->whereKey($household->id)->lockForUpdate()->firstOrFail();
            if ($lockedHousehold->owner_user_id !== $actor->id) {
                throw new AuthorizationException('Only the current household owner can transfer ownership.');
            }
            if ($newOwnerUserId === $actor->id) {
                throw ValidationException::withMessages([
                    'member_user_id' => ['Choose another household member as the new owner.'],
                ]);
            }

            $memberships = HouseholdMember::query()
                ->where('household_id', $lockedHousehold->id)
                ->whereIn('user_id', [$actor->id, $newOwnerUserId])
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');
            $currentOwner = $memberships->get($actor->id);
            $newOwner = $memberships->get($newOwnerUserId);
            if (! $currentOwner instanceof HouseholdMember || $currentOwner->role !== HouseholdRole::Owner) {
                throw new AuthorizationException('The current owner membership is unavailable.');
            }
            if (! $newOwner instanceof HouseholdMember || $newOwner->role !== HouseholdRole::Member) {
                throw ValidationException::withMessages([
                    'member_user_id' => ['The new owner must already be a member of this household.'],
                ]);
            }

            $currentOwner->update(['role' => HouseholdRole::Member]);
            $newOwner->update(['role' => HouseholdRole::Owner]);
            $lockedHousehold->update(['owner_user_id' => $newOwnerUserId, 'is_personal' => false]);
            $this->audit->record(
                'household.ownership_transferred',
                $actor,
                $lockedHousehold,
                $newOwner,
                [
                    'previous_owner_user_id' => $actor->id,
                    'new_owner_user_id' => $newOwnerUserId,
                ],
            );
        });

        return $household->refresh()->loadCount('members');
    }
}
