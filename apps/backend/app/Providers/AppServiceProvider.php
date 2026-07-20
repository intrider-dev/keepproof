<?php

namespace App\Providers;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Models\Household;
use App\Modules\Households\Policies\HouseholdPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Password::defaults(static fn (): Password => Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Household::class, HouseholdPolicy::class);

        Route::bind('household', static function (string $value): Household {
            $user = request()->user();

            if (! $user instanceof User) {
                throw (new ModelNotFoundException)->setModel(Household::class, [$value]);
            }

            return $user->households()->whereKey($value)->firstOrFail();
        });

        RateLimiter::for('auth.login', static fn (Request $request): Limit => Limit::perMinute(5)->by(
            Str::lower(trim((string) $request->input('email'))).'|'.$request->ip(),
        ));
        RateLimiter::for('auth.register', static fn (Request $request): Limit => Limit::perMinute(5)->by(
            (string) $request->ip(),
        ));
        RateLimiter::for('auth.password', static fn (Request $request): Limit => Limit::perMinute(3)->by(
            Str::lower(trim((string) $request->input('email'))).'|'.$request->ip(),
        ));
        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(120)->by(
            (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));

        ResetPassword::createUrlUsing(static fn (User $user, string $token): string => sprintf(
            '%s/reset-password?token=%s&email=%s',
            rtrim((string) config('app.frontend_url'), '/'),
            rawurlencode($token),
            rawurlencode($user->email),
        ));
    }
}
