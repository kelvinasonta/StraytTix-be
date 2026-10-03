<?php

declare(strict_types=1);

namespace Modules\Events\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Models\EventFormat;

class EventMetadataController extends Controller
{
    private const DEFAULT_FORMATS = [
        ['name' => 'Conference', 'slug' => 'conference'],
        ['name' => 'Workshop', 'slug' => 'workshop'],
        ['name' => 'Seminar', 'slug' => 'seminar'],
        ['name' => 'Festival', 'slug' => 'festival'],
        ['name' => 'Meetup', 'slug' => 'meetup'],
        ['name' => 'Concert', 'slug' => 'concert'],
        ['name' => 'Webinar', 'slug' => 'webinar'],
        ['name' => 'Summit', 'slug' => 'summit'],
        ['name' => 'Networking', 'slug' => 'networking'],
        ['name' => 'Hackathon', 'slug' => 'hackathon'],
        ['name' => 'Masterclass', 'slug' => 'masterclass'],
        ['name' => 'Roundtable', 'slug' => 'roundtable'],
        ['name' => 'Exhibition / Expo', 'slug' => 'exhibition-expo'],
        ['name' => 'Party', 'slug' => 'party'],
    ];

    private const DEFAULT_CATEGORIES = [
        'Technology',
        'Business & Entrepreneurship',
        'Design & Creative',
        'Music & Entertainment',
        'Health & Wellness',
        'Education & Training',
        'Sports & Fitness',
        'Community & Culture',
        'Science & Engineering',
        'Food & Beverage',
        'Career & Networking',
    ];

    /**
     * Retrieve list of event formats.
     */
    public function eventFormats(): JsonResponse
    {
        $formats = EventFormat::orderBy('name')->get();

        if ($formats->isEmpty()) {
            foreach (self::DEFAULT_FORMATS as $item) {
                EventFormat::firstOrCreate(
                    ['slug' => $item['slug']],
                    ['name' => $item['name']]
                );
            }
            $formats = EventFormat::orderBy('name')->get();
        }

        $data = $formats->map(fn (EventFormat $format) => [
            'id' => $format->uuid,
            'name' => $format->name,
            'slug' => $format->slug,
        ])->values()->all();

        return ApiResponse::success(
            'Event formats retrieved successfully.',
            $data
        );
    }

    /**
     * Retrieve list of supported event types (physical, virtual, hybrid).
     */
    public function eventTypes(): JsonResponse
    {
        $types = array_map(fn (EventType $type) => [
            'key' => $type->value,
            'name' => ucfirst($type->value),
        ], EventType::cases());

        return ApiResponse::success(
            'Event types retrieved successfully.',
            $types
        );
    }

    /**
     * Retrieve standard event categories.
     */
    public function categories(): JsonResponse
    {
        return ApiResponse::success(
            'Event categories retrieved successfully.',
            self::DEFAULT_CATEGORIES
        );
    }
}
