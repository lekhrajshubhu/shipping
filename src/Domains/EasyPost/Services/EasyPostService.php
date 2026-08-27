<?php

namespace Systha\Shipping\Domains\EasyPost\Services;

use EasyPost\EasyPostClient;
use EasyPost\Exception\Api\ApiException;
use EasyPost\Exception\General\EasyPostException;
use RuntimeException;
use Systha\Jwellery\Domains\Shared\Models\OrderShipment;
use Throwable;

class EasyPostService
{
    private ?EasyPostClient $client = null;

    /**
     * @return array<string, mixed>
     */
    public function getConfiguredRates(): array
    {
        $fromAddress = $this->getConfigArray('shipping.easypost.defaults.from_address');
        $toAddress = $this->getConfigArray('shipping.easypost.defaults.to_address');
        $parcel = $this->getConfigArray('shipping.easypost.defaults.parcel');

        return $this->getRates($fromAddress, $toAddress, $parcel);
    }

    /**
     * @param  array<string, mixed>  $fromAddress
     * @param  array<string, mixed>  $toAddress
     * @param  array<string, mixed>|null  $parcel
     * @return array<string, mixed>
     */
    public function getRates(array $fromAddress, array $toAddress, ?array $parcel = null): array
    {
        $apiKey = $this->getApiKey();
        $parcel = $parcel ?? $this->getConfigArray('shipping.easypost.defaults.parcel');
        $units = $this->getOptionalConfigArray('shipping.easypost.defaults.units');

        try {
            $shipment = $this->client($apiKey)->shipment->create([
                'from_address' => $fromAddress,
                'to_address' => $toAddress,
                'parcel' => $parcel,
            ]);
        } catch (EasyPostException | Throwable $exception) {
            throw new RuntimeException(
                'EasyPost shipment rate estimation failed.',
                0,
                $exception
            );
        }

        $rates = [];

        foreach (($shipment->rates ?? []) as $rate) {
            $rates[] = $this->normalizeRate($rate);
        }

        usort($rates, static function (array $left, array $right): int {
            $leftRate = is_numeric($left['rate']) ? (float) $left['rate'] : PHP_FLOAT_MAX;
            $rightRate = is_numeric($right['rate']) ? (float) $right['rate'] : PHP_FLOAT_MAX;

            return $leftRate <=> $rightRate;
        });

        return [
            'shipment_id' => $shipment->id ?? null,
            'parcel' => [
                'length' => $parcel['length'] ?? null,
                'width' => $parcel['width'] ?? null,
                'height' => $parcel['height'] ?? null,
                'weight' => $parcel['weight'] ?? null,
                'dimension_unit' => $units['dimension'] ?? null,
                'weight_unit' => $units['weight'] ?? null,
            ],
            'rates' => $rates,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generateLabel(string $shipmentId, string $rateId): array
    {
        $apiKey = $this->getApiKey();
        $shipment = $this->retrieveShipment($apiKey, $shipmentId);

        if ($this->isPurchasedShipment($shipment)) {
            $result = $this->normalizePurchasedShipment($shipment, $rateId);
            $this->syncPurchasedShipmentRecord($shipment, $result['selected_rate'] ?? null, $rateId);

            return $result;
        }

        if (! $this->shipmentHasRate($shipment, $rateId)) {
            throw new RuntimeException('Selected EasyPost rate does not belong to the shipment.');
        }

        try {
            $purchasedShipment = $this->client($apiKey)->shipment->buy($shipmentId, ['id' => $rateId]);
        } catch (EasyPostException | Throwable $exception) {
            throw new RuntimeException(
                'EasyPost shipment purchase failed.',
                0,
                $exception
            );
        }

        $result = $this->normalizePurchasedShipment($purchasedShipment, $rateId);
        $this->syncPurchasedShipmentRecord($purchasedShipment, $result['selected_rate'] ?? null, $rateId);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function refundShipment(string $shipmentId): array
    {
        $apiKey = $this->getApiKey();
        $shipment = $this->retrieveShipment($apiKey, $shipmentId);
        $refundStatus = strtolower((string) data_get($shipment, 'refund_status', ''));

        if (in_array($refundStatus, ['submitted', 'refunded'], true)) {
            $result = $this->normalizeRefundShipment($shipment, true);
            $this->syncRefundShipmentRecord($shipment, (string) ($result['refund_status'] ?? $refundStatus), $result['tracking_code'] ?? null);

            return $result;
        }

        if (in_array($refundStatus, ['rejected', 'not_applicable'], true)) {
            $result = $this->normalizeRefundShipment($shipment, false);
            $this->syncRefundShipmentRecord($shipment, (string) ($result['refund_status'] ?? $refundStatus), $result['tracking_code'] ?? null);

            return $result;
        }

        if (! $this->isPurchasedShipment($shipment)) {
            throw new RuntimeException('This EasyPost shipment does not have a purchased shipping label.');
        }

        try {
            $refundedShipment = $this->client($apiKey)->shipment->refund($shipmentId);
        } catch (ApiException | Throwable $exception) {
            if ($exception instanceof ApiException && (($exception->getHttpStatus() ?? null) === 404)) {
                throw new RuntimeException(
                    'EasyPost shipment not found.',
                    0,
                    $exception
                );
            }

            throw new RuntimeException(
                'Unable to refund EasyPost shipment.',
                0,
                $exception
            );
        }

        $result = $this->normalizeRefundShipment($refundedShipment, false);
        $this->syncRefundShipmentRecord($refundedShipment, (string) ($result['refund_status'] ?? 'submitted'), $result['tracking_code'] ?? null);

        return $result;
    }

    protected function client(string $apiKey): EasyPostClient
    {
        if ($this->client instanceof EasyPostClient) {
            return $this->client;
        }

        $this->client = new EasyPostClient($apiKey);

        return $this->client;
    }

    /**
     * @return mixed
     */
    private function retrieveShipment(string $apiKey, string $shipmentId): mixed
    {
        try {
            return $this->client($apiKey)->shipment->retrieve($shipmentId);
        } catch (ApiException $exception) {
            if (($exception->getHttpStatus() ?? null) === 404) {
                throw new RuntimeException(
                    'EasyPost shipment not found.',
                    0,
                    $exception
                );
            }

            throw new RuntimeException(
                'Unable to retrieve EasyPost shipment.',
                0,
                $exception
            );
        } catch (EasyPostException | Throwable $exception) {
            throw new RuntimeException(
                'Unable to retrieve EasyPost shipment.',
                0,
                $exception
            );
        }
    }

    private function getApiKey(): string
    {
        $apiKey = config('shipping.easypost.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('EasyPost API key is not configured.');
        }

        return trim($apiKey);
    }

    /**
     * @param  string  $key
     * @param  array<string, mixed>|null  $default
     * @return array<string, mixed>
     */
    private function getConfigArray(string $key, ?array $default = null): array
    {
        $value = config($key, $default ?? []);

        if (! is_array($value) || $value === []) {
            throw new RuntimeException(sprintf('EasyPost configuration [%s] is not configured.', $key));
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function getOptionalConfigArray(string $key): array
    {
        $value = config($key, []);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  mixed  $rate
     * @return array<string, mixed>
     */
    private function normalizeRate(mixed $rate): array
    {
        if (is_object($rate)) {
            return [
                'id' => $rate->id ?? null,
                'carrier' => $rate->carrier ?? null,
                'service' => $rate->service ?? null,
                'rate' => $rate->rate ?? null,
                'currency' => $rate->currency ?? null,
                'delivery_days' => $rate->delivery_days ?? null,
                'delivery_date' => $rate->delivery_date ?? null,
                'delivery_date_guaranteed' => $rate->delivery_date_guaranteed ?? null,
            ];
        }

        if (is_array($rate)) {
            return [
                'id' => $rate['id'] ?? null,
                'carrier' => $rate['carrier'] ?? null,
                'service' => $rate['service'] ?? null,
                'rate' => $rate['rate'] ?? null,
                'currency' => $rate['currency'] ?? null,
                'delivery_days' => $rate['delivery_days'] ?? null,
                'delivery_date' => $rate['delivery_date'] ?? null,
                'delivery_date_guaranteed' => $rate['delivery_date_guaranteed'] ?? null,
            ];
        }

        throw new RuntimeException('Unexpected EasyPost rate payload received.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeSelectedRate(mixed $rate): ?array
    {
        if ($rate === null) {
            return null;
        }

        $normalizedRate = $this->normalizeRate($rate);

        return [
            'rate_id' => $normalizedRate['id'] ?? null,
            'carrier' => $normalizedRate['carrier'] ?? null,
            'service' => $normalizedRate['service'] ?? null,
            'rate' => $normalizedRate['rate'] ?? null,
            'currency' => $normalizedRate['currency'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePostageLabel(mixed $label): array
    {
        return [
            'id' => data_get($label, 'id'),
            'url' => data_get($label, 'label_url'),
            'pdf_url' => data_get($label, 'label_pdf_url'),
            'zpl_url' => data_get($label, 'label_zpl_url'),
            'epl2_url' => data_get($label, 'label_epl2_url'),
            'file_type' => data_get($label, 'label_file_type'),
            'size' => data_get($label, 'label_size'),
        ];
    }

    private function shipmentHasRate(mixed $shipment, string $rateId): bool
    {
        foreach ((array) data_get($shipment, 'rates', []) as $rate) {
            if ((string) data_get($rate, 'id') === $rateId) {
                return true;
            }
        }

        return false;
    }

    private function isPurchasedShipment(mixed $shipment): bool
    {
        $status = strtolower((string) data_get($shipment, 'status', ''));

        return $status === 'purchased'
            || filled(data_get($shipment, 'tracking_code'))
            || filled(data_get($shipment, 'postage_label'));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeRefundShipment(mixed $shipment, bool $alreadyRequested): array
    {
        return [
            'shipment_id' => data_get($shipment, 'id'),
            'tracking_code' => data_get($shipment, 'tracking_code'),
            'refund_status' => $this->normalizeRefundStatus(data_get($shipment, 'refund_status')),
            'already_requested' => $alreadyRequested,
        ];
    }

    private function normalizeRefundStatus(mixed $refundStatus): ?string
    {
        if (! is_string($refundStatus) || trim($refundStatus) === '') {
            return null;
        }

        return strtolower(trim($refundStatus));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeShipmentAddress(mixed $address): array
    {
        return [
            'name' => data_get($address, 'name'),
            'company' => data_get($address, 'company'),
            'street1' => data_get($address, 'street1'),
            'street2' => data_get($address, 'street2'),
            'city' => data_get($address, 'city'),
            'state' => data_get($address, 'state'),
            'zip' => data_get($address, 'zip'),
            'country' => data_get($address, 'country'),
            'phone' => data_get($address, 'phone'),
            'email' => data_get($address, 'email'),
            'residential' => data_get($address, 'residential'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeShipmentParcel(mixed $parcel): array
    {
        return [
            'id' => data_get($parcel, 'id'),
            'length' => data_get($parcel, 'length'),
            'width' => data_get($parcel, 'width'),
            'height' => data_get($parcel, 'height'),
            'weight' => data_get($parcel, 'weight'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSelectedRateForShipment(mixed $shipment, ?string $rateId = null): ?array
    {
        $selectedRate = $this->normalizeSelectedRate(data_get($shipment, 'selected_rate'));

        if ($selectedRate !== null) {
            return $selectedRate;
        }

        if (! filled($rateId)) {
            return null;
        }

        foreach ((array) data_get($shipment, 'rates', []) as $rate) {
            if ((string) data_get($rate, 'id') !== $rateId) {
                continue;
            }

            return $this->normalizeSelectedRate($rate);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function persistOrderShipment(string $shipmentId, array $attributes): void
    {
        OrderShipment::query()->updateOrCreate(
            ['shipment_id' => $shipmentId],
            $attributes
        );
    }

    /**
     * @param  array<string, mixed>|null  $selectedRate
     */
    protected function syncSelectedShipmentRecord(mixed $shipment, ?array $selectedRate): void
    {
        $shipmentId = data_get($shipment, 'id');

        if (! is_string($shipmentId) || trim($shipmentId) === '') {
            return;
        }

        $this->persistOrderShipment($shipmentId, [
            'rate_id' => $selectedRate['rate_id'] ?? null,
            'carrier' => $selectedRate['carrier'] ?? null,
            'amount' => $selectedRate['rate'] ?? null,
            'parcel' => $this->normalizeShipmentParcel(data_get($shipment, 'parcel')),
            'from_address' => $this->normalizeShipmentAddress(data_get($shipment, 'from_address')),
            'to_address' => $this->normalizeShipmentAddress(data_get($shipment, 'to_address')),
            'shipment_status' => data_get($shipment, 'status'),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $selectedRate
     */
    protected function syncPurchasedShipmentRecord(mixed $shipment, ?array $selectedRate, ?string $fallbackRateId = null): void
    {
        $shipmentId = data_get($shipment, 'id');

        if (! is_string($shipmentId) || trim($shipmentId) === '') {
            return;
        }

        $resolvedRate = $selectedRate ?? $this->resolveSelectedRateForShipment($shipment, $fallbackRateId);

        $this->persistOrderShipment($shipmentId, [
            'rate_id' => $resolvedRate['rate_id'] ?? $fallbackRateId,
            'carrier' => $resolvedRate['carrier'] ?? null,
            'amount' => $resolvedRate['rate'] ?? null,
            'parcel' => $this->normalizeShipmentParcel(data_get($shipment, 'parcel')),
            'shipment_label' => $this->normalizePostageLabel(data_get($shipment, 'postage_label')),
            'tracking_number' => data_get($shipment, 'tracking_code'),
            'from_address' => $this->normalizeShipmentAddress(data_get($shipment, 'from_address')),
            'to_address' => $this->normalizeShipmentAddress(data_get($shipment, 'to_address')),
            'shipment_status' => data_get($shipment, 'status'),
        ]);
    }

    protected function syncRefundShipmentRecord(mixed $shipment, string $refundStatus, ?string $trackingCode = null): void
    {
        $shipmentId = data_get($shipment, 'id');

        if (! is_string($shipmentId) || trim($shipmentId) === '') {
            return;
        }

        $existing = OrderShipment::query()->where('shipment_id', $shipmentId)->first();
        $now = now();
        $refundRequestedAt = $existing?->refund_requested_at;
        $refundedAt = $existing?->refunded_at;

        if (in_array($refundStatus, ['submitted', 'refunded'], true) && ! $refundRequestedAt) {
            $refundRequestedAt = $now;
        }

        if ($refundStatus === 'refunded' && ! $refundedAt) {
            $refundedAt = $now;
        }

        $selectedRate = $this->resolveSelectedRateForShipment($shipment, $existing?->rate_id);

        $this->persistOrderShipment($shipmentId, [
            'rate_id' => $existing?->rate_id ?? $selectedRate['rate_id'] ?? null,
            'carrier' => $existing?->carrier ?? $selectedRate['carrier'] ?? null,
            'amount' => $existing?->amount ?? $selectedRate['rate'] ?? null,
            'parcel' => $existing?->parcel ?? $this->normalizeShipmentParcel(data_get($shipment, 'parcel')),
            'shipment_label' => $existing?->shipment_label ?? $this->normalizePostageLabel(data_get($shipment, 'postage_label')),
            'tracking_number' => filled($trackingCode)
                ? $trackingCode
                : ($existing?->tracking_number ?? data_get($shipment, 'tracking_code')),
            'from_address' => $existing?->from_address ?? $this->normalizeShipmentAddress(data_get($shipment, 'from_address')),
            'to_address' => $existing?->to_address ?? $this->normalizeShipmentAddress(data_get($shipment, 'to_address')),
            'shipment_status' => $existing?->shipment_status ?? data_get($shipment, 'status'),
            'refund_status' => $refundStatus,
            'refund_requested_at' => $refundRequestedAt,
            'refunded_at' => $refundedAt,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePurchasedShipment(mixed $shipment, ?string $rateId = null): array
    {
        $selectedRate = $this->normalizeSelectedRate(data_get($shipment, 'selected_rate'));

        if ($selectedRate === null && filled($rateId)) {
            foreach ((array) data_get($shipment, 'rates', []) as $rate) {
                if ((string) data_get($rate, 'id') !== $rateId) {
                    continue;
                }

                $selectedRate = $this->normalizeSelectedRate($rate);
                break;
            }
        }

        return [
            'shipment_id' => data_get($shipment, 'id'),
            'selected_rate' => $selectedRate,
            'tracking_code' => data_get($shipment, 'tracking_code'),
            'label' => $this->normalizePostageLabel(data_get($shipment, 'postage_label')),
        ];
    }
    public function getSelectedRate(
        string $shipmentId,
        string $rateId
    ): array {
        $apiKey = $this->getApiKey();
        $client = $this->client($apiKey);

        $shipment = $client->shipment->retrieve($shipmentId);

        $selectedRate = null;

        foreach ((array) data_get($shipment, 'rates', []) as $rate) {
            if ((string) data_get($rate, 'id') !== $rateId) {
                continue;
            }

            $selectedRate = $this->normalizeSelectedRate($rate);
            break;
        }

        if (! $selectedRate) {
            throw new \RuntimeException(
                'Selected EasyPost rate does not belong to the shipment.'
            );
        }

        $this->syncSelectedShipmentRecord($shipment, $selectedRate);

        return [
            'shipment' => [
                'id' => data_get($shipment, 'id'),

                'from_address' => [
                    'street1' => data_get($shipment, 'from_address.street1'),
                    'street2' => data_get($shipment, 'from_address.street2'),
                    'city' => data_get($shipment, 'from_address.city'),
                    'state' => data_get($shipment, 'from_address.state'),
                    'zip' => data_get($shipment, 'from_address.zip'),
                    'country' => data_get($shipment, 'from_address.country'),
                ],

                'to_address' => [
                    'street1' => data_get($shipment, 'to_address.street1'),
                    'street2' => data_get($shipment, 'to_address.street2'),
                    'city' => data_get($shipment, 'to_address.city'),
                    'state' => data_get($shipment, 'to_address.state'),
                    'zip' => data_get($shipment, 'to_address.zip'),
                    'country' => data_get($shipment, 'to_address.country'),
                ],

                'parcel' => [
                    'length' => data_get($shipment, 'parcel.length'),
                    'width' => data_get($shipment, 'parcel.width'),
                    'height' => data_get($shipment, 'parcel.height'),
                    'weight' => data_get($shipment, 'parcel.weight'),
                ],

                'tracking_code' => data_get($shipment, 'tracking_code'),

                'postage_label' => [
                    'url' => data_get($shipment, 'postage_label.label_url'),
                    'pdf_url' => data_get($shipment, 'postage_label.label_pdf_url'),
                ],
            ],

            'selected_rate' => [
                'id' => $selectedRate['rate_id'] ?? null,
                'rate_id' => $selectedRate['rate_id'] ?? null,
                'carrier' => $selectedRate['carrier'] ?? null,
                'service' => $selectedRate['service'] ?? null,
                'rate' => $selectedRate['rate'] ?? null,
                'currency' => $selectedRate['currency'] ?? null,
                'delivery_days' => $selectedRate['delivery_days'] ?? null,
                'delivery_date' => $selectedRate['delivery_date'] ?? null,
                'delivery_date_guaranteed' => $selectedRate['delivery_date_guaranteed'] ?? false,
            ],
        ];
    }

    public function getShipmentRates(string $shipmentId): array
    {
        try {
            $apiKey = $this->getApiKey();

              $client = $this->client($apiKey);

            $shipment = $client->shipment->retrieve($shipmentId);

            $rates = collect($shipment->rates ?? [])
                ->map(function ($rate) {
                    return [
                        'id' => $rate->id ?? null,
                        'carrier' => $rate->carrier ?? null,
                        'service' => $rate->service ?? null,
                        'rate' => $rate->rate ?? null,
                        'currency' => $rate->currency ?? null,
                        'delivery_days' => $rate->delivery_days ?? null,
                        'delivery_date' => $rate->delivery_date ?? null,
                        'delivery_date_guaranteed' =>
                        $rate->delivery_date_guaranteed ?? false,
                    ];
                })
                ->sortBy(
                    fn(array $rate) =>
                    is_numeric($rate['rate'] ?? null)
                        ? (float) $rate['rate']
                        : PHP_FLOAT_MAX
                )
                ->values()
                ->all();

            return [
                'shipment_id' => $shipment->id ?? null,
                'status' => $shipment->status ?? null,
                'mode' => $shipment->mode ?? null,
                'tracking_code' => $shipment->tracking_code ?? null,

                'from_address' => [
                    'name' => $shipment->from_address->name ?? null,
                    'company' => $shipment->from_address->company ?? null,
                    'street1' => $shipment->from_address->street1 ?? null,
                    'street2' => $shipment->from_address->street2 ?? null,
                    'city' => $shipment->from_address->city ?? null,
                    'state' => $shipment->from_address->state ?? null,
                    'zip' => $shipment->from_address->zip ?? null,
                    'country' => $shipment->from_address->country ?? null,
                    'phone' => $shipment->from_address->phone ?? null,
                    'email' => $shipment->from_address->email ?? null,
                    'residential' => $shipment->from_address->residential ?? null,
                ],

                'to_address' => [
                    'name' => $shipment->to_address->name ?? null,
                    'company' => $shipment->to_address->company ?? null,
                    'street1' => $shipment->to_address->street1 ?? null,
                    'street2' => $shipment->to_address->street2 ?? null,
                    'city' => $shipment->to_address->city ?? null,
                    'state' => $shipment->to_address->state ?? null,
                    'zip' => $shipment->to_address->zip ?? null,
                    'country' => $shipment->to_address->country ?? null,
                    'phone' => $shipment->to_address->phone ?? null,
                    'email' => $shipment->to_address->email ?? null,
                    'residential' => $shipment->to_address->residential ?? null,
                ],

                'parcel' => [
                    'id' => $shipment->parcel->id ?? null,
                    'length' => $shipment->parcel->length ?? null,
                    'width' => $shipment->parcel->width ?? null,
                    'height' => $shipment->parcel->height ?? null,
                    'weight' => $shipment->parcel->weight ?? null,
                ],

                'rates' => $rates,
            ];
        } catch (\Throwable $e) {
            report($e);

            throw new \RuntimeException(
                'EasyPost error: ' . $e->getMessage(),
                previous: $e
            );
        }
    }
}
