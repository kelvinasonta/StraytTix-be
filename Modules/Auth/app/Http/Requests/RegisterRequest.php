<?php

namespace Modules\Auth\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'first_name' => 'required|string|min:2|max:255',
            'last_name' => 'required|string|min:2|max:255',
            'email' => 'required|string|unique:users,email|email:rfc',
            'phone' => ['nullable', 'string', 'regex:/^\+?[1-9]\d{1,14}$/'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'account_type' => 'required|in:customer,organizer',
            'organizer_type' => 'required_if:account_type,organizer|nullable|string|in:individual,organization',
            'organization_name' => 'required_if:organizer_type,organization|nullable|string|min:3|max:255',
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
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'first_name.min' => 'First name must be at least 2 characters',
            'last_name.min' => 'Last name must be at least 2 characters',
            'first_name.max' => 'First name must not exceed 255 characters',
            'last_name.max' => 'Last name must not exceed 255 characters',
            'email.required' => 'Email is required',
            'email.string' => 'Email must be a string',
            'email.unique' => 'Email is already taken',
            'email.email' => 'Email must be a valid email address',
            'phone.regex' => 'Phone number must be in E.164 format (e.g., +1234567890)',
            'timezone.timezone' => 'Timezone must be a valid timezone',
            'account_type.required' => 'Account type is required',
            'account_type.in' => 'Account type must be either a customer account or an  organization account',
            'organizer_type.required_if' => 'Organizer type is required when you select organization account',
            'organizer_type.in' => 'Organizer type must be either an individual account or an organization account',
            'organization_name.required_if' => 'Organization name is required when you select an organization account',
            'organization_name.min' => 'Organization name must be at least 3 characters',
            'organization_name.max' => 'Organization name must not exceed 255 characters',
            'password.required' => 'Password is required',
            'password.min' => 'Your password must be at least 8 characters.',
            'password.letters' => 'Your password must contain at least one letter.',
            'password.mixed' => 'Your password must contain both uppercase and lowercase letters.',
            'password.numbers' => 'Your password must contain at least one number.',
            'password.symbols' => 'Your password must contain at least one symbol (like ! @ # $).',
        ];
    }
}
