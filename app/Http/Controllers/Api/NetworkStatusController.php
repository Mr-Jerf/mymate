<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NetworkStatus;
use Illuminate\Http\JsonResponse;

class NetworkStatusController extends Controller
{
    public function __invoke(NetworkStatus $status): JsonResponse
    {
        return response()->json(['data' => $status->snapshot()])
            ->header('Cache-Control', 'public, max-age=30, s-maxage=30');
    }
}
