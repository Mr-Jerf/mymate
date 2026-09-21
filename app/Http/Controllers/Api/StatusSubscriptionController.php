<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StatusPage\StoreStatusSubscriptionRequest;
use App\Models\Site;
use App\Models\StatusSubscription;
use App\Support\StatusPageSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function verify(string $token): Response
    {
        $subscription = StatusSubscription::where('verification_hash', hash('sha256', $token))->first();
        if ($subscription === null) {
            return $this->subscriptionPage('Link unavailable', 'This confirmation link is invalid or has already been used.', 404);
        }
        $subscription->update(['verified_at' => now(), 'verification_hash' => null, 'unsubscribed_at' => null]);
        return $this->subscriptionPage('Subscription confirmed', 'You’re all set to receive Network status notifications.');
    }

    public function unsubscribe(string $token): Response
    {
        $subscription = $this->subscriptionForUnsubscribeToken($token);
        if ($subscription === null) {
            return $this->subscriptionPage('Link unavailable', 'This unsubscribe link is invalid or has already been used.', 404);
        }
        $subscription->update(['unsubscribed_at' => now()]);
        return $this->subscriptionPage('You’ve been unsubscribed', 'You will no longer receive Network status notifications for this subscription.');
    }

    public function manage(string $token): Response
    {
        $subscription = $this->subscriptionForUnsubscribeToken($token);
        if ($subscription === null) {
            return $this->subscriptionPage('Link unavailable', 'This preferences link is invalid or has already been used.', 404);
        }

        return $this->preferencesPage($token, $subscription->preferences ?? []);
    }

    public function updatePreferences(Request $request, string $token): Response
    {
        $subscription = $this->subscriptionForUnsubscribeToken($token);
        if ($subscription === null) {
            return $this->subscriptionPage('Link unavailable', 'This preferences link is invalid or has already been used.', 404);
        }

        $preferences = $request->validate([
            'outage' => ['sometimes', 'boolean'],
            'degraded' => ['sometimes', 'boolean'],
            'updates' => ['sometimes', 'boolean'],
            'resolved' => ['sometimes', 'boolean'],
            'maintenance' => ['sometimes', 'boolean'],
        ]);
        $subscription->update(['preferences' => collect(['outage', 'degraded', 'updates', 'resolved', 'maintenance'])->mapWithKeys(fn (string $key): array => [$key => (bool) ($preferences[$key] ?? false)])->all()]);

        return $this->subscriptionPage('Preferences saved', 'Your Network status notification preferences have been updated.');
    }

    private function subscriptionForUnsubscribeToken(string $token): ?StatusSubscription
    {
        $hash = hash('sha256', $token);
        return StatusSubscription::query()->where(function ($query) use ($token, $hash): void {
            $query->where('unsubscribe_hash', $hash)->orWhere('unsubscribe_hash', $token);
        })->first();
    }

    /** @param array<string, mixed> $preferences */
    private function preferencesPage(string $token, array $preferences): Response
    {
        $safeToken = e($token);
        $options = '';
        foreach ([
            'outage' => 'Outages',
            'degraded' => 'Degraded service',
            'updates' => 'Status updates',
            'resolved' => 'Resolved notifications',
            'maintenance' => 'Maintenance notifications',
        ] as $key => $label) {
            $checked = ($preferences[$key] ?? false) === true ? ' checked' : '';
            $options .= '<label><input type="checkbox" name="'.$key.'" value="1"'.$checked.'> '.e($label).'</label>';
        }
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Notification preferences · Network Status</title><style>:root{color-scheme:dark}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#070a12;color:#f8fafc;font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}.card{width:min(520px,100%);padding:32px 30px;border:1px solid #27344b;border-radius:20px;background:#101827;box-shadow:0 24px 70px #0008}.card h1{margin:0;font-size:24px;letter-spacing:-.03em}.card p{color:#aab7cb;margin:10px 0 22px}.options{display:grid;gap:13px;margin:20px 0}.options label{display:flex;gap:10px;align-items:center;color:#e2e8f0}.options input{accent-color:#34d399}.button{border:0;border-radius:999px;background:#34d399;color:#052e1b;padding:11px 16px;font-weight:700;cursor:pointer}.brand{margin-top:24px;color:#64748b;font-size:12px}</style></head><body><main class="card"><h1>Notification preferences</h1><p>Choose the Network status updates you want to receive.</p><form method="post" action="/api/public/status-subscriptions/manage/'.$safeToken.'"><div class="options">'.$options.'</div><button class="button" type="submit">Save preferences</button></form><div class="brand">Network Status</div></main></body></html>';
        return response($html)->header('Content-Type', 'text/html; charset=UTF-8')->header('Cache-Control', 'no-store');
    }

    private function subscriptionPage(string $title, string $message, int $status = 200): Response
    {
        $safeTitle = e($title);
        $safeMessage = e($message);
        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$safeTitle} · Network Status</title>
<style>
:root{color-scheme:dark}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#070a12;color:#f8fafc;font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}.card{width:min(520px,100%);padding:36px 30px;border:1px solid #27344b;border-radius:20px;background:#101827;box-shadow:0 24px 70px #0008;text-align:center}.mark{width:52px;height:52px;margin:0 auto 18px;display:grid;place-items:center;border-radius:50%;background:#34d39922;color:#34d399;font-size:28px}.card h1{margin:0;font-size:24px;letter-spacing:-.03em}.card p{margin:12px 0 0;color:#aab7cb}.brand{margin-top:26px;color:#64748b;font-size:12px}
</style>
</head><body><main class="card"><div class="mark" aria-hidden="true">✓</div><h1>{$safeTitle}</h1><p>{$safeMessage}</p><div class="brand">Network Status</div></main></body></html>
HTML;
        return response($html, $status)->header('Content-Type', 'text/html; charset=UTF-8')->header('Cache-Control', 'no-store');
    }
}
