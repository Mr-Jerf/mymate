<?php

namespace App\Http\Requests\StatusIncident;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusIncidentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['investigating', 'monitoring', 'resolved'])],
            'summary' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
