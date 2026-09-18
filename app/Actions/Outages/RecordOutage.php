<?php

namespace App\Actions\Outages;

use App\Models\Device;
use App\Models\Outage;
use App\Models\StatusIncident;
use App\Services\NetworkStatus;
use App\Support\StatusNotificationDispatcher;
use Illuminate\Support\Facades\DB;

/** Record device down->up events and attach them to one customer-facing incident per site. */
class RecordOutage
{
    public function open(Device $device): void
    {
        DB::transaction(function () use ($device): void {
            $outage = Outage::firstOrCreate(
                ['device_id' => $device->id, 'ended_at' => null],
                ['started_at' => now(), 'cause' => 'unreachable'],
            );
            $site = $device->site;
            $state = $site?->state_code;
            if ($site === null || $state === null || ! isset(NetworkStatus::STATES[$state])) return;
            // Serialize incident lookup/creation for this site so concurrent device events share one incident.
            $site = $site->newQuery()->whereKey($site->id)->lockForUpdate()->first();
            $devices = Device::query()->where('monitored', true)->whereHas('site', fn ($query) => $query->whereKey($site->id))->get();
            $severity = $devices->isNotEmpty() && $devices->every(fn ($item): bool => $item->status?->value === 'down') ? 'outage' : 'degraded';
            $incident = StatusIncident::query()->where('site_id', $site->id)->whereNull('resolved_at')->latest('started_at')->first();
            $created = false;
            if ($incident === null) {
                $created = true;
                $incident = StatusIncident::create(['site_id' => $site->id, 'state_code' => $state, 'severity' => $severity, 'status' => 'investigating', 'summary' => $severity === 'outage' ? 'Service outage' : 'Degraded service', 'started_at' => now()]);
            } else {
                $incident->update(['severity' => $severity, 'status' => 'investigating']);
            }
            if ($outage->status_incident_id !== $incident->id) {
                $outage->status_incident_id = $incident->id;
                $outage->save();
            }
            if ($created) app(StatusNotificationDispatcher::class)->incidentStarted($incident);
        });
    }

    public function close(Device $device): void
    {
        $open = Outage::where('device_id', $device->id)->whereNull('ended_at')->latest('started_at')->first();
        if ($open === null) return;
        $open->ended_at = now();
        $open->duration_s = (int) $open->started_at->diffInSeconds($open->ended_at);
        $open->save();
        $incident = $open->incident;
        if ($incident !== null && ! $incident->outages()->whereNull('ended_at')->exists()) {
            $incident->update(['status' => 'resolved', 'resolved_at' => now()]);
            app(StatusNotificationDispatcher::class)->incidentResolved($incident);
        }
    }
}
