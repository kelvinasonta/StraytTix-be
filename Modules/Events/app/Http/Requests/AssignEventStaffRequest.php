<?php

namespace Modules\Events\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignEventStaffRequest extends FormRequest
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
            // Single assignment mode - accepts either integer ID or UUID string
            'user_id' => ['required_without:staff', 'nullable'],
            'role' => ['nullable', 'string', 'max:50'],
            'permissions' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:500'],

            // Bulk assignment mode
            'staff' => ['required_without:user_id', 'nullable', 'array', 'min:1'],
            'staff.*.user_id' => ['required_with:staff'],
            'staff.*.role' => ['nullable', 'string', 'max:50'],
            'staff.*.permissions' => ['nullable', 'array'],
            'staff.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
