<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('status_subscriptions')->select('email_hash')->groupBy('email_hash')->havingRaw('COUNT(*) > 1')->pluck('email_hash')->each(function (string $emailHash): void {
            $duplicateIds = DB::table('status_subscriptions')->where('email_hash', $emailHash)->orderBy('id')->pluck('id')->slice(1);
            if ($duplicateIds->isNotEmpty()) {
                DB::table('status_subscriptions')->whereIn('id', $duplicateIds->all())->delete();
            }
        });

        Schema::table('status_subscriptions', function (Blueprint $table): void {
            $table->unique('email_hash', 'status_subscriptions_email_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('status_subscriptions', function (Blueprint $table): void {
            $table->dropUnique('status_subscriptions_email_hash_unique');
        });
    }
};
