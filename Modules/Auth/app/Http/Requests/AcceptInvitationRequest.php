<?php

namespace Modules\Auth\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'first_name' => ['required_without:user_id', 'string', 'min:2', 'max:255'],
            'last_name' => ['required_without:user_id', 'string', 'min:2', 'max:255'],
            'password' => [
                'required_without:user_id',
                'string',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ];
    }
}
