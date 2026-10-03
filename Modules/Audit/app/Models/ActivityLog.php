<?php

namespace Modules\Audit\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Models\Organizer;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'organizer_id',
        'organization_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ActivityLog $log) {
            if (empty($log->organizer_id) && ! empty($log->organization_id)) {
                $log->organizer_id = $log->organization_id;
            } elseif (! empty($log->organizer_id) && empty($log->organization_id)) {
                $log->organization_id = $log->organizer_id;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    /**
     * Scope query to logs belonging to an organizer.
     */
    public function scopeForOrganizer(Builder $query, Organizer|int|string $organizer): Builder
    {
        $id = $organizer instanceof Organizer ? $organizer->id : $organizer;

        return $query->where(function (Builder $q) use ($id) {
            $q->where('organizer_id', $id)
                ->orWhere('organization_id', $id);
        });
    }

    /**
     * Scope query to logs for a specific action.
     */
    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope query to logs initiated by a user.
     */
    public function scopeForUser(Builder $query, User|int|string $user): Builder
    {
        $id = $user instanceof User ? $user->id : $user;

        return $query->where('user_id', $id);
    }
}
