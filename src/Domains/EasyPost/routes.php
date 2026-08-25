<?php

use Illuminate\Support\Facades\Route;
use Systha\Shipping\Domains\EasyPost\Controllers\EasyPostController;

Route::prefix('shipping/easypost')->group(function (): void {
    Route::get('/', [EasyPostController::class, 'index']);
});
