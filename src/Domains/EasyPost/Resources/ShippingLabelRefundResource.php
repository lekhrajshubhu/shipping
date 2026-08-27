<?php

namespace Systha\Shipping\Domains\EasyPost\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingLabelRefundResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'shipment_id' => $this->resource['shipment_id'] ?? null,
            'tracking_code' => $this->resource['tracking_code'] ?? null,
            'refund_status' => $this->resource['refund_status'] ?? null,
            'already_requested' => $this->resource['already_requested'] ?? false,
        ];
    }
}
