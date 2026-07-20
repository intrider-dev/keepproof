<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Http\Middleware;

use App\Modules\Shared\Http\ProblemDetails;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireRecentPasswordConfirmation
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = $request->session()->get('auth.password_confirmed_at');
        $timeout = config('auth.password_timeout', 10800);
        $validTimeout = is_int($timeout) ? $timeout : 10800;

        if (! is_int($confirmedAt) || $confirmedAt < time() - $validTimeout) {
            return ProblemDetails::response(
                $request,
                403,
                'Recent password confirmation is required for this sensitive action.',
                ['code' => 'password_confirmation_required'],
            );
        }

        return $next($request);
    }
}
