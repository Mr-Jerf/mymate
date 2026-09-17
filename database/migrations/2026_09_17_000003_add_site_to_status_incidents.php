<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('status_incidents', function (Blueprint $table): void {
            $table->foreignId('site_id')->nullable()->after('id')->constrained('sites')->nullOnDelete();
            $table->index(['site_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::table('status_incidents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('site_id');
        });
    }
};
