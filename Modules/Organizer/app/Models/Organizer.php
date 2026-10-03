<?php

namespace Modules\Organizer\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Enum\OrganizerVerificationStatus;

class Organizer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'uuid',
        'type',
        'name',
        'slug',
        'description',
        'logo',
        'cover_photo',
        'email',
        'phone',
        'website',
        'is_active',
        'verification_status',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrganizerType::class,
            'is_active' => 'boolean',
            'verification_status' => OrganizerVerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($organizer) {
            if (empty($organizer->uuid)) {
                $organizer->uuid = (string) Str::uuid();
            }
            if (empty($organizer->slug) && ! empty($organizer->name)) {
                $organizer->slug = Str::slug($organizer->name);
            }
        });
    }

    public function getBannerUrlAttribute(): ?string
    {
        return $this->cover_photo;
    }

    public function getCoverImageAttribute(): ?string
    {
        return $this->cover_photo;
    }

    public function getBioAttribute(): ?string
    {
        return $this->description;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizerMember::class, 'organizer_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(OrganizerVerification::class, 'organizer_id');
    }

    public function latestVerification(): HasOne
    {
        return $this->hasOne(OrganizerVerification::class, 'organizer_id')->latestOfMany();
    }

    public function isVerified(): bool
    {
        return $this->verification_status === OrganizerVerificationStatus::VERIFIED;
    }

    public function bank(): HasOne
    {
        return $this->hasOne(OrganizerBankAccounts::class, 'organizer_id');
    }
}
