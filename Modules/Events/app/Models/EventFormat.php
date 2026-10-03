<?php

namespace Modules\Events\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// use Modules\Events\Database\Factories\EventFormatFactory;

class EventFormat extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'name',
        'slug',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
            }

            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    // protected static function newFactory(): EventFormatFactory
    // {
    //     // return EventFormatFactory::new();
    // }
}
