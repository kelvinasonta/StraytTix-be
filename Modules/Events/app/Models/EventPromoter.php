<?php

declare(strict_types=1);

namespace Modules\Events\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EventPromoter extends Model
{
    use HasFactory;

    protected $table = 'event_promoters';

    protected $fillable = [
        'uuid',
        'event_id',
        'code',
        'discount',
        'discount_type',
        'uses',
        'sales',
        'max_uses',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'discount' => 'float',
            'uses' => 'integer',
            'sales' => 'float',
            'max_uses' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EventPromoter $promoter) {
            if (empty($promoter->uuid)) {
                $promoter->uuid = (string) Str::uuid();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
