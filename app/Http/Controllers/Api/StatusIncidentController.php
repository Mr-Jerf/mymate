<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StatusIncident\StoreStatusIncidentUpdateRequest;
use App\Http\Requests\StatusIncident\UpdateStatusIncidentRequest;
use App\Http\Resources\StatusIncidentResource;
use App\Http\Resources\StatusIncidentUpdateResource;
use App\Models\StatusIncident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StatusIncidentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = StatusIncident::query()->latest('started_at')->limit(100);
        if ($request->user()?->isRestricted()) {
            $query->whereHas('outages', fn ($outages): mixed => $outages->whereIn('device_id', $request->user()->visibleDeviceIds()));
        }
        return StatusIncidentResource::collection($query->get());
    }

    public function update(UpdateStatusIncidentRequest $request, StatusIncident $statusIncident): StatusIncidentResource
    {
        $this->authorizeVisibility($request, $statusIncident);
        $data = $request->validated();
        if (($data['status'] ?? null) === 'resolved') {
            $data['resolved_at'] = now();
        } elseif (array_key_exists('status', $data)) {
            $data['resolved_at'] = null;
        }
        $statusIncident->update($data);
        return new StatusIncidentResource($statusIncident->refresh());
    }

    public function updates(Request $request, StatusIncident $statusIncident): AnonymousResourceCollection
    {
        $this->authorizeVisibility($request, $statusIncident);
        return StatusIncidentUpdateResource::collection($statusIncident->updates()->with('user')->oldest()->limit(100)->get());
    }

    public function storeUpdate(StoreStatusIncidentUpdateRequest $request, StatusIncident $statusIncident): StatusIncidentUpdateResource
    {
        $this->authorizeVisibility($request, $statusIncident);
        $update = $statusIncident->updates()->create([
            'user_id' => $request->user()->id,
            'message' => $request->validated('message'),
        ]);
        return new StatusIncidentUpdateResource($update->load('user'));
    }

    private function authorizeVisibility(Request $request, StatusIncident $statusIncident): void
    {
        $user = $request->user();
        abort_unless($user !== null && (! $user->isRestricted() || $statusIncident->outages()->whereIn('device_id', $user->visibleDeviceIds())->exists()), 403);
    }
}
