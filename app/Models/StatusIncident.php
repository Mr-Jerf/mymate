<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatusIncident extends Model
{
    protected $fillable = ['site_id', 'state_code', 'severity', 'status', 'summary', 'started_at', 'monitoring_started_at', 'monitoring_until', 'resolved_at'];

    protected $casts = ['started_at' => 'datetime', 'monitoring_started_at' => 'datetime', 'monitoring_until' => 'datetime', 'resolved_at' => 'datetime'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function outages(): HasMany
    {
        return $this->hasMany(Outage::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(StatusIncidentUpdate::class);
    }
}
