<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Actions\ChangePassword;
use App\Modules\Accounts\Http\Requests\ChangePasswordRequest;
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
    public function update(ChangePasswordRequest $request, ChangePassword $changePassword): Response
    {
        $user = $this->authenticatedUser($request);
        $changePassword->execute(
            $user,
            $request->currentPassword(),
            $request->newPassword(),
            $request->session()->getId(),
        );
        $request->session()->regenerate();
        $request->session()->put('auth.password_confirmed_at', time());

        return response()->noContent();
    }

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink(['email' => $request->email()]);

        return response()->json([
            'message' => 'If an account matches that email, a password reset link has been sent.',
        ], Response::HTTP_ACCEPTED);
    }

    /** @throws ValidationException */
    public function reset(ResetPasswordRequest $request, AuditRecorder $audit): Response
    {
        $status = Password::reset(
            $request->credentials(),
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
        $user = $this->authenticatedUser($request);

        if (! Hash::check($request->password(), $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        $confirmedAt = time();
        $request->session()->put('auth.password_confirmed_at', $confirmedAt);
        $audit->record('account.password_confirmed', $user, subject: $user);

        return response()->json([
            'confirmed_until' => now()->addSeconds($this->confirmationTimeout())->toAtomString(),
        ]);
    }

    private function confirmationTimeout(): int
    {
        $configured = config('auth.password_timeout');

        if (is_int($configured) && $configured > 0) {
            return $configured;
        }

        if (is_string($configured) && ctype_digit($configured) && $configured !== '0') {
            return (int) $configured;
        }

        throw new \LogicException('The auth.password_timeout configuration must be a positive integer.');
    }
}
