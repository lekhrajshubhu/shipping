<?php

uses(Tests\TestCase::class);

use EasyPost\Exception\General\EasyPostException;
use Systha\Shipping\Domains\EasyPost\Services\EasyPostService;

it('validates shipment and rate ids for label generation', function (array $payload, string $errorKey): void {
    $this->postJson('/shipping/easypost/labels/generate', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$errorKey]);
})->with([
    'missing shipment id' => [
        [
            'rate_id' => 'rate_test_123',
        ],
        'shipment_id',
    ],
    'missing rate id' => [
        [
            'shipment_id' => 'shp_test_123',
        ],
        'rate_id',
    ],
]);

it('returns a normalized shipping label payload', function (): void {
    $this->mock(EasyPostService::class, function ($mock): void {
        $mock->shouldReceive('generateLabel')
            ->once()
            ->with('shp_test_123', 'rate_test_123')
            ->andReturn([
                'shipment_id' => 'shp_test_123',
                'selected_rate' => [
                    'rate_id' => 'rate_test_123',
                    'carrier' => 'UPS',
                    'service' => 'Ground',
                    'rate' => '12.95',
                    'currency' => 'USD',
                ],
                'tracking_code' => '1ZTEST123',
                'label' => [
                    'id' => 'pl_test_123',
                    'url' => 'https://example.test/label.png',
                    'pdf_url' => 'https://example.test/label.pdf',
                    'zpl_url' => null,
                    'epl2_url' => null,
                    'file_type' => 'image/png',
                    'size' => '4x6',
                ],
            ]);
    });

    $this->postJson('/shipping/easypost/labels/generate', [
        'shipment_id' => 'shp_test_123',
        'rate_id' => 'rate_test_123',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.shipment_id', 'shp_test_123')
        ->assertJsonPath('data.selected_rate.rate_id', 'rate_test_123')
        ->assertJsonPath('data.selected_rate.carrier', 'UPS')
        ->assertJsonPath('data.selected_rate.service', 'Ground')
        ->assertJsonPath('data.selected_rate.rate', '12.95')
        ->assertJsonPath('data.selected_rate.currency', 'USD')
        ->assertJsonPath('data.tracking_code', '1ZTEST123')
        ->assertJsonPath('data.label.id', 'pl_test_123')
        ->assertJsonPath('data.label.url', 'https://example.test/label.png')
        ->assertJsonPath('data.label.pdf_url', 'https://example.test/label.pdf')
        ->assertJsonPath('data.label.zpl_url', null)
        ->assertJsonPath('data.label.epl2_url', null)
        ->assertJsonPath('data.label.file_type', 'image/png')
        ->assertJsonPath('data.label.size', '4x6');
});

it('returns a safe error response without leaking secrets', function (): void {
    config()->set('shipping.easypost.api_key', 'sk_test_secret_value');

    $this->mock(EasyPostService::class, function ($mock): void {
        $mock->shouldReceive('generateLabel')
            ->once()
            ->andThrow(new \RuntimeException(
                'EasyPost shipment purchase failed.',
                0,
                new EasyPostException('EasyPost API error.')
            ));
    });

    $this->postJson('/shipping/easypost/labels/generate', [
        'shipment_id' => 'shp_test_123',
        'rate_id' => 'rate_test_123',
    ])
        ->assertStatus(502)
        ->assertExactJson([
            'success' => false,
            'message' => 'Unable to generate shipping label with EasyPost.',
        ])
        ->assertDontSee('sk_test_secret_value');
});
