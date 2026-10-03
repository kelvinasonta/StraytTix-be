<?php

namespace Modules\Organizer\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\app\Transformers\UserResource;

class OrganizerMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'role' => $this->role?->value ?? $this->role,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
