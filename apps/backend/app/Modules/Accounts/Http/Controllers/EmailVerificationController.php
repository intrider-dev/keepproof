<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class EmailVerificationController extends Controller
{
    public function resend(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->noContent();
    }

    public function verify(Request $request, string $id, string $hash, AuditRecorder $audit): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(
            hash_equals((string) $user->getKey(), $id)
            && hash_equals(sha1($user->getEmailForVerification()), $hash),
            Response::HTTP_FORBIDDEN,
        );

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
            $audit->record('account.email_verified', $user, subject: $user);
        }

        return response()->noContent();
    }
}
