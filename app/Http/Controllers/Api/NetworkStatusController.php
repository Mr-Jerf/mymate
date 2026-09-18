<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NetworkStatus;
use App\Support\StatusPageSettings;
use Illuminate\Http\JsonResponse;

class NetworkStatusController extends Controller
{
    public function __invoke(NetworkStatus $status, StatusPageSettings $settings): JsonResponse
    {
        if (! $settings->publicView()['public_enabled']) {
            abort(404);
        }
        return response()->json(['data' => $status->snapshot()])
            ->header('Cache-Control', 'public, max-age=30, s-maxage=30');
    }
}
