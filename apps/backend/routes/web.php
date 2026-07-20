<?php

declare(strict_types=1);

use App\Modules\Accounts\Http\Controllers\EmailVerificationController;
use App\Modules\Documents\Http\Controllers\ReceiptDocumentController;
use App\Modules\Shared\Http\Controllers\ReadinessController;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    return view('welcome');
});

Route::get('/health/ready', ReadinessController::class);

// Verification links are top-level browser navigations and therefore do not
// carry the Origin header Sanctum uses to identify a stateful SPA request.
Route::get('/api/v1/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/api/v1/receipt-downloads/{documentId}', [ReceiptDocumentController::class, 'serveOriginal'])
    ->whereUlid('documentId')
    ->middleware(['auth', 'signed:relative', 'throttle:30,1'])
    ->name('receipt.original.download');

Route::get('/docs/openapi.yaml', static function () {
    return response()->file(base_path('openapi/openapi.yaml'), [
        'Content-Type' => 'application/yaml; charset=utf-8',
        'Cache-Control' => 'no-store',
    ]);
});
