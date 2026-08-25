<?php

use Illuminate\Support\Facades\Route;
use Systha\Shipping\Domains\EasyPost\Controllers\AddressController;
use Systha\Shipping\Domains\EasyPost\Controllers\EasyPostController;
use Systha\Shipping\Domains\EasyPost\Controllers\ShippingRateController;

Route::prefix('shipping/easypost')->group(function (): void {
    Route::get('/', [EasyPostController::class, 'index']);
    Route::post('/rates/test', [ShippingRateController::class, 'test']);
    Route::post('/rates/estimate', [ShippingRateController::class, 'estimate']);
    Route::post('/addresses/verify', [AddressController::class, 'verify']);
});
