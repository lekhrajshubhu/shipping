<?php

namespace Systha\Shipping\Domains\EasyPost\Controllers;

use EasyPost\Exception\General\EasyPostException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use RuntimeException;
use Systha\Shipping\Domains\EasyPost\Requests\VerifyAddressRequest;
use Systha\Shipping\Domains\EasyPost\Resources\AddressResource;
use Systha\Shipping\Domains\EasyPost\Services\EasyPostAddressService;
use Throwable;

class AddressController extends Controller
{
    public function __construct(
        private readonly EasyPostAddressService $easyPostAddressService
    ) {
    }

    public function verify(VerifyAddressRequest $request): JsonResponse
    {
        try {
            $result = $this->easyPostAddressService->verifyAddress($request->validated());
        } catch (RuntimeException|Throwable $exception) {
            $status = $this->determineStatusCode($exception);

            return response()->json([
                'success' => false,
                'message' => $status === 502
                    ? 'Unable to verify address with EasyPost.'
                    : 'Unable to verify address.',
            ], $status);
        }

        return response()->json([
            'success' => true,
            'data' => AddressResource::make($result)->resolve(request()),
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
}
