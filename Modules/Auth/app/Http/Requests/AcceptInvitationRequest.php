<?php

namespace Modules\Auth\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Models\OrganizerInvitation;

class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $token = (string) $this->input('token', '');
        $userAlreadyExists = false;

        if ($token !== '') {
            $tokenHash = hash('sha256', $token);
            $email = OrganizerInvitation::where('token', $tokenHash)
                ->orWhere('token', $token)
                ->value('email');

            if ($email) {
                $userAlreadyExists = User::where('email', $email)->exists();
            }
        }

        $requiredRule = $userAlreadyExists ? 'nullable' : 'required_without:user_id';

        return [
            'token' => ['required', 'string'],
            'first_name' => [$requiredRule, 'string', 'min:2', 'max:255'],
            'last_name' => [$requiredRule, 'string', 'min:2', 'max:255'],
            'password' => [
                $requiredRule,
                'string',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ];
    }
}
