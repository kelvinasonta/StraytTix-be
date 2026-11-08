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

    private const DEFAULT_COUNTRIES = [
        [
            'code' => 'NG',
            'iso2' => 'NG',
            'iso3' => 'NGA',
            'name' => 'Nigeria',
            'dial_code' => '+234',
            'currency' => 'NGN',
            'currency_symbol' => '₦',
            'flag' => '🇳🇬',
        ],
        [
            'code' => 'GH',
            'iso2' => 'GH',
            'iso3' => 'GHA',
            'name' => 'Ghana',
            'dial_code' => '+233',
            'currency' => 'GHS',
            'currency_symbol' => 'GH₵',
            'flag' => '🇬🇭',
        ],
        [
            'code' => 'KE',
            'iso2' => 'KE',
            'iso3' => 'KEN',
            'name' => 'Kenya',
            'dial_code' => '+254',
            'currency' => 'KES',
            'currency_symbol' => 'KSh',
            'flag' => '🇰🇪',
        ],
        [
            'code' => 'ZA',
            'iso2' => 'ZA',
            'iso3' => 'ZAF',
            'name' => 'South Africa',
            'dial_code' => '+27',
            'currency' => 'ZAR',
            'currency_symbol' => 'R',
            'flag' => '🇿🇦',
        ],
        [
            'code' => 'RW',
            'iso2' => 'RW',
            'iso3' => 'RWA',
            'name' => 'Rwanda',
            'dial_code' => '+250',
            'currency' => 'RWF',
            'currency_symbol' => 'FRw',
            'flag' => '🇷🇼',
        ],
        [
            'code' => 'UG',
            'iso2' => 'UG',
            'iso3' => 'UGA',
            'name' => 'Uganda',
            'dial_code' => '+256',
            'currency' => 'UGX',
            'currency_symbol' => 'USh',
            'flag' => '🇺🇬',
        ],
        [
            'code' => 'TZ',
            'iso2' => 'TZ',
            'iso3' => 'TZA',
            'name' => 'Tanzania',
            'dial_code' => '+255',
            'currency' => 'TZS',
            'currency_symbol' => 'TSh',
            'flag' => '🇹🇿',
        ],
        [
            'code' => 'CI',
            'iso2' => 'CI',
            'iso3' => 'CIV',
            'name' => "Côte d'Ivoire",
            'dial_code' => '+225',
            'currency' => 'XOF',
            'currency_symbol' => 'CFA',
            'flag' => '🇨🇮',
        ],
        [
            'code' => 'SN',
            'iso2' => 'SN',
            'iso3' => 'SEN',
            'name' => 'Senegal',
            'dial_code' => '+221',
            'currency' => 'XOF',
            'currency_symbol' => 'CFA',
            'flag' => '🇸🇳',
        ],
        [
            'code' => 'EG',
            'iso2' => 'EG',
            'iso3' => 'EGY',
            'name' => 'Egypt',
            'dial_code' => '+20',
            'currency' => 'EGP',
            'currency_symbol' => 'E£',
            'flag' => '🇪🇬',
        ],
        [
            'code' => 'GB',
            'iso2' => 'GB',
            'iso3' => 'GBR',
            'name' => 'United Kingdom',
            'dial_code' => '+44',
            'currency' => 'GBP',
            'currency_symbol' => '£',
            'flag' => '🇬🇧',
        ],
        [
            'code' => 'US',
            'iso2' => 'US',
            'iso3' => 'USA',
            'name' => 'United States',
            'dial_code' => '+1',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'flag' => '🇺🇸',
        ],
        [
            'code' => 'CA',
            'iso2' => 'CA',
            'iso3' => 'CAN',
            'name' => 'Canada',
            'dial_code' => '+1',
            'currency' => 'CAD',
            'currency_symbol' => 'CA$',
            'flag' => '🇨🇦',
        ],
        [
            'code' => 'AE',
            'iso2' => 'AE',
            'iso3' => 'ARE',
            'name' => 'United Arab Emirates',
            'dial_code' => '+971',
            'currency' => 'AED',
            'currency_symbol' => 'AED',
            'flag' => '🇦🇪',
        ],
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

    /**
     * Retrieve supported countries and currencies for events and organizer profiles.
     */
    public function countries(): JsonResponse
    {
        return ApiResponse::success(
            'Countries retrieved successfully.',
            self::DEFAULT_COUNTRIES
        );
    }
}
