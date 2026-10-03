<?php

namespace Modules\Events\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'eventCountry' => ['required', 'string', 'max:255'],
            'eventType' => ['required', new Enum(EventType::class)],

            // Physical / In-person Location details
            'eventLocationId' => ['nullable', 'string', 'max:50'],
            'venueName' => ['required_if:eventType,physical,hybrid', 'nullable', 'string', 'max:255'],
            'venueAddress' => ['nullable', 'string', 'max:500'],
            'streetAddress' => ['nullable', 'string', 'max:255'],
            'city' => ['required_if:eventType,physical,hybrid', 'nullable', 'string', 'max:255'],
            'state' => ['required_if:eventType,physical,hybrid', 'nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:50'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],

            // Virtual details
            'onlineMeetingUrl' => ['required_if:eventType,virtual', 'nullable', 'url', 'max:2048'],

            // Event format & categories
            'eventFormat' => ['nullable', 'string', 'max:255'],
            'eventCategory' => ['nullable', 'array', 'max:5'],
            'eventCategory.*' => ['string', 'max:100'],

            // Event Schedule
            'scheduleType' => ['required', 'string', 'in:single,recurring'],
            'scheduleTimezone' => ['required', 'string', 'timezone:all'],
            'eventFrequency' => ['required_if:scheduleType,recurring', 'nullable', 'string', 'in:daily,weekly,monthly,custom'],
            'startDate' => ['required', 'date'],
            'startTime' => ['required', 'date_format:H:i'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'endTime' => ['required', 'date_format:H:i'],

            // Recurring details (if recurring event)
            'recurringEndType' => ['required_if:scheduleType,recurring', 'nullable', 'string', 'in:on_date,after_occurrences'],
            'recurringEndDate' => ['required_if:recurringEndType,on_date', 'nullable', 'date', 'after:startDate'],
            'recurringOccurrences' => ['required_if:recurringEndType,after_occurrences', 'nullable', 'integer', 'min:1', 'max:100'],

            // Agenda / Schedule items (optional)
            'schedules' => ['nullable', 'array'],
            'schedules.*.day' => ['required_with:schedules', 'string', 'max:255'],
            'schedules.*.title' => ['required_with:schedules', 'string', 'max:255'],
            'schedules.*.description' => ['nullable', 'string'],
            'schedules.*.startTime' => ['required_with:schedules', 'date_format:H:i'],
            'schedules.*.endTime' => ['required_with:schedules', 'date_format:H:i'],

            // Event Lineup (speakers, performers, hosts, etc.)
            'lineup' => ['nullable', 'array'],
            'lineup.*.name' => ['required_with:lineup', 'string', 'max:255'],
            'lineup.*.role' => ['nullable', 'string', 'max:255'],
            'lineup.*.bio' => ['nullable', 'string'],
            'lineup.*.avatarUrl' => ['nullable', 'url', 'max:2048'],
            'lineup.*.links' => ['nullable', 'array'],
            'lineup.*.links.*.platform' => ['string', 'max:255'],
            'lineup.*.links.*.url' => ['url', 'max:2048'],

            // Status & Visibility
            'status' => ['sometimes', new Enum(EventStatus::class)],

            'visibility' => ['sometimes', new Enum(EventVisibility::class)],
            'bannerUrl' => ['nullable', 'url', 'max:2048'],

            'isFeatured' => ['nullable', 'boolean'],

            'faqs' => ['nullable', 'array'],
            'faqs.*.question' => ['required_with:faqs', 'string', 'max:255'],
            'faqs.*.answer' => ['required_with:faqs', 'string'],

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
