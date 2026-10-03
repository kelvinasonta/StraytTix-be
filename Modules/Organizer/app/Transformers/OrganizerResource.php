<?php

namespace Modules\Organizer\app\Transformers;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Models\OrganizerMember;

class OrganizerResource extends JsonResource
{
    protected bool $full;

    protected ?User $user;

    public function __construct($resource, bool $full = false, ?User $user = null)
    {
        parent::__construct($resource);

        $this->full = $full;
        $this->user = $user;
    }

    public function forUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $this->user ?? $request->user();

        $role = $this->resolveRole($user, $request);
        $isOwner = $user !== null && ($this->user_id === $user->id || $role === OrganizerMemberRole::OWNER->value);

        $verificationStatus = $this->verification_status instanceof BackedEnum
            ? $this->verification_status->value
            : ($this->verification_status ?? 'unverified');

        $verifiedAtStr = $this->verified_at instanceof \DateTimeInterface
            ? $this->verified_at->format(\DateTimeInterface::ATOM)
            : ($this->verified_at ? (string) $this->verified_at : null);

        if ($this->full) {
            return [
                'id' => $this->uuid,
                'name' => $this->name,
                'slug' => $this->slug,
                'description' => $this->description,
                'bio' => $this->description,
                'logo' => $this->logo,
                'logo_url' => $this->logo,
                'cover_photo' => $this->cover_photo,
                'cover_image' => $this->cover_photo,
                'banner_url' => $this->cover_photo,
                'bannerUrl' => $this->cover_photo,
                'email' => $this->email,
                'phone' => $this->phone,
                'website' => $this->website,
                'is_active' => $this->is_active,
                'type' => $this->type?->value ?? $this->type,
                'verification_status' => $verificationStatus,
                'is_verified' => $verificationStatus === 'verified',
                'verified_at' => $verifiedAtStr,
                'role' => $role,
                'isOwner' => $isOwner,
            ];
        }

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'bio' => $this->description,
            'logo' => $this->logo,
            'logo_url' => $this->logo,
            'cover_photo' => $this->cover_photo,
            'cover_image' => $this->cover_photo,
            'banner_url' => $this->cover_photo,
            'bannerUrl' => $this->cover_photo,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'type' => $this->type?->value ?? $this->type,
            'verification_status' => $verificationStatus,
            'is_verified' => $verificationStatus === 'verified',
            'verified_at' => $verifiedAtStr,
            'role' => $role,
            'isOwner' => $isOwner,
        ];
    }

    /**
     * Resolve the authenticated user's role in this organization.
     */
    protected function resolveRole(?User $user, Request $request): ?string
    {
        if (! $user) {
            return null;
        }

        // 1. Primary Owner
        if ($this->user_id === $user->id) {
            return OrganizerMemberRole::OWNER->value;
        }

        // 2. From BelongsToMany pivot (e.g. $user->organizers)
        if (isset($this->pivot->role)) {
            return $this->pivot->role instanceof BackedEnum
                ? $this->pivot->role->value
                : (string) $this->pivot->role;
        }

        // 3. Set by EnsureOrganizerRole middleware
        $activeRole = $request->attributes->get('active_organizer_role');
        if ($activeRole && $request->attributes->get('active_organizer')?->id === $this->id) {
            return $activeRole instanceof BackedEnum
                ? $activeRole->value
                : (string) $activeRole;
        }

        // 4. Query relation / organizer_members table
        if ($this->relationLoaded('members')) {
            $member = $this->members->firstWhere('user_id', $user->id);
            if ($member?->role) {
                return $member->role instanceof BackedEnum
                    ? $member->role->value
                    : (string) $member->role;
            }
        } else {
            $role = OrganizerMember::where('organizer_id', $this->id)
                ->where('user_id', $user->id)
                ->value('role');

            if ($role) {
                return $role instanceof BackedEnum
                    ? $role->value
                    : (string) $role;
            }
        }

        return null;
    }
}
