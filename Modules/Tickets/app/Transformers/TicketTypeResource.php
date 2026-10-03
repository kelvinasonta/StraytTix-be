<?php

namespace Modules\Tickets\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'ticketType' => $this->ticket_type ?? 'single',
            'ticket_type' => $this->ticket_type ?? 'single',
            'groupSize' => (int) ($this->group_size ?? 1),
            'group_size' => (int) ($this->group_size ?? 1),
            'type' => $this->type,
            'description' => $this->description,
            'ticketCapacity' => $this->ticket_capacity,
            'quantity' => $this->quantity,
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'soldQuantity' => (int) $this->sold_quantity,
            'reservedQuantity' => (int) $this->reserved_quantity,
            'remainingQuantity' => $this->remainingQuantity(),
            'isAvailable' => $this->isAvailable(),
            'saleStartsAt' => $this->sale_starts_at?->toISOString(),
            'saleEndsAt' => $this->sale_ends_at?->toISOString(),
            'status' => $this->status,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
