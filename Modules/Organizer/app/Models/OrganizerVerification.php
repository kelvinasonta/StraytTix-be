<?php

declare(strict_types=1);

namespace Modules\Organizer\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerVerificationStatus;

class OrganizerVerification extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'organizer_verifications';

    protected $fillable = [
        'uuid',
        'organizer_id',
        'requested_by',
        'status',
        'business_name',
        'business_type',
        'registration_number',
        'tax_id',
        'website_url',
        'official_email',
        'official_phone',
        'address',
        'city',
        'state',
        'country',
        'documents',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrganizerVerificationStatus::class,
            'documents' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($verification) {
            if (empty($verification->uuid)) {
                $verification->uuid = (string) Str::uuid();
            }
        });
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
