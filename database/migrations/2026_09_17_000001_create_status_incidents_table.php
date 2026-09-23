<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_incidents', function (Blueprint $table): void {
            $table->id();
            $table->string('state_code', 2);
            $table->string('severity', 12); // outage | degraded
            $table->string('status', 16)->default('investigating'); // investigating | monitoring | resolved
            $table->string('summary')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['state_code', 'resolved_at']);
        });

        Schema::table('outages', function (Blueprint $table): void {
            $table->foreignId('status_incident_id')->nullable()->after('device_id')
                ->constrained('status_incidents')->nullOnDelete();
            $table->index('status_incident_id');
        });
    }

    public function down(): void
    {
        Schema::table('outages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('status_incident_id');
        });
        Schema::dropIfExists('status_incidents');
    }
};
