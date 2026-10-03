<?php

namespace Modules\Events\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'eventCountry' => ['nullable', 'string', 'max:255'],
            'eventType' => ['sometimes', new Enum(EventType::class)],
            'eventFormat' => ['nullable', 'string', 'max:255'],
            'eventCategory' => ['nullable', 'array', 'max:5'],
            'eventCategory.*' => ['string', 'max:100'],
            'status' => ['sometimes', new Enum(EventStatus::class)],
            'visibility' => ['sometimes', new Enum(EventVisibility::class)],
            'bannerUrl' => ['nullable', 'url', 'max:2048'],

            'venueName' => ['nullable', 'string', 'max:255'],
            'venueAddress' => ['nullable', 'string'],
            'streetAddress' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:50'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'onlineMeetingUrl' => ['nullable', 'url', 'max:2048'],

            'scheduleType' => ['sometimes', 'string', 'in:single,recurring'],
            'scheduleTimezone' => ['nullable', 'string', 'timezone:all'],
            'eventFrequency' => ['nullable', 'string', 'in:daily,weekly,monthly,custom'],
            'startDate' => ['nullable', 'date'],
            'startTime' => ['nullable', 'date_format:H:i'],
            'endDate' => ['nullable', 'date'],
            'endTime' => ['nullable', 'date_format:H:i'],
            'recurringEndType' => ['nullable', 'string', 'in:on_date,after_occurrences'],
            'recurringEndDate' => ['nullable', 'date'],
            'recurringOccurrences' => ['nullable', 'integer', 'min:1', 'max:100'],

            'schedules' => ['nullable', 'array'],
            'schedules.*.day' => ['nullable', 'string', 'max:255'],
            'schedules.*.title' => ['nullable', 'string', 'max:255'],
            'schedules.*.description' => ['nullable', 'string'],
            'schedules.*.startTime' => ['nullable', 'date_format:H:i'],
            'schedules.*.endTime' => ['nullable', 'date_format:H:i'],

            'lineup' => ['nullable', 'array'],
            'lineup.*.name' => ['nullable', 'string', 'max:255'],
            'lineup.*.role' => ['nullable', 'string', 'max:255'],
            'lineup.*.bio' => ['nullable', 'string'],
            'lineup.*.avatarUrl' => ['nullable', 'url', 'max:2048'],
            'lineup.*.links' => ['nullable', 'array'],

            'isFeatured' => ['sometimes', 'boolean'],

            'faqs' => ['nullable', 'array'],
            'faqs.*.question' => ['sometimes', 'required', 'string', 'max:255'],
            'faqs.*.answer' => ['sometimes', 'required', 'string'],

            'galleryUrls' => ['nullable', 'array'],
            'galleryUrls.*' => ['url', 'max:2048'],

            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['url', 'max:2048'],

            // Virtual streaming & instructions
            'streamProvider' => ['nullable', 'string', 'max:255'],
            'streamInstructions' => ['nullable', 'string'],

            // Policies & restrictions
            'refundPolicy' => ['nullable', 'string'],
            'refundOption' => ['nullable', 'string', 'max:50'],
            'ageRestriction' => ['nullable', 'string', 'max:50'],
            'dressCode' => ['nullable', 'string', 'max:100'],
            'eventRules' => ['nullable', 'string'],

            // Amenities
            'amenities' => ['nullable', 'array'],

            // Confirmation & Status messages
            'confirmationEmailMsg' => ['nullable', 'string'],
            'statusMessage' => ['nullable', 'string'],
            'isStatusMsgEnabled' => ['nullable', 'boolean'],
            'statusMessageType' => ['nullable', 'string', 'max:50'],
            'preSaleMessage' => ['nullable', 'string'],
            'postSaleMessage' => ['nullable', 'string'],

            // Theme & Style
            'themeColor' => ['nullable', 'string', 'max:50'],
            'bannerStyle' => ['nullable', 'string', 'max:50'],

            // Settings object (if passed as nested dictionary)
            'settings' => ['nullable', 'array'],

            // Custom Questions
            'customQuestions' => ['nullable', 'array'],
            'customQuestions.*.id' => ['nullable', 'string', 'max:255'],
            'customQuestions.*.question' => ['required_with:customQuestions', 'string', 'max:255'],
            'customQuestions.*.type' => ['nullable', 'string', 'max:50'],
            'customQuestions.*.options' => ['nullable'],
            'customQuestions.*.isRequired' => ['nullable', 'boolean'],
            'customQuestions.*.sortOrder' => ['nullable', 'integer'],

            // Promoters
            'promoters' => ['nullable', 'array'],
            'promoters.*.id' => ['nullable', 'string', 'max:255'],
            'promoters.*.code' => ['required_with:promoters', 'string', 'max:100'],
            'promoters.*.discount' => ['nullable', 'numeric', 'min:0'],
            'promoters.*.discountType' => ['nullable', 'string', 'in:percentage,fixed'],
            'promoters.*.uses' => ['nullable', 'integer', 'min:0'],
            'promoters.*.sales' => ['nullable', 'numeric', 'min:0'],
            'promoters.*.maxUses' => ['nullable', 'integer', 'min:1'],
            'promoters.*.status' => ['nullable', 'string', 'in:active,inactive'],
            'promoters.*.sortOrder' => ['nullable', 'integer'],

            'meta' => ['nullable', 'array'],
        ];
    }
}
