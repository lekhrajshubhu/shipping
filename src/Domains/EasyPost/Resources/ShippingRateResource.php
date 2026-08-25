<?php

namespace Systha\Shipping\Domains\EasyPost\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingRateResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource['id'] ?? null,
            'carrier' => $this->resource['carrier'] ?? null,
            'service' => $this->resource['service'] ?? null,
            'rate' => $this->resource['rate'] ?? null,
            'currency' => $this->resource['currency'] ?? null,
            'delivery_days' => $this->resource['delivery_days'] ?? null,
            'delivery_date' => $this->resource['delivery_date'] ?? null,
            'delivery_date_guaranteed' => $this->resource['delivery_date_guaranteed'] ?? false,
        ];
    }
}
