<?php

namespace Systha\Shipping\Domains\EasyPost\Services;

use EasyPost\Event;
use EasyPost\Exception\General\EasyPostException;
use EasyPost\Util\Util;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Systha\Jwellery\Domains\Shared\Models\OrderShipment;
use Throwable;

class EasyPostWebhookService
{
    /**
     * @return array<string, mixed>
     */
    public function process(string $rawBody, ?string $signature): array
    {
        $this->authenticate($rawBody, $signature);

        try {
            /** @var Event $event */
            $event = Util::receiveEvent($rawBody);
        } catch (EasyPostException|Throwable $exception) {
            throw new RuntimeException(
                'Malformed EasyPost webhook payload.',
                0,
                $exception
            );
        }

        $normalizedEvent = $this->normalizeEvent($event);

        if (($normalizedEvent['event_description'] ?? null) !== 'refund.successful') {
            $this->logAcceptedEvent($normalizedEvent, false);

            return [
                ...$normalizedEvent,
                'handled' => false,
                'already_synchronized' => false,
                'synchronized' => false,
            ];
        }

        $refund = $this->normalizeRefundResult(data_get($event, 'result'));

        if ($refund === null) {
            throw new RuntimeException('Malformed EasyPost webhook payload.');
        }

        $sync = $this->synchronizeRefundResult($normalizedEvent, $refund);

        $payload = [
            ...$normalizedEvent,
            ...$refund,
            'handled' => true,
            'already_synchronized' => $sync['already_synchronized'],
            'synchronized' => $sync['synchronized'],
        ];

        $this->logAcceptedEvent($payload, true);

        return $payload;
    }

    private function authenticate(string $rawBody, ?string $signature): void
    {
        $webhookSecret = $this->getWebhookSecret();

        if (! is_string($signature) || trim($signature) === '') {
            throw new RuntimeException('EasyPost webhook signature is missing.');
        }

        try {
            Util::validateWebhook($rawBody, [
                'X-Hmac-Signature' => $signature,
            ], $webhookSecret);
        } catch (EasyPostException $exception) {
            throw new RuntimeException(
                'Invalid EasyPost webhook signature.',
                0,
                $exception
            );
        }
    }

    private function getWebhookSecret(): string
    {
        $webhookSecret = config('shipping.easypost.webhook_secret');

        if (! is_string($webhookSecret) || trim($webhookSecret) === '') {
            throw new RuntimeException('EasyPost webhook secret is not configured.');
        }

        return trim($webhookSecret);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeEvent(Event $event): array
    {
        return [
            'event_id' => data_get($event, 'id'),
            'event_description' => data_get($event, 'description'),
            'description' => data_get($event, 'description'),
            'mode' => data_get($event, 'mode'),
            'created_at' => data_get($event, 'created_at'),
            'updated_at' => data_get($event, 'updated_at'),
        ];
    }

    /**
     * @param  mixed  $refund
     * @return array<string, mixed>|null
     */
    private function normalizeRefundResult(mixed $refund): ?array
    {
        if (! is_object($refund) && ! is_array($refund)) {
            return null;
        }

        $shipmentId = data_get($refund, 'shipment_id');
        $status = data_get($refund, 'status');

        if (! is_string($shipmentId) || trim($shipmentId) === '') {
            return null;
        }

        if (! is_string($status) || trim($status) === '') {
            return null;
        }

        return [
            'refund_id' => data_get($refund, 'id'),
            'shipment_id' => $shipmentId,
            'tracking_code' => data_get($refund, 'tracking_code'),
            'carrier' => data_get($refund, 'carrier'),
            'refund_status' => strtolower(trim($status)),
            'confirmation_number' => data_get($refund, 'confirmation_number'),
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $refund
     * @return array{already_synchronized: bool, synchronized: bool}
     */
    protected function synchronizeRefundResult(array $event, array $refund): array
    {
        $shipmentId = $refund['shipment_id'];
        $eventId = $event['event_id'] ?? null;
        $refundStatus = $refund['refund_status'];
        $trackingCode = $refund['tracking_code'] ?? null;

        $orderShipment = $this->findOrderShipment($shipmentId);

        if (! $orderShipment instanceof OrderShipment) {
            return [
                'already_synchronized' => false,
                'synchronized' => false,
            ];
        }

        if (is_string($eventId) && trim($eventId) !== '' && (string) $orderShipment->easypost_event_id === $eventId) {
            return [
                'already_synchronized' => true,
                'synchronized' => false,
            ];
        }

        $this->saveOrderShipmentRefundStatus(
            $orderShipment,
            $refundStatus,
            is_string($trackingCode) && trim($trackingCode) !== '' ? $trackingCode : null,
            is_string($eventId) && trim($eventId) !== '' ? $eventId : null,
            data_get($event, 'created_at')
        );

        return [
            'already_synchronized' => false,
            'synchronized' => true,
        ];
    }

    protected function findOrderShipment(string $shipmentId): ?OrderShipment
    {
        return OrderShipment::query()
            ->where('shipment_id', $shipmentId)
            ->first();
    }

    protected function saveOrderShipmentRefundStatus(
        OrderShipment $orderShipment,
        string $refundStatus,
        ?string $trackingCode,
        ?string $eventId = null,
        mixed $eventCreatedAt = null
    ): void {
        $now = now();

        $refundRequestedAt = $orderShipment->refund_requested_at;
        $refundedAt = $orderShipment->refunded_at;

        if (in_array($refundStatus, ['submitted', 'refunded'], true) && ! $refundRequestedAt) {
            $refundRequestedAt = $this->parseWebhookTimestamp($eventCreatedAt) ?? $now;
        }

        if ($refundStatus === 'refunded' && ! $refundedAt) {
            $refundedAt = $this->parseWebhookTimestamp($eventCreatedAt) ?? $now;
        }

        $orderShipment->forceFill([
            'refund_status' => $refundStatus,
            'refund_requested_at' => $refundRequestedAt,
            'refunded_at' => $refundedAt,
            'tracking_number' => filled($trackingCode) ? $trackingCode : $orderShipment->tracking_number,
            'easypost_event_id' => $eventId ?? $orderShipment->easypost_event_id,
        ])->save();
    }

    private function parseWebhookTimestamp(mixed $value): ?\Illuminate\Support\Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return \Illuminate\Support\Carbon::instance($value);
        }

        if (is_numeric($value)) {
            return \Illuminate\Support\Carbon::createFromTimestamp((int) $value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $event
     * @return void
     */
    private function logAcceptedEvent(array $event, bool $handled): void
    {
        Log::info('EasyPost webhook accepted.', [
            'event_id' => $event['event_id'] ?? null,
            'event_description' => $event['event_description'] ?? null,
            'mode' => $event['mode'] ?? null,
            'shipment_id' => $event['shipment_id'] ?? null,
            'refund_status' => $event['refund_status'] ?? null,
            'tracking_code' => $event['tracking_code'] ?? null,
            'handled' => $handled,
        ]);
    }
}
