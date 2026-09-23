<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('status_incidents', function (Blueprint $table): void {
            $table->timestamp('monitoring_started_at')->nullable()->after('started_at');
            $table->timestamp('monitoring_until')->nullable()->after('monitoring_started_at');
            $table->index(['status', 'monitoring_until']);
        });
    }

    public function down(): void
    {
        Schema::table('status_incidents', function (Blueprint $table): void {
            $table->dropIndex(['status', 'monitoring_until']);
            $table->dropColumn(['monitoring_started_at', 'monitoring_until']);
        });
    }
};
