<?php

uses(Tests\TestCase::class);

use EasyPost\Exception\General\EasyPostException;
use RuntimeException;
use Systha\Shipping\Domains\EasyPost\Services\EasyPostService;

it('returns normalized shipping rates from the EasyPost endpoint', function (): void {
    $this->mock(EasyPostService::class, function ($mock): void {
        $mock->shouldReceive('getRates')
            ->once()
            ->andReturn([
                'shipment_id' => 'shp_test_123',
                'parcel' => [
                    'length' => 4,
                    'width' => 4,
                    'height' => 4,
                    'weight' => 1.92,
                    'dimension_unit' => 'in',
                    'weight_unit' => 'oz',
                ],
                'rates' => [
                    [
                        'id' => 'rate_1',
                        'carrier' => 'USPS',
                        'service' => 'GroundAdvantage',
                        'rate' => '6.25',
                        'currency' => 'USD',
                        'delivery_days' => 4,
                        'delivery_date' => null,
                        'delivery_date_guaranteed' => false,
                    ],
                    [
                        'id' => 'rate_2',
                        'carrier' => 'UPS',
                        'service' => 'Ground',
                        'rate' => '9.50',
                        'currency' => 'USD',
                        'delivery_days' => 3,
                        'delivery_date' => null,
                        'delivery_date_guaranteed' => false,
                    ],
                ],
            ]);
    });

    $this->postJson('/shipping/easypost/rates/estimate')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.shipment_id', 'shp_test_123')
        ->assertJsonPath('data.parcel.length', 4)
        ->assertJsonPath('data.parcel.width', 4)
        ->assertJsonPath('data.parcel.height', 4)
        ->assertJsonPath('data.parcel.weight', 1.92)
        ->assertJsonPath('data.parcel.dimension_unit', 'in')
        ->assertJsonPath('data.parcel.weight_unit', 'oz')
        ->assertJsonPath('data.rates.0.id', 'rate_1')
        ->assertJsonPath('data.rates.0.carrier', 'USPS')
        ->assertJsonPath('data.rates.0.service', 'GroundAdvantage')
        ->assertJsonPath('data.rates.0.rate', '6.25')
        ->assertJsonPath('data.rates.0.currency', 'USD')
        ->assertJsonPath('data.rates.0.delivery_days', 4)
        ->assertJsonPath('data.rates.0.delivery_date', null)
        ->assertJsonPath('data.rates.0.delivery_date_guaranteed', false)
        ->assertJsonPath('data.rates.1.id', 'rate_2')
        ->assertJsonPath('data.rates.1.carrier', 'UPS')
        ->assertJsonPath('data.rates.1.service', 'Ground')
        ->assertJsonPath('data.rates.1.rate', '9.50')
        ->assertJsonPath('data.rates.1.currency', 'USD')
        ->assertJsonPath('data.rates.1.delivery_days', 3)
        ->assertJsonPath('data.rates.1.delivery_date', null)
        ->assertJsonPath('data.rates.1.delivery_date_guaranteed', false);
});

it('returns a safe provider error response when the EasyPost service fails', function (): void {
    $this->mock(EasyPostService::class, function ($mock): void {
        $mock->shouldReceive('getRates')
            ->once()
            ->andThrow(new RuntimeException(
                'EasyPost shipment rate estimation failed.',
                0,
                new EasyPostException('EasyPost API error.')
            ));
    });

    $this->postJson('/shipping/easypost/rates/estimate')
        ->assertStatus(502)
        ->assertExactJson([
            'success' => false,
            'message' => 'Unable to retrieve shipping rates from EasyPost.',
        ]);
});
