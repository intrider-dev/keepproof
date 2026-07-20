<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Models\Household;
use App\Modules\Households\Policies\HouseholdPolicy;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Purchases\Policies\PurchasePolicy;
use App\Modules\Shared\Configuration\FrontendUrl;
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
        Gate::policy(Purchase::class, PurchasePolicy::class);

        Route::bind('household', static function (string $value): Household {
            $user = request()->user();

            if (! $user instanceof User) {
                throw (new ModelNotFoundException)->setModel(Household::class, [$value]);
            }

            return $user->households()->whereKey($value)->firstOrFail();
        });

        Route::bind('purchase', static function (string $value): Purchase {
            $household = request()->route('household');

            if ($household instanceof Household) {
                return $household->purchases()->whereKey($value)->firstOrFail();
            }

            $user = request()->user();
            if (! $user instanceof User) {
                throw (new ModelNotFoundException)->setModel(Purchase::class, [$value]);
            }

            return Purchase::query()
                ->whereKey($value)
                ->whereIn('household_id', $user->households()->select('households.id'))
                ->firstOrFail();
        });

        RateLimiter::for('auth.login', static fn (Request $request): Limit => Limit::perMinute(5)->by(
            self::emailThrottleKey($request),
        ));
        RateLimiter::for('auth.register', static fn (Request $request): Limit => Limit::perMinute(5)->by(
            self::requestIp($request),
        ));
        RateLimiter::for('auth.password', static fn (Request $request): Limit => Limit::perMinute(3)->by(
            self::emailThrottleKey($request),
        ));
        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(120)->by(
            self::apiThrottleKey($request),
        ));

        ResetPassword::createUrlUsing(static function (mixed $notifiable, string $token): string {
            if (! $notifiable instanceof User) {
                throw new \LogicException('Password reset notifications require the KeepProof user model.');
            }

            return sprintf(
                '%s/reset-password?token=%s&email=%s',
                FrontendUrl::base(),
                rawurlencode($token),
                rawurlencode($notifiable->email),
            );
        });
    }

    private static function emailThrottleKey(Request $request): string
    {
        $email = $request->input('email');
        $normalized = is_string($email) ? Str::lower(trim($email)) : 'invalid-email';

        return $normalized.'|'.self::requestIp($request);
    }

    private static function apiThrottleKey(Request $request): string
    {
        $identifier = $request->user()?->getAuthIdentifier();

        if (is_string($identifier)) {
            return $identifier;
        }

        if (is_int($identifier)) {
            return (string) $identifier;
        }

        return self::requestIp($request);
    }

    private static function requestIp(Request $request): string
    {
        return $request->ip() ?? 'unknown-ip';
    }
}
