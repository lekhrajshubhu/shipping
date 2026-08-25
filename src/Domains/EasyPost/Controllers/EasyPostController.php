<?php

namespace Systha\Shipping\Domains\EasyPost\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class EasyPostController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Shipping EasyPost domain is ready.',
        ]);
    }
}
