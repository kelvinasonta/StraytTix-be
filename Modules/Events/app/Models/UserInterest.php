<?php

namespace Modules\Events\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\app\Models\User;

class UserInterest extends Model
{
    use HasFactory;

    protected $table = 'user_interests';

    protected $fillable = [
        'user_id',
        'interest_type',
        'interest_value',
        'affinity_score',
    ];

    protected function casts(): array
    {
        return [
            'affinity_score' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
