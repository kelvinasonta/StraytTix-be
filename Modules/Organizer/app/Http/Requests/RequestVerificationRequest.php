<?php

declare(strict_types=1);

namespace Modules\Organizer\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'official_email' => ['nullable', 'email', 'max:255'],
            'official_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'documents' => ['required', 'array', 'min:1'],
            'documents.*.type' => ['required', 'string', 'max:100'],
            'documents.*.url' => ['required', 'string', 'url'],
            'documents.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
