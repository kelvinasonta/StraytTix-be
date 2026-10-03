<?php

declare(strict_types=1);

namespace Modules\Organizer\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizerVerificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'organizer_id' => $this->organizer?->uuid ?? $this->organizer_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : (string) $this->status,
            'business_name' => $this->business_name,
            'business_type' => $this->business_type,
            'registration_number' => $this->registration_number,
            'tax_id' => $this->tax_id,
            'website_url' => $this->website_url,
            'official_email' => $this->official_email,
            'official_phone' => $this->official_phone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'documents' => $this->documents ?? [],
            'rejection_reason' => $this->rejection_reason,
            'requester' => $this->whenLoaded('requester', function () {
                return [
                    'id' => $this->requester->id,
                    'uuid' => $this->requester->uuid,
                    'name' => $this->requester->name,
                    'email' => $this->requester->email,
                ];
            }),
            'reviewer' => $this->whenLoaded('reviewer', function () {
                return [
                    'id' => $this->reviewer->id,
                    'uuid' => $this->reviewer->uuid,
                    'name' => $this->reviewer->name,
                ];
            }),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
