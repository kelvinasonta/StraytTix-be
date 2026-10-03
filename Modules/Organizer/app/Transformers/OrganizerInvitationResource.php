<?php

namespace Modules\Organizer\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizerInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'email' => $this->email,
            'role' => $this->role?->value ?? $this->role,
            'token' => $this->token,
            'expiresAt' => $this->expires_at,
            'isPending' => $this->isPending(),
            'createdAt' => $this->created_at,
        ];
    }
}
