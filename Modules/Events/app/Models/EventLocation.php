<?php

namespace Modules\Events\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EventLocation extends Model
{
    use HasFactory;

    protected $table = 'events_location';

    protected $fillable = [
        'uuid',
        'event_id',
        'venue_name',
        'venue_address',
        'street_address',
        'city',
        'state',
        'zip',
        'landmark',
        'longitude',
        'latitude',
        'online_meeting_url',
    ];

    protected function casts(): array
    {
        return [
            'longitude' => 'float',
            'latitude' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EventLocation $location) {
            if (empty($location->uuid)) {
                $location->uuid = (string) Str::uuid();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
