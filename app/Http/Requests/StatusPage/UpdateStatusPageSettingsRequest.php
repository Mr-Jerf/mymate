<?php

namespace App\Http\Requests\StatusPage;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusPageSettingsRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdmin() === true; }

    public function rules(): array
    {
        return [
            'brand_name' => ['sometimes', 'string', 'max:120'],
            'subtitle' => ['sometimes', 'string', 'max:240'],
            'poll_ms' => ['sometimes', 'integer', 'min:10000', 'max:300000'],
            'show_site_names' => ['sometimes', 'boolean'],
            'show_device_counts' => ['sometimes', 'boolean'],
            'allow_subscriptions' => ['sometimes', 'boolean'],
            'public_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
