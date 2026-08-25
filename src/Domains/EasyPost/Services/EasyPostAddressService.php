<?php

namespace Systha\Shipping\Domains\EasyPost\Services;

use EasyPost\EasyPostClient;
use EasyPost\Exception\General\EasyPostException;
use RuntimeException;
use Throwable;

class EasyPostAddressService
{
    private ?EasyPostClient $client = null;

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    public function verifyAddress(array $address): array
    {
        $apiKey = $this->getApiKey();

        try {
            $verifiedAddress = $this->client($apiKey)->address->create([
                ...$address,
                'verify' => true,
            ]);
        } catch (EasyPostException|Throwable $exception) {
            throw new RuntimeException(
                'EasyPost address verification failed.',
                0,
                $exception
            );
        }

        return $this->normalizeAddress($verifiedAddress);
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
     * @param  mixed  $address
     * @return array<string, mixed>
     */
    private function normalizeAddress(mixed $address): array
    {
        return [
            'id' => $address->id ?? null,
            'street1' => $address->street1 ?? null,
            'street2' => $address->street2 ?? null,
            'city' => $address->city ?? null,
            'state' => $address->state ?? null,
            'zip' => $address->zip ?? null,
            'country' => $address->country ?? null,
            'name' => $address->name ?? null,
            'company' => $address->company ?? null,
            'phone' => $address->phone ?? null,
            'email' => $address->email ?? null,
            'residential' => $address->residential ?? null,
            'verified' => $this->deliveryVerified($address->verifications ?? null),
            'verifications' => $this->normalizeVerifications($address->verifications ?? null),
        ];
    }

    /**
     * @param  mixed  $verifications
     * @return array<string, mixed>
     */
    private function normalizeVerifications(mixed $verifications): array
    {
        if (! is_object($verifications)) {
            return [];
        }

        return [
            'delivery' => $this->normalizeVerification($verifications->delivery ?? null),
            'zip4' => $this->normalizeVerification($verifications->zip4 ?? null),
        ];
    }

    /**
     * @param  mixed  $verification
     * @return array<string, mixed>
     */
    private function normalizeVerification(mixed $verification): array
    {
        if (! is_object($verification)) {
            return [
                'success' => null,
                'errors' => [],
                'details' => null,
            ];
        }

        return [
            'success' => $verification->success ?? null,
            'errors' => $this->normalizeErrors($verification->errors ?? null),
            'details' => $this->normalizeDetails($verification->details ?? null),
        ];
    }

    /**
     * @param  mixed  $errors
     * @return array<int, array<string, mixed>>
     */
    private function normalizeErrors(mixed $errors): array
    {
        if (! is_iterable($errors)) {
            return [];
        }

        $normalized = [];

        foreach ($errors as $error) {
            if (! is_object($error) && ! is_array($error)) {
                continue;
            }

            $normalized[] = [
                'code' => is_array($error) ? ($error['code'] ?? null) : ($error->code ?? null),
                'field' => is_array($error) ? ($error['field'] ?? null) : ($error->field ?? null),
                'message' => is_array($error) ? ($error['message'] ?? null) : ($error->message ?? null),
                'suggestion' => is_array($error) ? ($error['suggestion'] ?? null) : ($error->suggestion ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @param  mixed  $details
     * @return array<string, mixed>
     */
    private function normalizeDetails(mixed $details): array
    {
        if (! is_object($details)) {
            return [];
        }

        return [
            'latitude' => $details->latitude ?? null,
            'longitude' => $details->longitude ?? null,
            'time_zone' => $details->time_zone ?? null,
        ];
    }

    private function deliveryVerified(mixed $verifications): ?bool
    {
        if (! is_object($verifications) || ! is_object($verifications->delivery ?? null)) {
            return null;
        }

        return $verifications->delivery->success ?? null;
    }
}
