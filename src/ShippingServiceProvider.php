<?php

namespace Systha\Shipping;

use Illuminate\Support\ServiceProvider;

class ShippingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once __DIR__.'/Support/legacy.php';
        require_once __DIR__.'/Support/helpers.php';

        $this->mergeConfigFrom(
            __DIR__.'/../config/shipping.php',
            'shipping'
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(
            __DIR__.'/../routes/api.php'
        );

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'shipping');
    }
}
