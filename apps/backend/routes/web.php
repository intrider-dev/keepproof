<?php

declare(strict_types=1);

use App\Modules\Shared\Http\Controllers\ReadinessController;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    return view('welcome');
});

Route::get('/health/ready', ReadinessController::class);
