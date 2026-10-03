<?php

namespace Modules\Events\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DiscoverEventsRequest extends FormRequest
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
            'query' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:64'],
            'city' => ['nullable', 'string', 'max:64'],
            'state' => ['nullable', 'string', 'max:64'],
            'event_type' => ['nullable', 'string', 'in:physical,online,hybrid,virtual'],
            'date' => ['nullable', 'string', 'in:today,tomorrow,this_weekend,this_month'],
            'price_range' => ['nullable', 'string', 'in:free,paid,under_5k,under_20k'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', 'min:1', 'max:500'],
            'sort' => ['nullable', 'string', 'in:recommended,upcoming,popular'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
