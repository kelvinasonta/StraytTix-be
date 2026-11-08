<?php

namespace Modules\Organizer\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\app\Transformers\UserResource;
use Modules\Organizer\app\Enum\OrganizerMemberRole;

class OrganizerMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleEnum = $this->role instanceof OrganizerMemberRole
            ? $this->role
            : OrganizerMemberRole::tryFrom((string) $this->role);

        $isOwner = $roleEnum === OrganizerMemberRole::OWNER;
        $permissions = is_array($this->permissions)
            ? $this->permissions
            : ($roleEnum?->permissions() ?? []);

        return [
            'id' => $this->id,
            'memberId' => $this->id,
            'userId' => $this->whenLoaded('user', fn () => $this->user?->uuid),
            'role' => $roleEnum?->value ?? (string) $this->role,
            'roleLabel' => $roleEnum?->label() ?? ucfirst((string) $this->role),
            'permissions' => $permissions,
            'isOwner' => $isOwner,
            'is_owner' => $isOwner,
            'joinedAt' => $this->created_at,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
