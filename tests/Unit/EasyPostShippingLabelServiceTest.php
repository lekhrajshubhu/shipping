<?php

uses(Tests\TestCase::class);

use EasyPost\Exception\General\EasyPostException;
use EasyPost\EasyPostClient;
use Systha\Shipping\Domains\EasyPost\Services\EasyPostService;

afterEach(function (): void {
    \Mockery::close();
});

function easypost_label_object(array $data): object
{
    return json_decode(json_encode($data, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
}

function easypost_service_with_shipment_service(object $shipmentService): EasyPostService
{
    return new class($shipmentService) extends EasyPostService {
        public function __construct(
            private readonly object $shipmentService
        ) {
        }

        protected function client(string $apiKey): EasyPostClient
        {
            return new class($this->shipmentService) extends \EasyPost\EasyPostClient {
                public function __construct(
                    private readonly object $shipmentService
                ) {
                    parent::__construct('sk_test_fake');
                }

                public function __get(string $serviceName)
                {
                    if ($serviceName === 'shipment') {
                        return $this->shipmentService;
                    }

                    return parent::__get($serviceName);
                }
            };
        }
    };
}

it('returns an already purchased shipment without buying again', function (): void {
    config()->set('shipping.easypost.api_key', 'sk_test_fake');

    $shipment = easypost_label_object([
        'id' => 'shp_test_123',
        'status' => 'purchased',
        'tracking_code' => '1ZPURCHASED123',
        'selected_rate' => [
            'id' => 'rate_test_123',
            'carrier' => 'UPS',
            'service' => 'Ground',
            'rate' => '12.95',
            'currency' => 'USD',
        ],
        'postage_label' => [
            'id' => 'pl_test_123',
            'label_url' => 'https://example.test/label.png',
            'label_pdf_url' => 'https://example.test/label.pdf',
            'label_zpl_url' => null,
            'label_epl2_url' => null,
            'label_file_type' => 'image/png',
            'label_size' => '4x6',
        ],
        'rates' => [
            [
                'id' => 'rate_test_123',
                'carrier' => 'UPS',
                'service' => 'Ground',
                'rate' => '12.95',
                'currency' => 'USD',
            ],
        ],
    ]);

    $shipmentService = \Mockery::mock();
    $shipmentService->shouldReceive('retrieve')
        ->once()
        ->with('shp_test_123')
        ->andReturn($shipment);
    $shipmentService->shouldNotReceive('buy');

    $result = easypost_service_with_shipment_service($shipmentService)->generateLabel('shp_test_123', 'rate_test_123');

    expect($result)->toMatchArray([
        'shipment_id' => 'shp_test_123',
        'selected_rate' => [
            'rate_id' => 'rate_test_123',
            'carrier' => 'UPS',
            'service' => 'Ground',
            'rate' => '12.95',
            'currency' => 'USD',
        ],
        'tracking_code' => '1ZPURCHASED123',
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

it('rejects a selected rate that does not belong to the shipment', function (): void {
    config()->set('shipping.easypost.api_key', 'sk_test_fake');

    $shipment = easypost_label_object([
        'id' => 'shp_test_123',
        'status' => 'created',
        'rates' => [
            [
                'id' => 'rate_other_123',
                'carrier' => 'USPS',
                'service' => 'GroundAdvantage',
                'rate' => '6.25',
                'currency' => 'USD',
            ],
        ],
    ]);

    $shipmentService = \Mockery::mock();
    $shipmentService->shouldReceive('retrieve')
        ->once()
        ->with('shp_test_123')
        ->andReturn($shipment);
    $shipmentService->shouldNotReceive('buy');

    expect(fn () => easypost_service_with_shipment_service($shipmentService)->generateLabel('shp_test_123', 'rate_test_123'))
        ->toThrow(RuntimeException::class, 'Selected EasyPost rate does not belong to the shipment.');
});

it('purchases the selected rate and returns normalized label data', function (): void {
    config()->set('shipping.easypost.api_key', 'sk_test_fake');

    $retrievedShipment = easypost_label_object([
        'id' => 'shp_test_123',
        'status' => 'created',
        'rates' => [
            [
                'id' => 'rate_test_123',
                'carrier' => 'UPS',
                'service' => 'Ground',
                'rate' => '12.95',
                'currency' => 'USD',
            ],
        ],
    ]);

    $purchasedShipment = easypost_label_object([
        'id' => 'shp_test_123',
        'status' => 'purchased',
        'tracking_code' => '1ZTEST123',
        'selected_rate' => [
            'id' => 'rate_test_123',
            'carrier' => 'UPS',
            'service' => 'Ground',
            'rate' => '12.95',
            'currency' => 'USD',
        ],
        'postage_label' => [
            'id' => 'pl_test_123',
            'label_url' => 'https://example.test/label.png',
            'label_pdf_url' => 'https://example.test/label.pdf',
            'label_zpl_url' => null,
            'label_epl2_url' => null,
            'label_file_type' => 'image/png',
            'label_size' => '4x6',
        ],
        'rates' => [
            [
                'id' => 'rate_test_123',
                'carrier' => 'UPS',
                'service' => 'Ground',
                'rate' => '12.95',
                'currency' => 'USD',
            ],
        ],
    ]);

    $shipmentService = \Mockery::mock();
    $shipmentService->shouldReceive('retrieve')
        ->once()
        ->with('shp_test_123')
        ->andReturn($retrievedShipment);
    $shipmentService->shouldReceive('buy')
        ->once()
        ->with('shp_test_123', ['id' => 'rate_test_123'])
        ->andReturn($purchasedShipment);

    $result = easypost_service_with_shipment_service($shipmentService)->generateLabel('shp_test_123', 'rate_test_123');

    expect($result)->toMatchArray([
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

it('surfaces provider failures as runtime exceptions', function (): void {
    config()->set('shipping.easypost.api_key', 'sk_test_fake');

    $shipment = easypost_label_object([
        'id' => 'shp_test_123',
        'status' => 'created',
        'rates' => [
            [
                'id' => 'rate_test_123',
                'carrier' => 'UPS',
                'service' => 'Ground',
                'rate' => '12.95',
                'currency' => 'USD',
            ],
        ],
    ]);

    $shipmentService = \Mockery::mock();
    $shipmentService->shouldReceive('retrieve')
        ->once()
        ->with('shp_test_123')
        ->andReturn($shipment);
    $shipmentService->shouldReceive('buy')
        ->once()
        ->with('shp_test_123', ['id' => 'rate_test_123'])
        ->andThrow(new EasyPostException('EasyPost API error.'));

    expect(fn () => easypost_service_with_shipment_service($shipmentService)->generateLabel('shp_test_123', 'rate_test_123'))
        ->toThrow(RuntimeException::class, 'EasyPost shipment purchase failed.');
});
