<?php

namespace Modules\Events\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Events\Database\Factories\EventScheduleFactory;

class EventSchedule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'event_id',
        'schedule_type',
        'recurring_type',
        'schedule_timezone',
        'event_frequency',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'recurring_end_date',
        'recurring_end_time',
        'recurring_occurrences',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    // protected static function newFactory(): EventScheduleFactory
    // {
    //     // return EventScheduleFactory::new();
    // }
}
