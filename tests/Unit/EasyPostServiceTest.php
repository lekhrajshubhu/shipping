<?php

uses(Tests\TestCase::class);

use Systha\Shipping\Domains\EasyPost\Services\EasyPostService;

it('rejects missing EasyPost api key before any network call', function (): void {
    config()->set('shipping.easypost.api_key', null);

    expect(fn () => app(EasyPostService::class)->getRates([
        'street1' => '118 2nd Street',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '94105',
        'country' => 'US',
    ], [
        'street1' => '179 N Harbor Dr',
        'city' => 'Redondo Beach',
        'state' => 'CA',
        'zip' => '90277',
        'country' => 'US',
    ]))
        ->toThrow(\RuntimeException::class, 'EasyPost API key is not configured.');
});
