<?php

declare(strict_types=1);

namespace Modules\Payments\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tickets\app\Transformers\TicketTypeResource;

class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'quantity' => $this->quantity,
            'unitPrice' => (float) $this->unit_price,
            'totalPrice' => (float) $this->total_price,
            'currency' => $this->currency,
            'attendeeName' => $this->attendee_name,
            'attendeeEmail' => $this->attendee_email,
            'ticketType' => $this->whenLoaded('ticketType', function () {
                return new TicketTypeResource($this->ticketType);
            }),
        ];
    }
}
