<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class EmailVerificationController extends Controller
{
    public function resend(Request $request): Response
    {
        $user = $this->authenticatedUser($request);

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->noContent();
    }

    public function verify(
        Request $request,
        string $id,
        string $hash,
        AuditRecorder $audit,
    ): Response|RedirectResponse {
        $user = $this->authenticatedUser($request);

        abort_unless(
            hash_equals($user->id, $id)
            && hash_equals(sha1($user->getEmailForVerification()), $hash),
            Response::HTTP_FORBIDDEN,
        );

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
            $audit->record('account.email_verified', $user, subject: $user);
        }

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        if (! is_string($frontendUrl)) {
            $frontendUrl = 'http://localhost:5173';
        }

        return redirect()->away(rtrim($frontendUrl, '/').'/verify-email?verified=1');
    }
}
