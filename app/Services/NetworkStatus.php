<?php

namespace App\Services;

use App\Models\MaintenanceWindow;
use App\Models\Site;
use App\Models\StatusIncident;
use App\Support\StatusPageSettings;

class NetworkStatus
{
    /** @var array<string, string> */
    public const STATES = [
        'AL'=>'Alabama','AK'=>'Alaska','AZ'=>'Arizona','AR'=>'Arkansas','CA'=>'California','CO'=>'Colorado','CT'=>'Connecticut','DE'=>'Delaware','DC'=>'District of Columbia','FL'=>'Florida','GA'=>'Georgia','HI'=>'Hawaii','ID'=>'Idaho','IL'=>'Illinois','IN'=>'Indiana','IA'=>'Iowa','KS'=>'Kansas','KY'=>'Kentucky','LA'=>'Louisiana','ME'=>'Maine','MD'=>'Maryland','MA'=>'Massachusetts','MI'=>'Michigan','MN'=>'Minnesota','MS'=>'Mississippi','MO'=>'Missouri','MT'=>'Montana','NE'=>'Nebraska','NV'=>'Nevada','NH'=>'New Hampshire','NJ'=>'New Jersey','NM'=>'New Mexico','NY'=>'New York','NC'=>'North Carolina','ND'=>'North Dakota','OH'=>'Ohio','OK'=>'Oklahoma','OR'=>'Oregon','PA'=>'Pennsylvania','RI'=>'Rhode Island','SC'=>'South Carolina','SD'=>'South Dakota','TN'=>'Tennessee','TX'=>'Texas','UT'=>'Utah','VT'=>'Vermont','VA'=>'Virginia','WA'=>'Washington','WV'=>'West Virginia','WI'=>'Wisconsin','WY'=>'Wyoming',
    ];

    public function snapshot(): array
    {
        $now = now();
        $windowStart = $now->copy()->subDays(60);
        $windowSeconds = $windowStart->diffInSeconds($now);
        $sites = Site::query()->whereIn('state_code', array_keys(self::STATES))->with(['devices' => function ($query) use ($windowStart, $now): void {
            $query->where('monitored', true)->with(['outages' => function ($outages) use ($windowStart, $now): void {
                $outages->where('started_at', '<', $now)->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $windowStart));
            }]);
        }])->orderBy('name')->get();
        $historyIncidents = StatusIncident::query()->whereNotNull('site_id')->whereHas('site', fn ($query) => $query->whereIn('state_code', array_keys(self::STATES)))->where('started_at', '<', $now)
            ->where(function ($q) use ($now): void {
                $q->where('started_at', '>', $now->copy()->subDays(60))
                    ->orWhere(fn ($nested) => $nested->whereNull('resolved_at')->orWhere('resolved_at', '>', $now->copy()->subDays(60)));
            })->with(['site', 'updates'])->get();
        $historyMaintenance = $this->maintenance($now->copy()->subDays(60), $now);
        $settings = app(StatusPageSettings::class)->publicView();
        $showSiteNames = $settings['show_site_names'];
        $showDeviceCounts = $settings['show_device_counts'];
        $publicSites = $sites->map(fn (Site $site): array => $this->siteSnapshot($site, $windowStart, $windowSeconds, $now, $historyIncidents, $historyMaintenance, $showSiteNames, $showDeviceCounts))->values()->all();
        $maintenance = $this->maintenance($now->copy()->subDays(30), $now->copy()->addDays(30))->map(fn (MaintenanceWindow $window): array => $this->maintenancePayload($window, $now))->values()->all();
        $statusFeed = StatusIncident::query()->whereNotNull('site_id')->whereHas('site', fn ($query) => $query->whereIn('state_code', array_keys(self::STATES)))->where('started_at', '<', $now)
            ->where(fn ($q) => $q->whereNull('resolved_at')->orWhere('resolved_at', '>', $now->copy()->subDays(30)))
            ->with(['site', 'updates'])->orderByDesc('started_at')->limit(100)->get()
            ->map(fn (StatusIncident $incident): array => $this->incidentPayload($incident, $showSiteNames))->values()->all();
        $history60 = $this->historyPayload($historyIncidents, $historyMaintenance, $now, $windowStart, $showSiteNames);
        $overall = $this->overallStatus($publicSites);
        return ['overall' => ['status' => $overall, 'label' => match ($overall) { 'operational'=>'All systems operational', 'degraded'=>'Some systems are experiencing issues', 'outage'=>'A network outage is in progress', default=>'System status is currently unavailable' }], 'sites' => $publicSites, 'configuration' => app(StatusPageSettings::class)->publicView(), 'maintenance' => $maintenance, 'status_feed' => $statusFeed, 'history_60d' => $history60, 'generated_at' => $now->toISOString()];
    }

    private function siteSnapshot(Site $site, $windowStart, int $windowSeconds, $now, $historyIncidents, $historyMaintenance, bool $showSiteNames, bool $showDeviceCounts): array
    {
        $devices = $site->devices;
        $total = $devices->count();
        $down = $devices->filter(fn ($device): bool => $device->status?->value === 'down')->count();
        $unknown = $devices->filter(fn ($device): bool => $device->status?->value === 'unknown')->count();
        $outageSeconds = $devices->sum(fn ($device): int => $device->outages->sum(function ($outage) use ($windowStart, $now): int {
            $start = $outage->started_at->greaterThan($windowStart) ? $outage->started_at : $windowStart;
            $end = $outage->ended_at?->lessThan($now) ? $outage->ended_at : $now;
            return $end->greaterThan($start) ? $start->diffInSeconds($end) : 0;
        }));
        $key = hash('sha256', 'public-status-site:'.$site->id);
        $history = collect(range(6, 0))->map(function (int $daysAgo) use ($site, $now, $historyIncidents, $historyMaintenance, $showSiteNames): array {
            $dayStart = $now->copy()->subDays($daysAgo)->startOfDay(); $dayEnd = $dayStart->copy()->endOfDay();
            $incidents = $historyIncidents->filter(fn (StatusIncident $incident): bool => $incident->site_id === $site->id && $incident->started_at <= $dayEnd && ($incident->resolved_at === null || $incident->resolved_at >= $dayStart));
            $maintenance = $historyMaintenance->filter(fn (MaintenanceWindow $window): bool => $window->starts_at <= $dayEnd && $window->ends_at >= $dayStart);
            return ['date'=>$dayStart->toDateString(), 'status'=>$incidents->contains(fn ($i): bool => $i->severity === 'outage') ? 'outage' : ($incidents->isNotEmpty() ? 'degraded' : 'operational'), 'incidents'=>$incidents->map(fn ($i): array => $this->incidentPayload($i, $showSiteNames))->values()->all(), 'maintenance'=>$maintenance->map(fn ($w): array => $this->maintenancePayload($w, $now))->values()->all()];
        })->values()->all();
        return array_merge(['key'=>$key, 'state_code'=>$site->state_code, 'state_name'=>self::STATES[$site->state_code], 'status'=>$this->deviceStatus($devices), 'uptime_60d'=>$total > 0 && $unknown === 0 ? round(max(0, 100 - (($outageSeconds / ($windowSeconds * $total)) * 100)), 2) : null, 'history_7d'=>$history], $showSiteNames ? ['name'=>$site->name] : [], $showDeviceCounts ? ['monitored_devices'=>$total, 'down_devices'=>$down, 'unknown_devices'=>$unknown] : []);
    }

    private function deviceStatus($devices): string
    {
        if ($devices->isEmpty()) return 'unknown';
        if ($devices->every(fn ($d): bool => $d->status?->value === 'down')) return 'outage';
        if ($devices->every(fn ($d): bool => $d->status?->value === 'unknown')) return 'unknown';
        return $devices->contains(fn ($d): bool => $d->status?->value === 'down' || $d->status?->value === 'unknown') ? 'degraded' : 'operational';
    }

    private function overallStatus(array $sites): string
    {
        $statuses = collect($sites)->pluck('status');
        return $statuses->contains('outage') ? 'outage' : ($statuses->contains('degraded') ? 'degraded' : ($statuses->contains('unknown') ? 'unknown' : 'operational'));
    }

    private function historyPayload($incidents, $maintenance, $now, $windowStart, bool $showSiteNames): array
    {
        $events = $incidents->map(fn (StatusIncident $incident): array => array_merge($this->historyIncidentPayload($incident, $showSiteNames), ['event_type' => 'incident', 'date' => ($incident->started_at?->greaterThan($windowStart) ? $incident->started_at : $windowStart)?->toDateString()]))
            ->merge($maintenance->map(fn (MaintenanceWindow $window): array => array_merge($this->maintenancePayload($window, $now), ['event_type' => 'maintenance', 'date' => ($window->starts_at?->greaterThan($windowStart) ? $window->starts_at : $windowStart)?->toDateString(), 'site' => 'All sites'])))
            ->sortByDesc('date')->values()->all();
        return $events;
    }

    private function maintenance($from, $to)
    {
        return MaintenanceWindow::query()->where('enabled', true)->where('ends_at', '>', $from)->where('starts_at', '<=', $to)->orderBy('starts_at')->get()->filter(fn (MaintenanceWindow $w): bool => $w->scope === null || (is_array($w->scope) && ($w->scope['type'] ?? null) === 'all'))->take(100);
    }

    private function maintenancePayload(MaintenanceWindow $window, $now): array
    {
        return ['overview'=>$window->name, 'description'=>$window->description, 'starts_at'=>$window->starts_at?->toIso8601String(), 'ends_at'=>$window->ends_at?->toIso8601String(), 'status'=>$window->starts_at <= $now && $window->ends_at > $now ? 'active' : ($window->starts_at > $now ? 'scheduled' : 'completed'), 'active'=>$window->starts_at <= $now && $window->ends_at > $now];
    }

    private function historyIncidentPayload(StatusIncident $incident, bool $showSiteNames): array
    {
        return array_merge([
            'site_status' => $incident->severity,
            'summary' => $incident->summary ?? ($incident->severity === 'outage' ? 'Service outage' : 'Degraded service'),
            'status' => $incident->status,
            'started_at' => $incident->started_at?->toIso8601String(),
            'monitoring_started_at' => $incident->monitoring_started_at?->toIso8601String(),
            'monitoring_until' => $incident->monitoring_until?->toIso8601String(),
            'ended_at' => $incident->resolved_at?->toIso8601String(),
            'update_count' => $incident->updates->count(),
            'latest_update_at' => $incident->updates->max('created_at')?->toIso8601String(),
            'updates' => $incident->updates->sortBy('created_at')->take(10)->map(fn ($update): array => ['message' => mb_substr((string) $update->message, 0, 500), 'created_at' => $update->created_at?->toIso8601String()])->values()->all(),
        ], $showSiteNames ? ['site' => $incident->site?->name] : []);
    }

    private function incidentPayload(StatusIncident $incident, bool $showSiteNames): array
    {
        $payload = ['incident_id'=>$incident->id, 'site_key'=>$incident->site ? hash('sha256', 'public-status-site:'.$incident->site_id) : null, 'state'=>$incident->site?->state_code ?? $incident->state_code, 'site_status'=>$incident->severity, 'summary'=>$incident->summary ?? ($incident->severity === 'outage' ? 'Service outage' : 'Degraded service'), 'status'=>$incident->status, 'started_at'=>$incident->started_at?->toIso8601String(), 'monitoring_started_at'=>$incident->monitoring_started_at?->toIso8601String(), 'monitoring_until'=>$incident->monitoring_until?->toIso8601String(), 'ended_at'=>$incident->resolved_at?->toIso8601String(), 'active'=>$incident->resolved_at === null, 'updates'=>$incident->updates->map(fn ($u): array => ['message'=>$u->message, 'created_at'=>$u->created_at?->toIso8601String()])->values()->all()];
        return $showSiteNames ? array_merge($payload, ['site' => $incident->site?->name]) : $payload;
    }
}
