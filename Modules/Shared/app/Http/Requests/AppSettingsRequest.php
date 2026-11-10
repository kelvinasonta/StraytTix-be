<?php

namespace Modules\Shared\app\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AppSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sitename' => ['string', 'required'],
            'site_tagline' => ['string', 'required'],
            'favicon' => ['string', 'nullable'],
            'logo' => ['string', 'nullable'],
            'maintenance_message' => ['text', 'nullable'],
            'support_email' => ['email', 'nullable'],
            'timezone' => ['string', 'nullable'],
            'currency_id' => ['integer', 'nullable'],
            'platform_fee_percentage' => ['numeric', 'min:0', 'max:100', 'nullable'],
            'platform_fee_fixed' => ['numeric', 'min:0', 'nullable'],
            'platform_fee_passthrough' => ['boolean', 'nullable'],
            'ticket_fee_percentage' => ['numeric', 'min:0', 'max:100', 'nullable'],
            'ticket_fee_fixed' => ['numeric', 'min:0', 'nullable'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sitename.required' => 'Website should have a name',
            'site_tagline.required' => 'Site tag line required',
        ];
    }
}
