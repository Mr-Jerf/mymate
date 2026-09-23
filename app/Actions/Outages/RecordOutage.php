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
                $incident->update(['severity' => $severity, 'status' => 'investigating', 'monitoring_started_at' => null, 'monitoring_until' => null]);
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
        DB::transaction(function () use ($device): void {
            $candidate = Outage::where('device_id', $device->id)->whereNull('ended_at')->latest('started_at')->first();
            if ($candidate === null) return;

            // Lock the incident first, matching ResolveMonitoringIncidents, so a
            // resolver cannot promote this incident while the outage is closing.
            $incident = $candidate->status_incident_id === null
                ? null
                : StatusIncident::query()->whereKey($candidate->status_incident_id)->lockForUpdate()->first();
            $open = Outage::query()->whereKey($candidate->id)->lockForUpdate()->first();
            if ($open === null || $open->ended_at !== null) return;
            $open->ended_at = now();
            $open->duration_s = (int) $open->started_at->diffInSeconds($open->ended_at);
            $open->save();
            if ($incident !== null && ! $incident->outages()->whereNull('ended_at')->exists()) {
                $grace = app(\App\Support\StatusPageSettings::class)->publicView()['monitoring_grace_minutes'];
                $incident->update(['status' => 'monitoring', 'monitoring_started_at' => now(), 'monitoring_until' => now()->addMinutes($grace), 'resolved_at' => null]);
                DB::afterCommit(fn () => app(StatusNotificationDispatcher::class)->incidentMonitoring($incident));
            }
        });
    }
}
