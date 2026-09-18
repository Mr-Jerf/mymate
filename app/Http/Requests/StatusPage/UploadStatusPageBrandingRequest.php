<?php

namespace App\Http\Requests\StatusPage;

use Illuminate\Foundation\Http\FormRequest;

class UploadStatusPageBrandingRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdmin() === true; }

    public function rules(): array
    {
        return [
            'asset' => ['required', 'file', 'max:512', 'mimetypes:image/png,image/jpeg,image/webp,image/x-icon,image/vnd.microsoft.icon'],
            'kind' => ['required', 'in:logo,favicon'],
        ];
    }
}
