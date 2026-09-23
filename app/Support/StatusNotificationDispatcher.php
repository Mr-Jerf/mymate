<?php

namespace App\Support;

use App\Jobs\SendStatusNotification;
use App\Models\Site;
use App\Models\StatusIncident;
use App\Models\StatusSubscription;

class StatusNotificationDispatcher
{
    public function incidentStarted(StatusIncident $incident): void
    {
        $severity = $incident->severity;
        $this->dispatch($incident->site, $severity, $incident->id.':started', ucfirst($severity).' service at '.$incident->site?->name, "A {$severity} condition has been reported at {$incident->site?->name}. Network Status is monitoring the incident and will send further updates.");
    }

    public function incidentUpdate(StatusIncident $incident, int $updateId, string $message): void
    {
        $this->dispatch($incident->site, 'updates', $incident->id.':update:'.$updateId, 'Status update for '.$incident->site?->name, $message);
    }

    public function incidentMonitoring(StatusIncident $incident): void
    {
        $this->dispatch($incident->site, 'updates', $incident->id.':monitoring', 'Monitoring service at '.$incident->site?->name, "Service has been restored at {$incident->site?->name}; we are monitoring stability before marking the incident resolved.");
    }

    public function incidentResolved(StatusIncident $incident): void
    {
        $this->dispatch($incident->site, 'resolved', $incident->id.':resolved', 'Service restored at '.$incident->site?->name, "Service has been restored at {$incident->site?->name}.");
    }

    private function dispatch(?Site $site, string $preference, string $eventKey, string $subject, string $body): void
    {
        if ($site === null) return;
        StatusSubscription::query()->where('site_id', $site->id)->whereNotNull('verified_at')->whereNull('unsubscribed_at')->get()->each(function (StatusSubscription $subscription) use ($preference, $eventKey, $subject, $body): void {
            if (($subscription->preferences[$preference] ?? false) !== true) return;
            SendStatusNotification::dispatch($subscription->id, $eventKey, $subject, $body);
        });
    }
}
