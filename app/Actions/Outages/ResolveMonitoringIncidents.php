<?php

namespace App\Actions\Outages;

use App\Models\StatusIncident;
use App\Support\StatusNotificationDispatcher;
use Illuminate\Support\Facades\DB;

class ResolveMonitoringIncidents
{
    public function __invoke(): int
    {
        $resolved = 0;
        StatusIncident::query()->where('status', 'monitoring')->whereNull('resolved_at')->where('monitoring_until', '<=', now())->eachById(function (StatusIncident $incident) use (&$resolved): void {
            DB::transaction(function () use ($incident, &$resolved): void {
                $locked = StatusIncident::query()->whereKey($incident->id)->lockForUpdate()->first();
                if ($locked === null || $locked->status !== 'monitoring' || $locked->resolved_at !== null || $locked->monitoring_until?->isFuture()) return;
                if ($locked->outages()->whereNull('ended_at')->exists()) {
                    $locked->update(['status' => 'investigating', 'monitoring_started_at' => null, 'monitoring_until' => null]);
                    return;
                }
                $locked->update(['status' => 'resolved', 'resolved_at' => now(), 'monitoring_until' => null]);
                app(StatusNotificationDispatcher::class)->incidentResolved($locked);
                $resolved++;
            });
        });
        return $resolved;
    }
}
