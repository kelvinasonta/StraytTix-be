<?php

namespace Modules\Auth\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        if (is_string($this->email)) {
            $this->merge([
                // Convert the email to lowercase before validation
                'email' => Str::lower($this->email),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|email:rfc',
            'password' => [
                'required',
                'string',
                'min:8',
            ],
            'remember' => 'nullable|boolean',
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
            'email.required' => 'Email is required',
            'email.string' => 'Email must be a string',
            'email.unique' => 'Email is already taken',
            'email.email' => 'Email must be a valid email address',
            'password.required' => 'Password is required',
            'password.min' => 'Your password must be at least 8 characters.',
            'password.letters' => 'Your password must contain at least one letter.',
            'password.mixed' => 'Your password must contain both uppercase and lowercase letters.',
            'password.numbers' => 'Your password must contain at least one number.',
            'password.symbols' => 'Your password must contain at least one symbol (like ! @ # $).',
        ];
    }
}
