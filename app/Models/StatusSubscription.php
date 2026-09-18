<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatusSubscription extends Model
{
    protected $fillable = ['site_id', 'email_ciphertext', 'email_hash', 'verification_hash', 'unsubscribe_hash', 'preferences', 'verified_at', 'unsubscribed_at'];
    protected $casts = ['preferences' => 'array', 'verified_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
    public function deliveries(): HasMany { return $this->hasMany(StatusNotificationDelivery::class); }
}
