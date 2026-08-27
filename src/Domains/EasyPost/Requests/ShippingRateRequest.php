<?php

namespace Systha\Shipping\Domains\EasyPost\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShippingRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from_address' => ['required', 'array'],
            'from_address.street1' => ['required', 'string'],
            'from_address.street2' => ['nullable', 'string'],
            'from_address.city' => ['required', 'string'],
            'from_address.state' => ['required', 'string'],
            'from_address.zip' => ['required', 'string'],
            'from_address.country' => ['required', 'string', 'size:2'],
            'from_address.name' => ['nullable', 'string'],
            'from_address.company' => ['nullable', 'string'],
            'from_address.phone' => ['nullable', 'string'],
            'from_address.email' => ['nullable', 'email'],
            'from_address.residential' => ['nullable', 'boolean'],

            'to_address' => ['required', 'array'],
            'to_address.street1' => ['required', 'string'],
            'to_address.street2' => ['nullable', 'string'],
            'to_address.city' => ['required', 'string'],
            'to_address.state' => ['required', 'string'],
            'to_address.zip' => ['required', 'string'],
            'to_address.country' => ['required', 'string', 'size:2'],
            'to_address.name' => ['nullable', 'string'],
            'to_address.company' => ['nullable', 'string'],
            'to_address.phone' => ['nullable', 'string'],
            'to_address.email' => ['nullable', 'email'],
            'to_address.residential' => ['nullable', 'boolean'],

            // parcel
            'parcel' => ['nullable', 'array'],
            'parcel.length' => ['nullable', 'numeric'],
            'parcel.width' => ['nullable', 'numeric'],
            'parcel.height' => ['nullable', 'numeric'],
            'parcel.weight' => ['nullable', 'numeric'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from_address' => $this->normalizeAddress($this->input('from_address', [])),
            'to_address' => $this->normalizeAddress($this->input('to_address', [])),
        ]);
    }

    /**
     * @param  mixed  $address
     * @return array<string, mixed>
     */
    private function normalizeAddress(mixed $address): array
    {
        $normalized = is_array($address) ? $address : [];

        foreach ([
            'street1',
            'street2',
            'city',
            'state',
            'zip',
            'country',
            'name',
            'company',
            'phone',
            'email',
        ] as $field) {
            if (! array_key_exists($field, $normalized) || ! is_string($normalized[$field])) {
                continue;
            }

            $normalized[$field] = trim($normalized[$field]);
        }

        if (isset($normalized['country']) && is_string($normalized['country'])) {
            $normalized['country'] = strtoupper($normalized['country']);
        }

        return $normalized;
    }
}
