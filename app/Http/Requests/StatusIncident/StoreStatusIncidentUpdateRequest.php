<?php

namespace App\Http\Requests\StatusIncident;

use Illuminate\Foundation\Http\FormRequest;

class StoreStatusIncidentUpdateRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['message' => ['required', 'string', 'min:2', 'max:4000']]; }
}
