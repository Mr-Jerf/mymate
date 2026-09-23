<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\StoreSiteRequest;
use App\Http\Requests\Site\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Sites: the physical locations gear lives at. Read is open to any operator; writes are
 * gated to admins by the global RestrictWritesToAdmins middleware.
 *
 * The index carries a device count and a down-count per site so the geo map can size and
 * colour a whole tower from one payload, without pulling every device down with it.
 */
class SiteController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $sites = Site::query()
            ->withCount('devices')
            ->withCount(['devices as devices_down_count' => fn ($q) => $q->where('status', 'down')])
            ->orderBy('name')
            ->get();

        return SiteResource::collection($sites);
    }

    public function store(StoreSiteRequest $request): SiteResource
    {
        $site = Site::create($request->validated());

        return (new SiteResource($site))->additional(['created' => true]);
    }

    public function show(Site $site): SiteResource
    {
        return new SiteResource($site->loadCount('devices'));
    }

    public function update(UpdateSiteRequest $request, Site $site): SiteResource
    {
        $site->update($request->validated());

        return new SiteResource($site->loadCount('devices'));
    }

    public function destroy(Site $site): Response|JsonResponse
    {
        return DB::transaction(function () use ($site): Response|JsonResponse {
            $lockedSite = Site::query()->whereKey($site->getKey())->lockForUpdate()->firstOrFail();
            $siteId = $lockedSite->getKey();
            $hasDevices = DB::table('devices')->where('site_id', $siteId)->exists();
            $hasLinks = DB::table('site_links')->where('site_a_id', $siteId)->orWhere('site_b_id', $siteId)->exists();
            $hasSubscriptions = DB::table('status_subscriptions')->where('site_id', $siteId)->exists();

            if ($hasDevices || $hasLinks || $hasSubscriptions) {
                return response()->json([
                    'message' => 'This site cannot be deleted while it has assigned devices, topology links, or subscriptions.',
                ], 409);
            }

            $lockedSite->delete();

            return response()->noContent();
        });
    }
}
