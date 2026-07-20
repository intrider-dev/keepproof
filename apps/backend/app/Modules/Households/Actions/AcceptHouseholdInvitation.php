<?php

declare(strict_types=1);

namespace App\Modules\Households\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Models\Household;
use App\Modules\Households\Models\HouseholdInvitation;
use App\Modules\Households\Models\HouseholdMember;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AcceptHouseholdInvitation
{
    public function __construct(private AuditRecorder $audit) {}

    /** @throws ValidationException */
    public function execute(string $invitationId, string $plainToken, User $user): Household
    {
        return DB::transaction(function () use ($invitationId, $plainToken, $user): Household {
            $invitation = HouseholdInvitation::query()
                ->whereKey($invitationId)
                ->lockForUpdate()
                ->first();

            if ($invitation === null
                || ! hash_equals($invitation->token_hash, hash('sha256', $plainToken))
                || ! hash_equals($invitation->email, $user->email)
            ) {
                throw ValidationException::withMessages([
                    'invitation' => ['The invitation is invalid or has expired.'],
                ]);
            }

            if ($invitation->accepted_at === null && $invitation->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'invitation' => ['The invitation is invalid or has expired.'],
                ]);
            }

            $household = $invitation->household()->firstOrFail();
            $membership = HouseholdMember::query()->firstOrCreate(
                ['household_id' => $household->id, 'user_id' => $user->id],
                ['role' => $invitation->role, 'joined_at' => now()],
            );

            if ($invitation->accepted_at === null) {
                $invitation->forceFill(['accepted_at' => now()])->save();
                $this->audit->record('household.invitation_accepted', $user, $household, $membership);
            }

            return $household;
        });
    }
}
