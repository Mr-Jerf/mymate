<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class OutageUpdate extends Model
{
    protected $fillable = ['outage_id', 'user_id', 'message'];

    public function outage(): BelongsTo
    {
        return $this->belongsTo(Outage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
