<?php

namespace Systha\Shipping\Domains\EasyPost\Controllers;

use EasyPost\Exception\General\EasyPostException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use RuntimeException;
use Systha\Shipping\Domains\EasyPost\Requests\GenerateShippingLabelRequest;
use Systha\Shipping\Domains\EasyPost\Requests\RefundShippingLabelRequest;
use Systha\Shipping\Domains\EasyPost\Resources\ShippingLabelResource;
use Systha\Shipping\Domains\EasyPost\Resources\ShippingLabelRefundResource;
use Systha\Shipping\Domains\EasyPost\Services\EasyPostService;
use Throwable;

class ShippingLabelController extends Controller
{
    public function __construct(
        private readonly EasyPostService $easyPostService
    ) {}

    public function generate(GenerateShippingLabelRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $result = $this->easyPostService->generateLabel(
                $data['shipment_id'],
                $data['rate_id']
            );
        } catch (RuntimeException | Throwable $exception) {
            header('Access-Control-Allow-Origin: *');
            $status = $this->determineStatusCode($exception);
            $message = match ($status) {
                404 => 'EasyPost shipment not found.',
                409 => 'EasyPost shipment is already purchased.',
                422 => $exception->getMessage() === 'Selected EasyPost rate does not belong to the shipment.'
                    ? 'Selected EasyPost rate does not belong to the shipment.'
                    : 'Unable to generate shipping label.',
                502 => 'Unable to generate shipping label with EasyPost.',
                default => 'Unable to generate shipping label.',
            };

            return response()->json([
                'success' => false,
                'status' => $status,
                'message' => $message,
            ], $status);
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    // public function refund(RefundShippingLabelRequest $request): JsonResponse
    // {
    //     try {
    //         $data = $request->validated();
    //         $result = $this->easyPostService->refundShipment(
    //             $data['shipment_id']
    //         );
    //         header('Access-Control-Allow-Origin: *');
    //         dd($result);
    //     } catch (RuntimeException|Throwable $exception) {
    //         $status = $this->determineStatusCode($exception);
    //         $message = match ($status) {
    //             404 => 'EasyPost shipment not found.',
    //             422 => $exception->getMessage() === 'This EasyPost shipment does not have a purchased shipping label.'
    //                 ? 'This EasyPost shipment does not have a purchased shipping label.'
    //                 : 'Unable to refund shipping label.',
    //             502 => 'Unable to refund shipping label with EasyPost.',
    //             default => 'Unable to refund shipping label.',
    //         };

    //         return response()->json([
    //             'success' => false,
    //             'status' => $status,
    //             'message' => $message,
    //         ], $status);
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'data' => ShippingLabelRefundResource::make($result)->resolve(request()),
    //     ]);
    // }

    public function refund(
        RefundShippingLabelRequest $request
    ): JsonResponse {
        try {
            $result = $this->easyPostService->refundShipment(
                $request->validated('shipment_id')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
    private function determineStatusCode(Throwable $exception): int
    {
        if ($exception instanceof RuntimeException) {
            return match ($exception->getMessage()) {
                'Selected EasyPost rate does not belong to the shipment.' => 422,
                'EasyPost shipment not found.' => 404,
                'EasyPost shipment is already purchased.' => 409,
                'This EasyPost shipment does not have a purchased shipping label.' => 422,
                default => $this->hasEasyPostExceptionInChain($exception) ? 502 : 500,
            };
        }

        if ($exception instanceof EasyPostException) {
            return 502;
        }

        return $this->hasEasyPostExceptionInChain($exception) ? 502 : 500;
    }

    private function hasEasyPostExceptionInChain(Throwable $exception): bool
    {
        $previous = $exception->getPrevious();

        while ($previous instanceof Throwable) {
            if ($previous instanceof EasyPostException) {
                return true;
            }

            $previous = $previous->getPrevious();
        }

        return false;
    }
}
