<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusNotificationDelivery extends Model
{
    protected $fillable = ['status_subscription_id', 'event_key', 'status', 'attempts', 'sent_at', 'last_error'];
    protected $casts = ['sent_at' => 'datetime'];
    public function subscription(): BelongsTo { return $this->belongsTo(StatusSubscription::class, 'status_subscription_id'); }
}
