<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StatusPage\StoreStatusSubscriptionRequest;
use App\Models\Site;
use App\Models\StatusSubscription;
use App\Support\StatusPageSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use App\Support\MailSettings;

class StatusSubscriptionController extends Controller
{
    public function store(StoreStatusSubscriptionRequest $request, StatusPageSettings $settings): JsonResponse
    {
        abort_unless($settings->publicView()['allow_subscriptions'], 404);
        $data = $request->validated();
        $site = Site::query()->whereNotNull('state_code')->get()->first(fn (Site $candidate): bool => hash_equals(hash('sha256', 'public-status-site:'.$candidate->id), $data['site_key']));
        if ($site === null) return response()->json(['message' => 'If eligible, a confirmation email will be sent.'], 202);
        $email = strtolower(trim($data['email']));
        $emailHash = hash_hmac('sha256', $email, (string) config('app.key'));
        $existing = StatusSubscription::where('email_hash', $emailHash)->first();
        if ($existing !== null && $existing->site_id !== $site->id) {
            return response()->json(['message' => 'If eligible, a confirmation email will be sent.'], 202);
        }
        $verification = Str::random(64);
        $unsubscribe = Str::random(64);
        try {
            $subscription = StatusSubscription::updateOrCreate(
                ['site_id' => $site->id, 'email_hash' => $emailHash],
                ['email_ciphertext' => Crypt::encryptString($email), 'verification_hash' => hash('sha256', $verification), 'unsubscribe_hash' => hash('sha256', $unsubscribe), 'preferences' => $data['preferences'] ?? ['outage' => true, 'degraded' => true, 'updates' => true, 'resolved' => true, 'maintenance' => true], 'unsubscribed_at' => null],
            );
        } catch (QueryException) {
            return response()->json(['message' => 'If eligible, a confirmation email will be sent.'], 202);
        }
        try {
            $mailer = app(MailSettings::class)->apply();
            $statusPageUrl = rtrim((string) config('services.status_page.url'), '/');
            $verificationUrl = $statusPageUrl.'/api/public/status-subscriptions/verify/'.$verification;
            $unsubscribeUrl = $statusPageUrl.'/api/public/status-subscriptions/unsubscribe/'.$unsubscribe;
            Mail::mailer($mailer)->raw("Confirm your Network status notifications subscription for {$site->name}: {$verificationUrl}\n\nUnsubscribe: {$unsubscribeUrl}", fn ($message) => $message->to($email)->subject('Confirm Network status notifications'));
        } catch (\Throwable) {
            // Keep the public response neutral; the delivery failure is handled by mail logs/ops.
        }
        return response()->json(['message' => 'If eligible, a confirmation email will be sent.'], 202);
    }

    public function verify(string $token): JsonResponse
    {
        $subscription = StatusSubscription::where('verification_hash', hash('sha256', $token))->firstOrFail();
        $subscription->update(['verified_at' => now(), 'verification_hash' => null, 'unsubscribed_at' => null]);
        return response()->json(['message' => 'Subscription confirmed.']);
    }

    public function unsubscribe(string $token): JsonResponse
    {
        $subscription = StatusSubscription::where('unsubscribe_hash', hash('sha256', $token))->firstOrFail();
        $subscription->update(['unsubscribed_at' => now()]);
        return response()->json(['message' => 'You have been unsubscribed from status notifications.']);
    }
}
