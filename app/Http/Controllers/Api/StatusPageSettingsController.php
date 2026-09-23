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
        $maxBytes = $request->validated('kind') === 'favicon' ? 64 * 1024 : 256 * 1024;
        abort_if($file === null || $file->getSize() > $maxBytes, 422);
        $dimensions = @getimagesize($file->getRealPath());
        abort_unless(is_array($dimensions) && ($dimensions[0] ?? 0) > 0 && ($dimensions[1] ?? 0) > 0 && $dimensions[0] <= 2048 && $dimensions[1] <= 2048, 422);
        $mime = $file->getMimeType();
        $allowedMimes = $request->validated('kind') === 'favicon'
            ? ['image/png', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon']
            : ['image/png', 'image/jpeg', 'image/webp'];
        abort_unless(is_string($mime) && in_array($mime, $allowedMimes, true), 422);
        $dataUri = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($file->getRealPath()));
        return response()->json(['data' => $settings->saveBranding($request->validated('kind'), $dataUri)]);
    }
}
