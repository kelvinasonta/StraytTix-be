<?php

namespace Modules\Events\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// use Modules\Events\Database\Factories\EventScheduleItemFactory;

class EventScheduleItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'event_id',
        'title',
        'description',
        'schedule_date',
        'start_time',
        'end_time',
        'sort_order',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
            }

            if (empty($model->sort_order)) {
                $model->sort_order = 0;
            }
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    // protected static function newFactory(): EventScheduleItemFactory
    // {
    //     // return EventScheduleItemFactory::new();
    // }
}
