<?php

namespace Systha\Shipping\Domains\EasyPost\Services;

use EasyPost\EasyPostClient;
use EasyPost\Exception\General\EasyPostException;
use RuntimeException;
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
        } catch (EasyPostException|Throwable $exception) {
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

    private function client(string $apiKey): EasyPostClient
    {
        if ($this->client instanceof EasyPostClient) {
            return $this->client;
        }

        $this->client = new EasyPostClient($apiKey);

        return $this->client;
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
}
