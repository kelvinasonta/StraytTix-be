<?php

namespace Modules\Organizer\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Organizer\app\Enum\OrganizerType;

class UpdateOrganizerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', new Enum(OrganizerType::class)],
            'description' => ['nullable', 'string'],
            'bio' => ['nullable', 'string'],
            'logo' => ['nullable', 'string', 'max:1000'],
            'logo_url' => ['nullable', 'string', 'max:1000'],
            'cover_photo' => ['nullable', 'string', 'max:1000'],
            'cover_image' => ['nullable', 'string', 'max:1000'],
            'banner_url' => ['nullable', 'string', 'max:1000'],
            'bannerUrl' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
