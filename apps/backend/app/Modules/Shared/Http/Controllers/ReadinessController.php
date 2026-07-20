<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
        } catch (\Throwable) {
            return response()->json([
                'status' => 'unavailable',
                'checks' => ['database' => 'failed'],
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json([
            'status' => 'ready',
            'checks' => ['database' => 'ok'],
        ]);
    }
}
