<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ChangePassword
{
    public function __construct(private AuditRecorder $audit) {}

    /** @throws ValidationException */
    public function execute(
        User $user,
        string $currentPassword,
        string $newPassword,
        string $currentSessionId,
    ): void {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        DB::transaction(function () use ($user, $newPassword, $currentSessionId): void {
            $user->forceFill([
                'password' => $newPassword,
                'remember_token' => Str::random(60),
            ])->save();
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();
            $this->audit->record('account.password_changed', $user, subject: $user, metadata: [
                'other_sessions_revoked' => true,
            ]);
        });
    }
}
