<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StatusPage\UpdateStatusPageSettingsRequest;
use App\Http\Requests\StatusPage\UploadStatusPageBrandingRequest;
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

    public function upload(UploadStatusPageBrandingRequest $request, StatusPageSettings $settings): JsonResponse
    {
        $file = $request->file('asset');
        $mime = $file->getMimeType();
        abort_unless(is_string($mime) && in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'], true), 422);
        $dataUri = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($file->getRealPath()));
        return response()->json(['data' => $settings->saveBranding($request->validated('kind'), $dataUri)]);
    }
}
