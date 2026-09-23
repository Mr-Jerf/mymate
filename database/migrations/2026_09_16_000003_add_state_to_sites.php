<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->string('state_code', 2)->nullable()->after('address')->index();
        });

        DB::statement("ALTER TABLE sites ADD CONSTRAINT sites_state_code_check CHECK (state_code IS NULL OR state_code IN ('ID', 'KS', 'MO', 'MT', 'UT'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sites DROP CONSTRAINT IF EXISTS sites_state_code_check');

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropIndex(['state_code']);
            $table->dropColumn('state_code');
        });
    }
};
