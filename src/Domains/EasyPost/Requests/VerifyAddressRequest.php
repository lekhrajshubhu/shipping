<?php

namespace Systha\Shipping\Domains\EasyPost\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyAddressRequest extends FormRequest
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
            'street1' => ['required', 'string'],
            'street2' => ['nullable', 'string'],
            'city' => ['required', 'string'],
            'state' => ['required', 'string'],
            'zip' => ['required', 'string'],
            'country' => ['required', 'string', 'size:2'],
            
            'name' => ['nullable', 'string'],
            'company' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'residential' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedInput());
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedInput(): array
    {
        $normalized = $this->all();

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
