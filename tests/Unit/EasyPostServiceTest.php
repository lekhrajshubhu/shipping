<?php

uses(Tests\TestCase::class);

use RuntimeException;
use Systha\Shipping\Domains\EasyPost\Services\EasyPostService;

it('rejects missing EasyPost api key before any network call', function (): void {
    config()->set('shipping.easypost.api_key', null);

    expect(fn () => app(EasyPostService::class)->getRates())
        ->toThrow(RuntimeException::class, 'EasyPost API key is not configured.');
});
