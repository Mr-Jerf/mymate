<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE sites DROP CONSTRAINT IF EXISTS sites_state_code_check');
        DB::statement("ALTER TABLE sites ADD CONSTRAINT sites_state_code_check CHECK (state_code IS NULL OR state_code IN ('AL','AK','AZ','AR','CA','CO','CT','DE','DC','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sites DROP CONSTRAINT IF EXISTS sites_state_code_check');
        DB::statement("ALTER TABLE sites ADD CONSTRAINT sites_state_code_check CHECK (state_code IS NULL OR state_code IN ('ID', 'KS', 'MO', 'MT', 'UT'))");
    }
};
