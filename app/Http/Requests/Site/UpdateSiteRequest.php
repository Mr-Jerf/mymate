<?php

namespace App\Http\Requests\Site;

use App\Enums\SiteKind;
use App\Services\NetworkStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'kind' => ['sometimes', Rule::enum(SiteKind::class)],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state_code' => ['sometimes', 'nullable', 'string', 'size:2', Rule::in(array_keys(NetworkStatus::STATES))],
            'external_ref' => [
                'sometimes', 'nullable', 'string', 'max:255',
                Rule::unique('sites', 'external_ref')->ignore($this->route('site')?->id),
            ],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
