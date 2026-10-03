<?php

namespace Modules\Audit\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'ipAddress' => $this->ip_address,
            'userAgent' => $this->user_agent,
            'metadata' => $this->metadata ?? [],
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->uuid ?? $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
