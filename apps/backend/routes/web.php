<?php

declare(strict_types=1);

use App\Modules\Shared\Http\Controllers\ReadinessController;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    return view('welcome');
});

Route::get('/health/ready', ReadinessController::class);

Route::get('/docs/openapi.yaml', static function () {
    return response()->file(base_path('openapi/openapi.yaml'), [
        'Content-Type' => 'application/yaml; charset=utf-8',
        'Cache-Control' => 'no-store',
    ]);
});
