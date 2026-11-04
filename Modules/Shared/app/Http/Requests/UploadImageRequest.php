<?php

namespace Modules\Shared\app\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadImageRequest extends FormRequest
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
            'image' => ['required_without:file', 'nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp'],
            'file' => ['required_without:image', 'nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp'],
            'preset' => ['nullable', 'string', 'in:avatar,event_banner,organizer_logo,general'],
            'disk' => ['nullable', 'string', 'in:public,s3,local'],
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
            'image.required_without' => 'Please provide an image file to upload.',
            'file.required_without' => 'Please provide an image file to upload.',
            'image.mimes' => 'The image must be a file of type: jpeg, jpg, png, webp.',
            'file.mimes' => 'The file must be an image of type: jpeg, jpg, png, webp.',
            'preset.in' => 'The selected preset is invalid. Supported presets: avatar, event_banner, organizer_logo, general.',
            'disk.in' => 'The selected storage disk is invalid.',
        ];
    }
}
