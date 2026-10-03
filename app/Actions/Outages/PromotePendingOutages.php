<?php

namespace App\Actions\Outages;

use App\Models\Device;
use App\Models\Outage;
use App\Models\StatusIncident;
use App\Services\NetworkStatus;
use App\Support\MaintenanceGuard;
use App\Support\StatusNotificationDispatcher;
use Illuminate\Support\Facades\DB;

/** Promote persistent raw outages into customer-facing site incidents. */
class PromotePendingOutages
{
    public function __invoke(): void
    {
        $cutoff = now()->subMinutes((int) config('mymate.status.incident_delay_minutes', 5));

        Outage::query()
            ->whereNull('ended_at')
            ->whereNull('status_incident_id')
            ->where('started_at', '<=', $cutoff)
            ->whereHas('device', fn ($query) => $query->where('monitored', true)->whereNotNull('site_id'))
            ->eachById(fn (Outage $outage) => $this->promote($outage->id));
    }

    private function promote(int $outageId): void
    {
        DB::transaction(function () use ($outageId): void {
            $outage = Outage::query()->whereKey($outageId)->lockForUpdate()->first();
            if ($outage === null || $outage->ended_at !== null || $outage->status_incident_id !== null) return;

            $device = Device::query()->with('site')->whereKey($outage->device_id)->first();
            $site = $device?->site;
            $state = $site?->state_code;
            if ($device === null || ! $device->monitored || $site === null || $state === null || ! isset(NetworkStatus::STATES[$state])) return;
            if ((new MaintenanceGuard)->covers($device->id)) return;

            $site = $site->newQuery()->whereKey($site->id)->lockForUpdate()->first();
            $devices = Device::query()->where('monitored', true)->whereHas('site', fn ($query) => $query->whereKey($site->id))->get();
            $severity = $devices->isNotEmpty() && $devices->every(fn ($item): bool => $item->status?->value === 'down') ? 'outage' : 'degraded';
            $incident = StatusIncident::query()->where('site_id', $site->id)->whereNull('resolved_at')->latest('started_at')->first();
            $created = false;
            if ($incident === null) {
                $created = true;
                $incident = StatusIncident::create([
                    'site_id' => $site->id,
                    'state_code' => $state,
                    'severity' => $severity,
                    'status' => 'investigating',
                    'summary' => $severity === 'outage' ? 'Service outage' : 'Degraded service',
                    'started_at' => $outage->started_at,
                ]);
            } else {
                $incident->update(['severity' => $severity, 'status' => 'investigating', 'monitoring_started_at' => null, 'monitoring_until' => null]);
            }

            $outage->status_incident_id = $incident->id;
            $outage->save();
            if ($created) DB::afterCommit(fn () => app(StatusNotificationDispatcher::class)->incidentStarted($incident));
        });
    }
}
