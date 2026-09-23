<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Outage\StoreOutageUpdateRequest;
use App\Http\Resources\OutageUpdateResource;
use App\Models\Outage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OutageUpdateController extends Controller
{
    public function index(Request $request, Outage $outage): AnonymousResourceCollection
    {
        $this->authorizeVisibility($request, $outage);

        return OutageUpdateResource::collection(
            $outage->updates()->with('user')->oldest()->limit(100)->get()
        );
    }

    public function store(StoreOutageUpdateRequest $request, Outage $outage): OutageUpdateResource
    {
        $this->authorizeVisibility($request, $outage);

        $update = $outage->updates()->create([
            'user_id' => $request->user()->id,
            'message' => $request->validated('message'),
        ]);

        return new OutageUpdateResource($update->load('user'));
    }

    private function authorizeVisibility(Request $request, Outage $outage): void
    {
        $user = $request->user();
        abort_unless(
            $user === null || ! $user->isRestricted() || in_array($outage->device_id, $user->visibleDeviceIds(), true),
            403,
            'This outage is outside your permitted device visibility.',
        );
    }
}
