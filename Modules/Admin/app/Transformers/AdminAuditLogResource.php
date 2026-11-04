<?php

declare(strict_types=1);

namespace Modules\Admin\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'reason' => $this->reason,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id' => $this->user->id,
                    'uuid' => $this->user->uuid,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'is_admin' => (bool) $this->user->is_admin,
                    'admin_role' => $this->user->admin_role,
                ] : null;
            }),
            'organizer' => $this->whenLoaded('organizer', function () {
                return $this->organizer ? [
                    'id' => $this->organizer->id,
                    'uuid' => $this->organizer->uuid,
                    'name' => $this->organizer->name,
                ] : null;
            }),
        ];
    }
}
