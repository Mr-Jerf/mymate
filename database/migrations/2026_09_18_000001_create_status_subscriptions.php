<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->text('email_ciphertext');
            $table->string('email_hash', 64);
            $table->string('verification_hash', 64)->nullable()->unique();
            $table->string('unsubscribe_hash', 64)->unique();
            $table->json('preferences');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'email_hash']);
            $table->index(['site_id', 'verified_at', 'unsubscribed_at']);
        });

        Schema::create('status_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('status_subscription_id')->constrained('status_subscriptions')->cascadeOnDelete();
            $table->string('event_key', 160);
            $table->string('status', 16)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['status_subscription_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_notification_deliveries');
        Schema::dropIfExists('status_subscriptions');
    }
};
