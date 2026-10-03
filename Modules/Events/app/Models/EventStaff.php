<?php

namespace Modules\Events\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Models\OrganizerMember;

class EventStaff extends Model
{
    use HasFactory;

    protected $table = 'event_staff';

    protected $fillable = [
        'event_id',
        'user_id',
        'organizer_member_id',
        'assigned_by',
        'role',
        'permissions',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organizerMember(): BelongsTo
    {
        return $this->belongsTo(OrganizerMember::class, 'organizer_member_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
