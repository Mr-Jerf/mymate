<?php

namespace App\Jobs;

use App\Models\StatusNotificationDelivery;
use App\Models\StatusSubscription;
use App\Support\MailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

class SendStatusNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $subscriptionId,
        public string $eventKey,
        public string $subject,
        public string $body,
    ) {}

    public function handle(MailSettings $mail): void
    {
        $subscription = StatusSubscription::query()->find($this->subscriptionId);
        if ($subscription === null || $subscription->verified_at === null || $subscription->unsubscribed_at !== null) return;
        $delivery = StatusNotificationDelivery::firstOrCreate(
            ['status_subscription_id' => $subscription->id, 'event_key' => $this->eventKey],
            ['status' => 'queued'],
        );
        if ($delivery->status === 'sent') return;
        $delivery->increment('attempts');
        $email = Crypt::decryptString($subscription->email_ciphertext);
        $mailer = $mail->apply();
        Mail::mailer($mailer)->raw($this->body, fn ($message) => $message->to($email)->subject($this->subject));
        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
    }

    public function failed(\Throwable $exception): void
    {
        StatusNotificationDelivery::where('status_subscription_id', $this->subscriptionId)
            ->where('event_key', $this->eventKey)
            ->update(['status' => 'failed', 'last_error' => substr($exception->getMessage(), 0, 1000)]);
    }
}
