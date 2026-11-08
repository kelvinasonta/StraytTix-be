<?php

namespace Modules\Organizer\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Organizer\app\Enum\OrganizerMemberRole;

class StoreOrganizerInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'role' => ['required', new Enum(OrganizerMemberRole::class)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ];
    }
}
