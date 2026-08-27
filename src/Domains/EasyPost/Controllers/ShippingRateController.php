<?php

namespace Systha\Shipping\Domains\EasyPost\Controllers;

use EasyPost\Exception\General\EasyPostException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;
use Systha\Shipping\Domains\EasyPost\Requests\ShippingRateRequest;
use Systha\Shipping\Domains\EasyPost\Resources\ShippingRateResource;
use Systha\Shipping\Domains\EasyPost\Services\EasyPostService;
use Throwable;

class ShippingRateController extends Controller
{
    public function __construct(
        private readonly EasyPostService $easyPostService
    ) {}

    public function estimate(ShippingRateRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $result = $this->easyPostService->getRates(
                $data['from_address'],
                $data['to_address'],
                $data['parcel'],
            );
            return $this->buildSuccessResponse($result);
        } catch (RuntimeException | Throwable $exception) {
            $status = $this->determineStatusCode($exception);

            return response()->json([
                'success' => false,
                'message' => $status === 502
                    ? 'Unable to retrieve shipping rates from EasyPost.'
                    : 'Unable to estimate shipping rates.',
            ], $status);
        }
    }

    public function test(): JsonResponse
    {
        try {
            $result = $this->easyPostService->getConfiguredRates();

            return $this->buildSuccessResponse($result);
        } catch (RuntimeException | Throwable $exception) {
            $status = $this->determineStatusCode($exception);

            return response()->json([
                'success' => false,
                'message' => $status === 502
                    ? 'Unable to retrieve shipping rates from EasyPost.'
                    : 'Unable to estimate shipping rates.',
            ], $status);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function buildSuccessResponse(array $result): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'shipment_id' => $result['shipment_id'] ?? null,
                'parcel' => $result['parcel'] ?? [],
                'rates' => ShippingRateResource::collection($result['rates'] ?? [])->resolve(request()),
            ],
        ]);
    }

    private function determineStatusCode(Throwable $exception): int
    {
        if ($exception instanceof EasyPostException) {
            return 502;
        }

        $previous = $exception->getPrevious();

        while ($previous instanceof Throwable) {
            if ($previous instanceof EasyPostException) {
                return 502;
            }

            $previous = $previous->getPrevious();
        }

        return 500;
    }

    public function selected(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipment_id' => ['required', 'string'],
            'rate_id' => ['required', 'string'],
        ]);

        $result = $this->easyPostService->getSelectedRate(
            $data['shipment_id'],
            $data['rate_id']
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function shipmentRates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipment_id' => ['required', 'string'],
        ]);

        try {
            $result = $this->easyPostService->getShipmentRates(
                $validated['shipment_id']
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }
    }
}
