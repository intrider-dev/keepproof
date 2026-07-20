<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Http\Requests\ConfirmPasswordRequest;
use App\Modules\Accounts\Http\Requests\ForgotPasswordRequest;
use App\Modules\Accounts\Http\Requests\ResetPasswordRequest;
use App\Modules\Accounts\Models\User;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PasswordController extends Controller
{
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink(['email' => (string) $request->validated('email')]);

        return response()->json([
            'message' => 'If an account matches that email, a password reset link has been sent.',
        ], Response::HTTP_ACCEPTED);
    }

    /** @throws ValidationException */
    public function reset(ResetPasswordRequest $request, AuditRecorder $audit): Response
    {
        $status = Password::reset(
            $request->safe()->only(['email', 'password', 'password_confirmation', 'token']),
            static function (User $user, string $password) use ($audit): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $audit->record('account.password_reset', $user, subject: $user);
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['The password reset link is invalid or has expired.'],
            ]);
        }

        return response()->noContent();
    }

    /** @throws ValidationException */
    public function confirm(ConfirmPasswordRequest $request, AuditRecorder $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check((string) $request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        $confirmedAt = time();
        $request->session()->put('auth.password_confirmed_at', $confirmedAt);
        $audit->record('account.password_confirmed', $user, subject: $user);

        return response()->json([
            'confirmed_until' => now()->addSeconds((int) config('auth.password_timeout'))->toAtomString(),
        ]);
    }
}
