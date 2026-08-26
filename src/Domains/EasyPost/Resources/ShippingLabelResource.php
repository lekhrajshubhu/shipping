<?php

namespace Systha\Shipping\Domains\EasyPost\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingLabelResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'shipment_id' => $this->resource['shipment_id'] ?? null,
            'selected_rate' => $this->resource['selected_rate'] ?? null,
            'tracking_code' => $this->resource['tracking_code'] ?? null,
            'label' => $this->resource['label'] ?? null,
        ];
    }
}
