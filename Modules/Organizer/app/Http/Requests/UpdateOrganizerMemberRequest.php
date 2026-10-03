<?php

namespace Modules\Organizer\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Organizer\app\Enum\OrganizerMemberRole;

class UpdateOrganizerMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', new Enum(OrganizerMemberRole::class)],
        ];
    }
}
