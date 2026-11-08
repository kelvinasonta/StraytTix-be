<?php

namespace Modules\Organizer\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerMemberRole;

class OrganizerMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organizer_id',
        'role',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'role' => OrganizerMemberRole::class,
            'permissions' => 'array',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
