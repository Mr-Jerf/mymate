<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('status_incidents', function (Blueprint $table): void {
            $table->timestamp('acknowledged_at')->nullable()->after('resolved_at');
            $table->foreignId('acknowledged_by_id')->nullable()->after('acknowledged_at')->constrained('users')->nullOnDelete();
            $table->index(['acknowledged_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('status_incidents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('acknowledged_by_id');
            $table->dropColumn('acknowledged_at');
        });
    }
};
