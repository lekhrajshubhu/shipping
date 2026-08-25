<?php

use Illuminate\Support\Facades\Route;
use Systha\Shipping\Domains\Shared\Controllers\HealthController;

Route::prefix('shipping')->group(function (): void {
    Route::get('/health', [HealthController::class, 'index']);
});
