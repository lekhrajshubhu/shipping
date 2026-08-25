<?php

uses(Tests\TestCase::class);

use EasyPost\Exception\General\EasyPostException;
use Systha\Shipping\Domains\EasyPost\Services\EasyPostAddressService;

it('verifies address data through the EasyPost endpoint', function (): void {
    $this->mock(EasyPostAddressService::class, function ($mock): void {
        $mock->shouldReceive('verifyAddress')
            ->once()
            ->andReturn([
                'id' => 'adr_test_123',
                'street1' => '417 MONTGOMERY ST',
                'street2' => 'FLOOR 5',
                'city' => 'SAN FRANCISCO',
                'state' => 'CA',
                'zip' => '94104',
                'country' => 'US',
                'name' => 'JOHN DOE',
                'company' => null,
                'phone' => '4151234567',
                'email' => 'john@example.com',
                'residential' => false,
                'verified' => true,
                'verifications' => [
                    'delivery' => [
                        'success' => true,
                        'errors' => [],
                        'details' => [
                            'latitude' => 37.7937,
                            'longitude' => -122.4011,
                            'time_zone' => 'America/Los_Angeles',
                        ],
                    ],
                    'zip4' => [
                        'success' => true,
                        'errors' => [],
                        'details' => null,
                    ],
                ],
            ]);
    });

    $this->postJson('/shipping/easypost/addresses/verify', [
        'street1' => '417 Montgomery St',
        'street2' => 'Floor 5',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '94104',
        'country' => 'US',
        'name' => 'John Doe',
        'company' => null,
        'phone' => '4151234567',
        'email' => 'john@example.com',
        'residential' => false,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', 'adr_test_123')
        ->assertJsonPath('data.street1', '417 MONTGOMERY ST')
        ->assertJsonPath('data.street2', 'FLOOR 5')
        ->assertJsonPath('data.city', 'SAN FRANCISCO')
        ->assertJsonPath('data.state', 'CA')
        ->assertJsonPath('data.zip', '94104')
        ->assertJsonPath('data.country', 'US')
        ->assertJsonPath('data.name', 'JOHN DOE')
        ->assertJsonPath('data.residential', false)
        ->assertJsonPath('data.verified', true)
        ->assertJsonPath('data.verifications.delivery.success', true)
        ->assertJsonPath('data.verifications.delivery.errors', [])
        ->assertJsonPath('data.verifications.delivery.details.latitude', 37.7937)
        ->assertJsonPath('data.verifications.delivery.details.longitude', -122.4011)
        ->assertJsonPath('data.verifications.delivery.details.time_zone', 'America/Los_Angeles')
        ->assertJsonPath('data.verifications.zip4.success', true)
        ->assertJsonPath('data.verifications.zip4.errors', [])
        ->assertJsonPath('data.verifications.zip4.details', null);
});

it('returns unverified address results without leaking raw SDK objects', function (): void {
    $this->mock(EasyPostAddressService::class, function ($mock): void {
        $mock->shouldReceive('verifyAddress')
            ->once()
            ->andReturn([
                'id' => 'adr_test_unverified',
                'street1' => '417 MONTGOMERY ST',
                'street2' => null,
                'city' => 'SAN FRANCISCO',
                'state' => 'CA',
                'zip' => '94104',
                'country' => 'US',
                'name' => 'JOHN DOE',
                'company' => null,
                'phone' => '4151234567',
                'email' => 'john@example.com',
                'residential' => false,
                'verified' => false,
                'verifications' => [
                    'delivery' => [
                        'success' => false,
                        'errors' => [
                            [
                                'code' => 'address.not_found',
                                'field' => 'street1',
                                'message' => 'Address could not be verified.',
                                'suggestion' => 'Check the street address.',
                            ],
                        ],
                        'details' => null,
                    ],
                    'zip4' => [
                        'success' => true,
                        'errors' => [],
                        'details' => null,
                    ],
                ],
            ]);
    });

    $this->postJson('/shipping/easypost/addresses/verify', [
        'street1' => '417 Montgomery St',
        'street2' => 'Floor 5',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '94104',
        'country' => 'US',
        'name' => 'John Doe',
        'company' => null,
        'phone' => '4151234567',
        'email' => 'john@example.com',
        'residential' => false,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', 'adr_test_unverified')
        ->assertJsonPath('data.verified', false)
        ->assertJsonPath('data.verifications.delivery.success', false)
        ->assertJsonPath('data.verifications.delivery.errors.0.code', 'address.not_found')
        ->assertJsonPath('data.verifications.delivery.errors.0.field', 'street1')
        ->assertJsonPath('data.verifications.delivery.errors.0.message', 'Address could not be verified.')
        ->assertJsonPath('data.verifications.delivery.errors.0.suggestion', 'Check the street address.')
        ->assertJsonPath('data.verifications.zip4.success', true)
        ->assertJsonCount(1, 'data.verifications.delivery.errors');
});

it('normalizes lowercase country codes through the address verification endpoint', function (): void {
    $this->mock(EasyPostAddressService::class, function ($mock): void {
        $mock->shouldReceive('verifyAddress')
            ->once()
            ->with([
                'street1' => '417 Montgomery St',
                'street2' => 'Floor 5',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94104',
                'country' => 'US',
                'name' => 'John Doe',
                'company' => null,
                'phone' => '4151234567',
                'email' => 'john@example.com',
                'residential' => false,
            ])
            ->andReturn([
                'id' => 'adr_test_123',
                'street1' => '417 MONTGOMERY ST',
                'street2' => 'FLOOR 5',
                'city' => 'SAN FRANCISCO',
                'state' => 'CA',
                'zip' => '94104',
                'country' => 'US',
                'name' => 'JOHN DOE',
                'company' => null,
                'phone' => '4151234567',
                'email' => 'john@example.com',
                'residential' => false,
                'verified' => true,
                'verifications' => [
                    'delivery' => [
                        'success' => true,
                        'errors' => [],
                        'details' => null,
                    ],
                    'zip4' => [
                        'success' => true,
                        'errors' => [],
                        'details' => null,
                    ],
                ],
            ]);
    });

    $this->postJson('/shipping/easypost/addresses/verify', [
        'street1' => '417 Montgomery St',
        'street2' => 'Floor 5',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '94104',
        'country' => 'us',
        'name' => 'John Doe',
        'company' => null,
        'phone' => '4151234567',
        'email' => 'john@example.com',
        'residential' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.country', 'US')
        ->assertJsonPath('data.verified', true);
});

it('validates required address fields on the verification endpoint', function (array $payload, string $errorKey): void {
    $this->postJson('/shipping/easypost/addresses/verify', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$errorKey]);
})->with([
    'missing street1' => [
        [
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94104',
            'country' => 'US',
        ],
        'street1',
    ],
    'missing city' => [
        [
            'street1' => '417 Montgomery St',
            'state' => 'CA',
            'zip' => '94104',
            'country' => 'US',
        ],
        'city',
    ],
    'missing state' => [
        [
            'street1' => '417 Montgomery St',
            'city' => 'San Francisco',
            'zip' => '94104',
            'country' => 'US',
        ],
        'state',
    ],
    'missing zip' => [
        [
            'street1' => '417 Montgomery St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'country' => 'US',
        ],
        'zip',
    ],
    'missing country' => [
        [
            'street1' => '417 Montgomery St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94104',
        ],
        'country',
    ],
    'invalid country length' => [
        [
            'street1' => '417 Montgomery St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94104',
            'country' => 'USA',
        ],
        'country',
    ],
]);

it('returns a safe provider error when address verification fails', function (): void {
    $this->mock(EasyPostAddressService::class, function ($mock): void {
        $mock->shouldReceive('verifyAddress')
            ->once()
            ->andThrow(new RuntimeException(
                'EasyPost address verification failed.',
                0,
                new EasyPostException('EasyPost API error.')
            ));
    });

    $this->postJson('/shipping/easypost/addresses/verify', [
        'street1' => '417 Montgomery St',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '94104',
        'country' => 'US',
    ])
        ->assertStatus(502)
        ->assertExactJson([
            'success' => false,
            'message' => 'Unable to verify address with EasyPost.',
        ]);
});
