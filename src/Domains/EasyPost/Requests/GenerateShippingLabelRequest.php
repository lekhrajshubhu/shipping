<?php

namespace Systha\Shipping\Domains\EasyPost\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateShippingLabelRequest extends FormRequest
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
            'shipment_id' => ['required', 'string'],
            'rate_id' => ['required', 'string'],
        ];
    }
}
