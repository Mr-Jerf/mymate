<?php

namespace App\Http\Requests\Outage;

use Illuminate\Foundation\Http\FormRequest;

class StoreOutageUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['message' => ['required', 'string', 'min:2', 'max:4000']];
    }
}
