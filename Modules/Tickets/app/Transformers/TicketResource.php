<?php

namespace Modules\Tickets\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Events\app\Transformers\EventResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'ticketNumber' => $this->ticket_number,
            'pricePaid' => (float) $this->price_paid,
            'currency' => $this->currency,
            'status' => $this->status,
            'attendeeName' => $this->attendee_name,
            'attendeeEmail' => $this->attendee_email,
            'checkedInAt' => $this->checked_in_at?->toISOString(),
            'qrCode' => $this->qr_code,
            'event' => $this->whenLoaded('event', function () {
                return new EventResource($this->event);
            }),
            'ticketType' => $this->whenLoaded('ticketType', function () {
                return new TicketTypeResource($this->ticketType);
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->uuid,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
