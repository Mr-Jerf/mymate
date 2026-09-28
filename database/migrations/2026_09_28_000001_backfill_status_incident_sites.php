<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Recover the public site association for legacy state-only incidents when the
     * recorded outage devices identify exactly one site. Ambiguous incidents are
     * deliberately left unassigned rather than exposing or inventing an impact.
     */
    public function up(): void
    {
        $candidates = DB::table('outages as o')
            ->join('devices as d', function (JoinClause $join): void {
                $join->on('d.id', '=', 'o.device_id')->whereNotNull('d.site_id');
            })
            ->whereNotNull('o.status_incident_id')
            ->groupBy('o.status_incident_id')
            ->havingRaw('COUNT(DISTINCT d.site_id) = 1')
            ->selectRaw('o.status_incident_id, MIN(d.site_id) as site_id')
            ->pluck('site_id', 'status_incident_id');

        foreach ($candidates as $incidentId => $siteId) {
            DB::table('status_incidents')
                ->where('id', $incidentId)
                ->whereNull('site_id')
                ->update(['site_id' => $siteId]);
        }
    }

    public function down(): void
    {
        // The backfill is intentionally non-destructive and cannot safely identify
        // which site_id values were present before this migration.
    }
};
