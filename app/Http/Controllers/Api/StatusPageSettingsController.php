<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StatusPage\UpdateStatusPageSettingsRequest;
use App\Support\StatusPageSettings;
use Illuminate\Http\JsonResponse;

class StatusPageSettingsController extends Controller
{
    public function show(StatusPageSettings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->publicView()]);
    }

    public function update(UpdateStatusPageSettingsRequest $request, StatusPageSettings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->save($request->validated())]);
    }
}
