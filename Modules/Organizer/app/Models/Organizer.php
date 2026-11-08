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
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Enum\OrganizerPermission;
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

    /**
     * Resolve the member role of the given user in this organization.
     */
    public function getRoleForUser(?User $user): ?OrganizerMemberRole
    {
        if (! $user) {
            return null;
        }

        if ((int) $this->user_id === (int) $user->id) {
            return OrganizerMemberRole::OWNER;
        }

        if (isset($this->pivot->role)) {
            return $this->pivot->role instanceof OrganizerMemberRole
                ? $this->pivot->role
                : OrganizerMemberRole::tryFrom((string) $this->pivot->role);
        }

        if ($this->relationLoaded('members')) {
            /** @var OrganizerMember|null $member */
            $member = $this->members->firstWhere('user_id', $user->id);
            if ($member?->role) {
                return $member->role instanceof OrganizerMemberRole
                    ? $member->role
                    : OrganizerMemberRole::tryFrom((string) $member->role);
            }
        } else {
            /** @var OrganizerMember|null $member */
            $member = OrganizerMember::where('organizer_id', $this->id)
                ->where('user_id', $user->id)
                ->first();

            if ($member?->role) {
                return $member->role;
            }
        }

        if ($user->isAdministrator()) {
            return OrganizerMemberRole::OWNER;
        }

        return null;
    }

    /**
     * Get the granted permissions for the given user in this organization.
     *
     * @return array<int, string>
     */
    public function getPermissionsForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        if ((int) $this->user_id === (int) $user->id) {
            return OrganizerMemberRole::OWNER->permissions();
        }

        /** @var OrganizerMember|null $member */
        $member = null;
        if ($this->relationLoaded('members')) {
            $member = $this->members->firstWhere('user_id', $user->id);
        } else {
            $member = OrganizerMember::where('organizer_id', $this->id)
                ->where('user_id', $user->id)
                ->first();
        }

        if ($member) {
            if (is_array($member->permissions)) {
                return $member->permissions;
            }

            return $member->role?->permissions() ?? [];
        }

        if ($user->isAdministrator()) {
            return OrganizerMemberRole::OWNER->permissions();
        }

        return [];
    }

    /**
     * Determine whether the given user has the specified permission in this organization.
     */
    public function userHasPermission(?User $user, OrganizerPermission|string $permission): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        $permString = $permission instanceof OrganizerPermission ? $permission->value : $permission;
        $granted = $this->getPermissionsForUser($user);

        if (in_array('*', $granted, true)) {
            return true;
        }

        return in_array($permString, $granted, true);
    }

    /**
     * Determine whether the given user is the owner or a team member of this organization.
     */
    public function isMember(?User $user): bool
    {
        return $this->getRoleForUser($user) !== null;
    }
}
