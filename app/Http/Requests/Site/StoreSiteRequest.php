<?php

namespace App\Http\Requests\Site;

use App\Enums\SiteKind;
use App\Services\NetworkStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // authenticated operators only (writes gated to admins by middleware)
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['sometimes', Rule::enum(SiteKind::class)],
            // Both or neither - a half-set coordinate can't be placed on the map.
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'address' => ['nullable', 'string', 'max:255'],
            'state_code' => ['nullable', 'string', 'size:2', Rule::in(array_keys(NetworkStatus::STATES))],
            'external_ref' => ['nullable', 'string', 'max:255', Rule::unique('sites', 'external_ref')],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
