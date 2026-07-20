<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];

        try {
            DB::select('SELECT 1');
            $checks['database'] = 'ok';
        } catch (Throwable) {
            $checks['database'] = 'failed';
        }

        $disk = config('receipt_processing.disk');
        try {
            $checks['storage'] = is_string($disk)
                && $disk !== ''
                && Storage::disk($disk)->exists('.keepproof/ready')
                    ? 'ok'
                    : 'failed';
        } catch (Throwable) {
            $checks['storage'] = 'failed';
        }

        $redisRequired = in_array('redis', [
            config('cache.default'),
            config('queue.default'),
            config('session.driver'),
        ], true);
        if ($redisRequired) {
            try {
                Redis::connection()->command('ping');
                $checks['redis'] = 'ok';
            } catch (Throwable) {
                $checks['redis'] = 'failed';
            }
        } else {
            $checks['redis'] = 'not_required';
        }

        $ready = ! in_array('failed', $checks, true);

        return response()->json([
            'status' => $ready ? 'ready' : 'unavailable',
            'checks' => $checks,
        ], $ready ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
