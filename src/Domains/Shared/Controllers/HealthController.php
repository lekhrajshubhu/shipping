<?php

namespace Systha\Shipping\Domains\Shared\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Shipping shared domain is ready.',
        ]);
    }
}
