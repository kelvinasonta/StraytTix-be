<?php

namespace Modules\Events\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMetric extends Model
{
    use HasFactory;

    protected $table = 'event_metrics';

    protected $fillable = [
        'event_id',
        'views_72h',
        'saves_72h',
        'shares_72h',
        'tickets_sold_72h',
        'total_impressions',
        'quality_score',
        'popularity_score',
        'last_scored_at',
    ];

    protected function casts(): array
    {
        return [
            'views_72h' => 'integer',
            'saves_72h' => 'integer',
            'shares_72h' => 'integer',
            'tickets_sold_72h' => 'integer',
            'total_impressions' => 'integer',
            'quality_score' => 'float',
            'popularity_score' => 'float',
            'last_scored_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
