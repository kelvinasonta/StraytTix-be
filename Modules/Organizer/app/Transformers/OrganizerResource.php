<?php

namespace Modules\Organizer\app\Transformers;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Models\Organizer;

/**
 * @mixin Organizer
 */
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $this->user ?? $request->user();

        $memberRoleEnum = $this->resolveRoleEnum($user, $request);
        $role = $memberRoleEnum?->value;
        $isOwner = $user !== null && ((int) $this->user_id === (int) $user->id || $memberRoleEnum === OrganizerMemberRole::OWNER);
        $permissions = $memberRoleEnum?->permissions() ?? [];

        $verificationStatus = $this->verification_status instanceof BackedEnum
            ? $this->verification_status->value
            : ($this->verification_status ?? 'unverified');

        $verifiedAtStr = $this->verified_at instanceof \DateTimeInterface
            ? $this->verified_at->format(\DateTimeInterface::ATOM)
            : ($this->verified_at ? (string) $this->verified_at : null);

        $data = [
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
            'type' => $this->type instanceof BackedEnum ? $this->type->value : $this->type,
            'verification_status' => $verificationStatus,
            'is_verified' => $verificationStatus === 'verified',
            'verified_at' => $verifiedAtStr,
            'role' => $role,
            'roleLabel' => $memberRoleEnum?->label(),
            'isOwner' => $isOwner,
            'is_owner' => $isOwner,
            'permissions' => $permissions,
        ];

        if ($this->full) {
            $data['is_active'] = $this->is_active;
        }

        return $data;
    }

    /**
     * Resolve the authenticated user's role enum in this organization.
     */
    protected function resolveRoleEnum(?User $user, Request $request): ?OrganizerMemberRole
    {
        if (! $user) {
            return null;
        }

        $activeRole = $request->attributes->get('active_organizer_role');
        $activeOrganizer = $request->attributes->get('active_organizer');

        if ($activeRole && $activeOrganizer?->id === $this->id && $request->user()?->id === $user->id) {
            return $activeRole instanceof OrganizerMemberRole
                ? $activeRole
                : OrganizerMemberRole::tryFrom((string) $activeRole);
        }

        if ($this->resource instanceof Organizer) {
            return $this->resource->getRoleForUser($user);
        }

        return null;
    }
}
