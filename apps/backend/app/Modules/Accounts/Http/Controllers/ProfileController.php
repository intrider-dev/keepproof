<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Http\Requests\UpdateProfileRequest;
use App\Modules\Accounts\Http\Resources\UserResource;
use App\Modules\Accounts\Models\User;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->load('households'));
    }

    public function update(UpdateProfileRequest $request, AuditRecorder $audit): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $emailChanged = false;
        $changedFields = [];

        DB::transaction(function () use ($request, $user, $audit, &$emailChanged, &$changedFields): void {
            $user->fill($request->validated());
            $emailChanged = $user->isDirty('email');
            $changedFields = array_keys($user->getDirty());

            if ($emailChanged) {
                $user->email_verified_at = null;
                $changedFields[] = 'email_verified_at';
            }

            $user->save();
            $audit->record(
                'account.profile_updated',
                $user,
                subject: $user,
                metadata: ['changed_fields' => implode(',', array_unique($changedFields))],
            );
        });

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return new UserResource($user->refresh()->load('households'));
    }
}
