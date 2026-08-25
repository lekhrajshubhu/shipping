<?php

uses(Tests\TestCase::class);

use EasyPost\Exception\General\EasyPostException;
use Systha\Shipping\Domains\EasyPost\Services\EasyPostService;

it('returns configured shipping rates from the EasyPost test endpoint without a request body', function (): void {
    $this->mock(EasyPostService::class, function ($mock): void {
        $mock->shouldReceive('getConfiguredRates')
            ->once()
            ->andReturn([
                'shipment_id' => 'shp_test_configured',
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
                        'id' => 'rate_configured_1',
                        'carrier' => 'USPS',
                        'service' => 'GroundAdvantage',
                        'rate' => '6.25',
                        'currency' => 'USD',
                        'delivery_days' => 4,
                        'delivery_date' => null,
                        'delivery_date_guaranteed' => false,
                    ],
                ],
            ]);
    });

    $this->postJson('/shipping/easypost/rates/test')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.shipment_id', 'shp_test_configured')
        ->assertJsonPath('data.parcel.length', 4)
        ->assertJsonPath('data.parcel.width', 4)
        ->assertJsonPath('data.parcel.height', 4)
        ->assertJsonPath('data.parcel.weight', 1.92)
        ->assertJsonPath('data.parcel.dimension_unit', 'in')
        ->assertJsonPath('data.parcel.weight_unit', 'oz')
        ->assertJsonPath('data.rates.0.id', 'rate_configured_1')
        ->assertJsonPath('data.rates.0.carrier', 'USPS')
        ->assertJsonPath('data.rates.0.service', 'GroundAdvantage')
        ->assertJsonPath('data.rates.0.rate', '6.25')
        ->assertJsonPath('data.rates.0.currency', 'USD')
        ->assertJsonPath('data.rates.0.delivery_days', 4)
        ->assertJsonPath('data.rates.0.delivery_date', null)
        ->assertJsonPath('data.rates.0.delivery_date_guaranteed', false);
});

it('returns normalized shipping rates from the EasyPost endpoint', function (): void {
    $this->mock(EasyPostService::class, function ($mock): void {
        $fromAddress = [
            'street1' => '118 2nd Street',
            'street2' => '4th Floor',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94105',
            'country' => 'US',
            'name' => null,
            'company' => 'EasyPost',
            'phone' => '415-456-7890',
            'email' => null,
            'residential' => null,
        ];

        $toAddress = [
            'street1' => '179 N Harbor Dr',
            'street2' => null,
            'city' => 'Redondo Beach',
            'state' => 'CA',
            'zip' => '90277',
            'country' => 'US',
            'name' => 'Dr. Steve Brule',
            'company' => null,
            'phone' => '310-808-5243',
            'email' => null,
            'residential' => null,
        ];

        $mock->shouldReceive('getRates')
            ->with($fromAddress, $toAddress)
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

    $this->postJson('/shipping/easypost/rates/estimate', [
        'from_address' => [
            'street1' => '118 2nd Street',
            'street2' => '4th Floor',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94105',
            'country' => 'us',
            'name' => null,
            'company' => 'EasyPost',
            'phone' => '415-456-7890',
            'email' => null,
            'residential' => null,
        ],
        'to_address' => [
            'street1' => '179 N Harbor Dr',
            'street2' => null,
            'city' => 'Redondo Beach',
            'state' => 'CA',
            'zip' => '90277',
            'country' => 'us',
            'name' => 'Dr. Steve Brule',
            'company' => null,
            'phone' => '310-808-5243',
            'email' => null,
            'residential' => null,
        ],
    ])
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

it('requires a nested from address street1', function (): void {
    $this->postJson('/shipping/easypost/rates/estimate', [
        'from_address' => [
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94105',
            'country' => 'US',
        ],
        'to_address' => [
            'street1' => '179 N Harbor Dr',
            'city' => 'Redondo Beach',
            'state' => 'CA',
            'zip' => '90277',
            'country' => 'US',
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['from_address.street1']);
});

it('requires a nested to address zip', function (): void {
    $this->postJson('/shipping/easypost/rates/estimate', [
        'from_address' => [
            'street1' => '118 2nd Street',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94105',
            'country' => 'US',
        ],
        'to_address' => [
            'street1' => '179 N Harbor Dr',
            'city' => 'Redondo Beach',
            'state' => 'CA',
            'country' => 'US',
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['to_address.zip']);
});

it('requires from and to address payloads for dynamic rate estimation', function (array $payload, string $errorKey): void {
    $this->postJson('/shipping/easypost/rates/estimate', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$errorKey]);
})->with([
    'missing from address' => [
        [
            'to_address' => [
                'street1' => '179 N Harbor Dr',
                'city' => 'Redondo Beach',
                'state' => 'CA',
                'zip' => '90277',
                'country' => 'US',
            ],
        ],
        'from_address',
    ],
    'missing to address' => [
        [
            'from_address' => [
                'street1' => '118 2nd Street',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94105',
                'country' => 'US',
            ],
        ],
        'to_address',
    ],
]);

it('requires dynamic rate address fields and country length', function (array $payload, string $errorKey): void {
    $this->postJson('/shipping/easypost/rates/estimate', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$errorKey]);
})->with([
    'missing from street1' => [
        [
            'from_address' => [
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94105',
                'country' => 'US',
            ],
            'to_address' => [
                'street1' => '179 N Harbor Dr',
                'city' => 'Redondo Beach',
                'state' => 'CA',
                'zip' => '90277',
                'country' => 'US',
            ],
        ],
        'from_address.street1',
    ],
    'missing from country' => [
        [
            'from_address' => [
                'street1' => '118 2nd Street',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94105',
            ],
            'to_address' => [
                'street1' => '179 N Harbor Dr',
                'city' => 'Redondo Beach',
                'state' => 'CA',
                'zip' => '90277',
                'country' => 'US',
            ],
        ],
        'from_address.country',
    ],
    'missing to city' => [
        [
            'from_address' => [
                'street1' => '118 2nd Street',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94105',
                'country' => 'US',
            ],
            'to_address' => [
                'street1' => '179 N Harbor Dr',
                'state' => 'CA',
                'zip' => '90277',
                'country' => 'US',
            ],
        ],
        'to_address.city',
    ],
    'missing to zip' => [
        [
            'from_address' => [
                'street1' => '118 2nd Street',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94105',
                'country' => 'US',
            ],
            'to_address' => [
                'street1' => '179 N Harbor Dr',
                'city' => 'Redondo Beach',
                'state' => 'CA',
                'country' => 'US',
            ],
        ],
        'to_address.zip',
    ],
    'invalid country length' => [
        [
            'from_address' => [
                'street1' => '118 2nd Street',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94105',
                'country' => 'USA',
            ],
            'to_address' => [
                'street1' => '179 N Harbor Dr',
                'city' => 'Redondo Beach',
                'state' => 'CA',
                'zip' => '90277',
                'country' => 'US',
            ],
        ],
        'from_address.country',
    ],
]);

it('normalizes lowercase country codes before passing them to the service', function (): void {
    $this->mock(EasyPostService::class, function ($mock): void {
        $mock->shouldReceive('getRates')
            ->once()
            ->with(
                [
                    'street1' => '118 2nd Street',
                    'street2' => '4th Floor',
                    'city' => 'San Francisco',
                    'state' => 'CA',
                    'zip' => '94105',
                    'country' => 'US',
                    'name' => null,
                    'company' => 'EasyPost',
                    'phone' => '415-456-7890',
                    'email' => null,
                    'residential' => null,
                ],
                [
                    'street1' => '179 N Harbor Dr',
                    'street2' => null,
                    'city' => 'Redondo Beach',
                    'state' => 'CA',
                    'zip' => '90277',
                    'country' => 'US',
                    'name' => 'Dr. Steve Brule',
                    'company' => null,
                    'phone' => '310-808-5243',
                    'email' => null,
                    'residential' => null,
                ]
            )
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
                'rates' => [],
            ]);
    });

    $this->postJson('/shipping/easypost/rates/estimate', [
        'from_address' => [
            'street1' => '118 2nd Street',
            'street2' => '4th Floor',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94105',
            'country' => 'us',
            'name' => null,
            'company' => 'EasyPost',
            'phone' => '415-456-7890',
            'email' => null,
            'residential' => null,
        ],
        'to_address' => [
            'street1' => '179 N Harbor Dr',
            'street2' => null,
            'city' => 'Redondo Beach',
            'state' => 'CA',
            'zip' => '90277',
            'country' => 'us',
            'name' => 'Dr. Steve Brule',
            'company' => null,
            'phone' => '310-808-5243',
            'email' => null,
            'residential' => null,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('returns a safe provider error response when the EasyPost service fails', function (): void {
    $this->mock(EasyPostService::class, function ($mock): void {
        $mock->shouldReceive('getRates')
            ->once()
            ->andThrow(new \RuntimeException(
                'EasyPost shipment rate estimation failed.',
                0,
                new EasyPostException('EasyPost API error.')
            ));
    });

    $this->postJson('/shipping/easypost/rates/estimate', [
        'from_address' => [
            'street1' => '118 2nd Street',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94105',
            'country' => 'US',
        ],
        'to_address' => [
            'street1' => '179 N Harbor Dr',
            'city' => 'Redondo Beach',
            'state' => 'CA',
            'zip' => '90277',
            'country' => 'US',
        ],
    ])
        ->assertStatus(502)
        ->assertExactJson([
            'success' => false,
            'message' => 'Unable to retrieve shipping rates from EasyPost.',
        ]);
});
