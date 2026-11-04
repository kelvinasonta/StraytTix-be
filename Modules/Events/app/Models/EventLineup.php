<?php

namespace Modules\Events\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// use Modules\Events\Database\Factories\EventLineupFactory;

class EventLineup extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'event_id',
        'name',
        'avatarUrl',
        'role',
        'bio',
        'links',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'links' => 'array',
        ];
    }

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

    // protected static function newFactory(): EventLineupFactory
    // {
    //     // return EventLineupFactory::new();
    // }
}
