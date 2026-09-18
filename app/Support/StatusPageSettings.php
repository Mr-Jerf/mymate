<?php

namespace App\Support;

use App\Models\Setting;

class StatusPageSettings
{
    public const DEFAULTS = [
        'brand_name' => 'Network Status',
        'subtitle' => 'Live service health',
        'poll_ms' => 30000,
        'show_site_names' => true,
        'show_device_counts' => true,
        'allow_subscriptions' => true,
        'public_enabled' => true,
        'color_operational' => '#34d399',
        'color_degraded' => '#fbbf24',
        'color_outage' => '#f87171',
        'color_unknown' => '#94a3b8',
        'color_maintenance_scheduled' => '#a78bfa',
        'color_maintenance_active' => '#60a5fa',
        'color_maintenance_completed' => '#34d399',
    ];

    public function publicView(): array
    {
        return [
            'brand_name' => (string) $this->get('brand_name'),
            'subtitle' => (string) $this->get('subtitle'),
            'poll_ms' => (int) $this->get('poll_ms'),
            'show_site_names' => (bool) $this->get('show_site_names'),
            'show_device_counts' => (bool) $this->get('show_device_counts'),
            'allow_subscriptions' => (bool) $this->get('allow_subscriptions'),
            'public_enabled' => (bool) $this->get('public_enabled'),
            'color_operational' => (string) $this->get('color_operational'),
            'color_degraded' => (string) $this->get('color_degraded'),
            'color_outage' => (string) $this->get('color_outage'),
            'color_unknown' => (string) $this->get('color_unknown'),
            'color_maintenance_scheduled' => (string) $this->get('color_maintenance_scheduled'),
            'color_maintenance_active' => (string) $this->get('color_maintenance_active'),
            'color_maintenance_completed' => (string) $this->get('color_maintenance_completed'),
        ];
    }

    public function save(array $values): array
    {
        foreach (self::DEFAULTS as $key => $default) {
            if (array_key_exists($key, $values)) {
                Setting::updateOrCreate(['key' => 'status_page.'.$key], ['value' => $values[$key]]);
            }
        }
        return $this->publicView();
    }

    private function get(string $key): mixed
    {
        return Setting::where('key', 'status_page.'.$key)->value('value') ?? self::DEFAULTS[$key];
    }
}
