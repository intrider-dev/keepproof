<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get('X-Correlation-ID');
        $correlationId = is_string($incoming) && Str::isUuid($incoming)
            ? Str::lower($incoming)
            : (string) Str::uuid();

        $request->attributes->set('correlation_id', $correlationId);
        Log::withContext(['correlation_id' => $correlationId]);

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new LogicException('HTTP middleware must return a Symfony response.');
        }

        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
