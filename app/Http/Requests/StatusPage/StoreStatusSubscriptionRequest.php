<?php

namespace App\Http\Requests\StatusPage;

use Illuminate\Foundation\Http\FormRequest;

class StoreStatusSubscriptionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'site_key' => ['required', 'string', 'size:64'],
            'email' => ['required', 'email:rfc,dns', 'max:320'],
            'preferences' => ['sometimes', 'array'],
            'preferences.outage' => ['sometimes', 'boolean'],
            'preferences.degraded' => ['sometimes', 'boolean'],
            'preferences.updates' => ['sometimes', 'boolean'],
            'preferences.resolved' => ['sometimes', 'boolean'],
            'preferences.maintenance' => ['sometimes', 'boolean'],
        ];
    }
}
