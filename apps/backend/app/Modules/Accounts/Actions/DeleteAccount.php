<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Exceptions\SharedHouseholdOwnershipBlocksDeletion;
use App\Modules\Accounts\Models\User;
use App\Modules\Documents\Models\ReceiptDocument;
use App\Modules\Households\Models\Household;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final readonly class DeleteAccount
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(User $user): void
    {
        $sharedOwnedHouseholdExists = Household::query()
            ->where('owner_user_id', $user->id)
            ->has('memberships', '>', 1)
            ->exists();
        if ($sharedOwnedHouseholdExists) {
            $this->audit->record('account.deletion_blocked', $user, subject: $user, metadata: [
                'reason' => 'shared_household_ownership',
            ]);

            throw new SharedHouseholdOwnershipBlocksDeletion;
        }

        $householdIds = Household::query()->where('owner_user_id', $user->id)->pluck('id');
        $documents = ReceiptDocument::query()->whereIn('household_id', $householdIds)->get([
            'id', 'storage_disk', 'storage_key',
        ]);

        foreach ($documents->groupBy('storage_disk') as $disk => $group) {
            if (! is_string($disk)) {
                throw new RuntimeException('A receipt storage disk is invalid.');
            }
            $keys = $group->pluck('storage_key')
                ->filter(static fn (mixed $key): bool => is_string($key))
                ->values()
                ->all();
            if ($keys !== [] && ! Storage::disk($disk)->delete($keys)) {
                throw new RuntimeException('Account deletion stopped because receipt storage is unavailable.');
            }
        }

        DB::transaction(function () use ($user): void {
            $this->audit->record('account.deleted', $user, subject: $user, metadata: [
                'deletion_scope' => 'owned_households_and_memberships',
            ]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });
    }
}
