<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProblemDetails
{
    /** @param array<string, mixed> $extensions */
    public static function response(
        Request $request,
        int $status,
        string $detail,
        array $extensions = [],
    ): JsonResponse {
        $title = Response::$statusTexts[$status] ?? 'Request failed';
        $payload = [
            'type' => sprintf('https://httpstatuses.com/%d', $status),
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => '/'.$request->path(),
            'correlation_id' => $request->attributes->get('correlation_id'),
            ...$extensions,
        ];

        $correlationId = $request->attributes->get('correlation_id');

        return response()->json(
            $payload,
            $status,
            [
                'Content-Type' => 'application/problem+json',
                'X-Correlation-ID' => is_string($correlationId) ? $correlationId : '',
            ],
            JSON_UNESCAPED_SLASHES,
        );
    }
}
