<?php

namespace Modules\Auth\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'token' => 'required|string',
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Token is required',
            'password.required' => 'Password is required',
            'password.min' => 'Your password must be at least 8 characters.',
            'password.letters' => 'Your password must contain at least one letter.',
            'password.mixed' => 'Your password must contain both uppercase and lowercase letters.',
            'password.numbers' => 'Your password must contain at least one number.',
            'password.symbols' => 'Your password must contain at least one symbol (like ! @ # $).',
        ];
    }
}
