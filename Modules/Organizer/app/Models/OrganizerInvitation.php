<?php

namespace Modules\Organizer\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerMemberRole;

class OrganizerInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'organizer_id',
        'email',
        'role',
        'token',
        'invited_by',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => OrganizerMemberRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($invitation) {
            if (empty($invitation->uuid)) {
                $invitation->uuid = (string) Str::uuid();
            }
            if (empty($invitation->token)) {
                $invitation->token = Str::random(40);
            }
        });
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return is_null($this->accepted_at) && $this->expires_at->isFuture();
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
