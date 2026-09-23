<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $duplicates = DB::table('status_subscriptions')
                ->select('email_hash')
                ->groupBy('email_hash')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('email_hash');

            if ($duplicates->isNotEmpty()) {
                throw new RuntimeException('Cannot enforce global status subscription email uniqueness: duplicate subscription records require explicit operator resolution.');
            }

            Schema::table('status_subscriptions', function (Blueprint $table): void {
                $table->unique('email_hash', 'status_subscriptions_email_hash_unique');
            });
        });
    }

    public function down(): void
    {
        Schema::table('status_subscriptions', function (Blueprint $table): void {
            $table->dropUnique('status_subscriptions_email_hash_unique');
        });
    }
};
