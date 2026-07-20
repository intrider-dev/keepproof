<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Actions\RegisterAccount;
use App\Modules\Accounts\Http\Requests\LoginRequest;
use App\Modules\Accounts\Http\Requests\RegisterRequest;
use App\Modules\Accounts\Http\Resources\UserResource;
use App\Modules\Accounts\Models\User;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class AuthenticationController extends Controller
{
    public function register(RegisterRequest $request, RegisterAccount $register): JsonResponse
    {
        $user = $register->execute(
            name: (string) $request->validated('name'),
            email: (string) $request->validated('email'),
            password: (string) $request->validated('password'),
            timezone: (string) $request->validated('timezone'),
            locale: (string) $request->validated('locale'),
        );

        event(new Registered($user));
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->load('households');

        return (new UserResource($user))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /** @throws ValidationException */
    public function login(LoginRequest $request, AuditRecorder $audit): UserResource
    {
        $authenticated = Auth::guard('web')->attempt([
            'email' => (string) $request->validated('email'),
            'password' => (string) $request->validated('password'),
        ]);

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $audit->record('account.logged_in', $user, subject: $user);

        return new UserResource($user->load('households'));
    }

    public function logout(Request $request, AuditRecorder $audit): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null) {
            $audit->record('account.logged_out', $user, subject: $user);
        }

        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
