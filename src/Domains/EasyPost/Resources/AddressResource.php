<?php

namespace Systha\Shipping\Domains\EasyPost\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource['id'] ?? null,
            'street1' => $this->resource['street1'] ?? null,
            'street2' => $this->resource['street2'] ?? null,
            'city' => $this->resource['city'] ?? null,
            'state' => $this->resource['state'] ?? null,
            'zip' => $this->resource['zip'] ?? null,
            'country' => $this->resource['country'] ?? null,
            'name' => $this->resource['name'] ?? null,
            'company' => $this->resource['company'] ?? null,
            'phone' => $this->resource['phone'] ?? null,
            'email' => $this->resource['email'] ?? null,
            'residential' => $this->resource['residential'] ?? null,
            'verified' => $this->resource['verified'] ?? null,
            'verifications' => $this->normalizeVerifications($this->resource['verifications'] ?? null),
        ];
    }

    /**
     * @param  mixed  $verifications
     * @return array<string, mixed>
     */
    private function normalizeVerifications(mixed $verifications): array
    {
        if (! is_array($verifications)) {
            return [
                'delivery' => [
                    'success' => null,
                    'errors' => [],
                    'details' => null,
                ],
                'zip4' => [
                    'success' => null,
                    'errors' => [],
                    'details' => null,
                ],
            ];
        }

        return [
            'delivery' => $this->normalizeVerification($verifications['delivery'] ?? null),
            'zip4' => $this->normalizeVerification($verifications['zip4'] ?? null),
        ];
    }

    /**
     * @param  mixed  $verification
     * @return array<string, mixed>
     */
    private function normalizeVerification(mixed $verification): array
    {
        if (! is_array($verification)) {
            return [
                'success' => null,
                'errors' => [],
                'details' => null,
            ];
        }

        return [
            'success' => $verification['success'] ?? null,
            'errors' => is_array($verification['errors'] ?? null) ? $verification['errors'] : [],
            'details' => $verification['details'] ?? null,
        ];
    }
}
