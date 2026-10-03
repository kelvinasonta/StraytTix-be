<?php

namespace Modules\Events\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventStaffRequest extends FormRequest
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
            'role' => ['nullable', 'string', 'max:50'],
            'permissions' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
