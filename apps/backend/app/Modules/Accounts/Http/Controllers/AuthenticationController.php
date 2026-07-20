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
        $payload = $request->payload();
        $user = $register->execute(
            name: $payload->name,
            email: $payload->email,
            password: $payload->password,
            timezone: $payload->timezone,
            locale: $payload->locale,
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
        $authenticated = Auth::guard('web')->attempt($request->credentials());

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::guard('web')->user();

        if (! $user instanceof User) {
            throw new \LogicException('The web guard authenticated an unsupported user model.');
        }

        $audit->record('account.logged_in', $user, subject: $user);

        return new UserResource($user->load('households'));
    }

    public function logout(Request $request, AuditRecorder $audit): Response
    {
        $user = $this->authenticatedUser($request);
        $audit->record('account.logged_out', $user, subject: $user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();

        return response()->noContent();
    }
}
