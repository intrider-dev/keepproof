<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RequireStatefulSession
{
    /**
     * Anonymous account mutations are first-party browser operations. Failing
     * before the controller prevents them when an origin was omitted from
     * Sanctum's stateful allowlist and no session middleware was installed.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            throw new HttpException(419, 'A stateful browser session is required.');
        }

        return $next($request);
    }
}
