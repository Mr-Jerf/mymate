<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatusIncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'state' => $this->state_code,
            'severity' => $this->severity,
            'status' => $this->status,
            'summary' => $this->summary,
            'started_at' => $this->started_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'acknowledged' => $this->acknowledged_at !== null,
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'acknowledged_by' => $this->acknowledgedBy?->name,
        ];
    }
}
