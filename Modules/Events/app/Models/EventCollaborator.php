<?php

declare(strict_types=1);

namespace Modules\Events\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Enum\EventCollaboratorRole;

class EventCollaborator extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'event_id',
        'user_id',
        'role',
        'custom_permissions',
        'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'role' => EventCollaboratorRole::class,
            'custom_permissions' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EventCollaborator $collaborator) {
            if (empty($collaborator->uuid)) {
                $collaborator->uuid = (string) Str::uuid();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Determine if this collaborator has a specific capability.
     */
    public function hasCapability(string $capability): bool
    {
        if (is_array($this->custom_permissions) && in_array($capability, $this->custom_permissions, true)) {
            return true;
        }

        return $this->role->can($capability);
    }
}
