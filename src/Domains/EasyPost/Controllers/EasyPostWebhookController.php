<?php

namespace Systha\Shipping\Domains\EasyPost\Controllers;

use EasyPost\Exception\General\EasyPostException;
use EasyPost\Util\Util;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;
use Systha\Shipping\Domains\EasyPost\Services\EasyPostWebhookService;
use Throwable;

class EasyPostWebhookController extends Controller
{
    public function __construct(
        private readonly EasyPostWebhookService $easyPostWebhookService
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Hmac-Signature');

        try {
            $this->authenticateWebhook($rawBody, $signature);
            $this->parseWebhookEvent($rawBody);
            $result = $this->easyPostWebhookService->process($rawBody, $signature);
        } catch (RuntimeException|Throwable $exception) {
            $status = $this->determineStatusCode($exception);

            return response()->json([
                'success' => false,
                'message' => $this->errorMessage($exception, $status),
            ], $status);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ], 200);
    }

    private function determineStatusCode(Throwable $exception): int
    {
        if ($exception instanceof RuntimeException) {
            return match ($exception->getMessage()) {
                'EasyPost webhook signature is missing.',
                'Invalid EasyPost webhook signature.' => 401,
                'Malformed EasyPost webhook payload.' => 400,
                'EasyPost webhook secret is not configured.' => 500,
                default => 500,
            };
        }

        return 500;
    }

    private function authenticateWebhook(string $rawBody, ?string $signature): void
    {
        $webhookSecret = config('shipping.easypost.webhook_secret');

        if (! is_string($webhookSecret) || trim($webhookSecret) === '') {
            throw new RuntimeException('EasyPost webhook secret is not configured.');
        }

        if (! is_string($signature) || trim($signature) === '') {
            throw new RuntimeException('EasyPost webhook signature is missing.');
        }

        try {
            Util::validateWebhook($rawBody, [
                'X-Hmac-Signature' => $signature,
            ], trim($webhookSecret));
        } catch (EasyPostException $exception) {
            throw new RuntimeException(
                'Invalid EasyPost webhook signature.',
                0,
                $exception
            );
        }
    }

    private function parseWebhookEvent(string $rawBody): void
    {
        try {
            Util::receiveEvent($rawBody);
        } catch (EasyPostException $exception) {
            throw new RuntimeException(
                'Malformed EasyPost webhook payload.',
                0,
                $exception
            );
        }
    }

    private function errorMessage(Throwable $exception, int $status): string
    {
        if ($exception instanceof RuntimeException) {
            return match ($exception->getMessage()) {
                'EasyPost webhook signature is missing.' => 'EasyPost webhook signature is missing.',
                'Invalid EasyPost webhook signature.' => 'Invalid EasyPost webhook signature.',
                'Malformed EasyPost webhook payload.' => 'Malformed EasyPost webhook payload.',
                'EasyPost webhook secret is not configured.' => 'EasyPost webhook secret is not configured.',
                default => $status === 401
                    ? 'Invalid EasyPost webhook signature.'
                    : 'Unable to process EasyPost webhook.',
            };
        }

        return 'Unable to process EasyPost webhook.';
    }
}
