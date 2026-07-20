<?php

declare(strict_types=1);

use App\Modules\Accounts\Http\Controllers\AccountDataController;
use App\Modules\Accounts\Http\Controllers\AuthenticationController;
use App\Modules\Accounts\Http\Controllers\EmailVerificationController;
use App\Modules\Accounts\Http\Controllers\PasswordController;
use App\Modules\Accounts\Http\Controllers\ProfileController;
use App\Modules\Dashboard\Http\Controllers\DashboardController;
use App\Modules\Documents\Http\Controllers\ReceiptDocumentController;
use App\Modules\Exports\Http\Controllers\PurchaseExportController;
use App\Modules\Households\Http\Controllers\HouseholdController;
use App\Modules\Households\Http\Controllers\HouseholdInvitationController;
use App\Modules\Households\Http\Controllers\HouseholdMemberController;
use App\Modules\Notifications\Http\Controllers\InAppNotificationController;
use App\Modules\Notifications\Http\Controllers\NotificationPreferenceController;
use App\Modules\Purchases\Http\Controllers\PurchaseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthenticationController::class, 'register'])
            ->middleware('throttle:auth.register');
        Route::post('/login', [AuthenticationController::class, 'login'])
            ->middleware('throttle:auth.login');
        Route::post('/forgot-password', [PasswordController::class, 'forgot'])
            ->middleware('throttle:auth.password');
        Route::post('/reset-password', [PasswordController::class, 'reset'])
            ->middleware('throttle:auth.password');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthenticationController::class, 'logout']);
        Route::post('/auth/confirm-password', [PasswordController::class, 'confirm'])
            ->middleware('throttle:6,1');
        Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:3,1');
        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::get('/me', [ProfileController::class, 'show']);

        Route::middleware('verified')->group(function (): void {
            Route::patch('/me', [ProfileController::class, 'update']);

            Route::get('/households', [HouseholdController::class, 'index']);
            Route::get('/households/{household}', [HouseholdController::class, 'show']);
            Route::patch('/households/{household}', [HouseholdController::class, 'update']);
            Route::get('/households/{household}/members', [HouseholdMemberController::class, 'index']);
            Route::delete('/households/{household}/members/{member}', [HouseholdMemberController::class, 'destroy']);
            Route::post('/households/{household}/invitations', [HouseholdInvitationController::class, 'store']);
            Route::post('/invitations/{invitation}/accept', [HouseholdInvitationController::class, 'accept']);
            Route::get('/households/{household}/dashboard', [DashboardController::class, 'show']);

            Route::get('/households/{household}/purchases', [PurchaseController::class, 'index']);
            Route::post('/households/{household}/purchases', [PurchaseController::class, 'store']);
            Route::get('/households/{household}/purchases/{purchase}', [PurchaseController::class, 'show']);
            Route::put('/households/{household}/purchases/{purchase}', [PurchaseController::class, 'update']);
            Route::delete('/households/{household}/purchases/{purchase}', [PurchaseController::class, 'destroy']);
            Route::get('/households/{household}/purchases/{purchase}/deadlines/{deadline}/calendar', [PurchaseExportController::class, 'calendar']);
            Route::get('/households/{household}/purchases/{purchase}/claim-package', [PurchaseExportController::class, 'claimPackage'])
                ->middleware('throttle:10,1');

            Route::post('/households/{household}/receipts', [ReceiptDocumentController::class, 'store'])
                ->middleware('throttle:10,1');
            Route::get('/households/{household}/documents/{document}', [ReceiptDocumentController::class, 'show']);
            Route::post('/households/{household}/documents/{document}/download', [ReceiptDocumentController::class, 'download'])
                ->middleware('throttle:30,1');
            Route::put('/households/{household}/documents/{document}/confirmation', [ReceiptDocumentController::class, 'confirm']);
            Route::post('/households/{household}/documents/{document}/retry', [ReceiptDocumentController::class, 'retry'])
                ->middleware('throttle:6,1');
            Route::delete('/households/{household}/documents/{document}/original', [ReceiptDocumentController::class, 'destroyOriginal']);

            Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show']);
            Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);
            Route::get('/notifications', [InAppNotificationController::class, 'index']);
            Route::put('/notifications/{notification}/read', [InAppNotificationController::class, 'read']);

            Route::middleware('password.recent')->group(function (): void {
                Route::get('/account/export', [AccountDataController::class, 'export'])
                    ->middleware('throttle:3,60');
                Route::delete('/account', [AccountDataController::class, 'destroy'])
                    ->middleware('throttle:3,60');
            });
        });
    });
});
