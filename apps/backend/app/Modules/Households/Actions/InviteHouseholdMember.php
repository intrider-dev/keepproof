<?php

declare(strict_types=1);

namespace App\Modules\Households\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Enums\HouseholdRole;
use App\Modules\Households\Models\Household;
use App\Modules\Households\Models\HouseholdInvitation;
use App\Modules\Households\Notifications\HouseholdInvitationNotification;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final readonly class InviteHouseholdMember
{
    public function __construct(private AuditRecorder $audit) {}

    /** @throws ValidationException */
    public function execute(Household $household, User $inviter, string $email): HouseholdInvitation
    {
        $alreadyMember = $household->members()->where('email', $email)->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages([
                'email' => ['This person is already a household member.'],
            ]);
        }

        $plainToken = bin2hex(random_bytes(32));

        $invitation = DB::transaction(function () use ($household, $inviter, $email, $plainToken): HouseholdInvitation {
            $household->invitations()
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->delete();

            $invitation = HouseholdInvitation::query()->create([
                'household_id' => $household->id,
                'invited_by_user_id' => $inviter->id,
                'email' => $email,
                'role' => HouseholdRole::Member,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addHours(72),
            ]);

            $this->audit->record('household.member_invited', $inviter, $household, $invitation);

            return $invitation;
        });

        Notification::route('mail', $email)->notify(
            new HouseholdInvitationNotification($invitation, $household, $plainToken),
        );

        return $invitation;
    }
}
