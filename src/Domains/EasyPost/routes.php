<?php

use Illuminate\Support\Facades\Route;
use Systha\Shipping\Domains\EasyPost\Controllers\AddressController;
use Systha\Shipping\Domains\EasyPost\Controllers\EasyPostController;
use Systha\Shipping\Domains\EasyPost\Controllers\ShippingLabelController;
use Systha\Shipping\Domains\EasyPost\Controllers\ShippingRateController;

Route::prefix('api/v2/shipping/easypost')->group(function (): void {
    Route::get('/', [EasyPostController::class, 'index']);
    Route::post('/rates/test', [ShippingRateController::class, 'test']);

    Route::post('/rates/estimate', [ShippingRateController::class, 'estimate']);
    
    Route::post('/addresses/verify', [AddressController::class, 'verify']);
    
    Route::post('/labels/generate', [ShippingLabelController::class, 'generate']);
    
    Route::post('/rates/selected', [ShippingRateController::class, 'selected']);

    Route::post('/rates/shipment', [ShippingRateController::class, 'shipmentRates']);
});
