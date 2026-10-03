<?php

declare(strict_types=1);

namespace Modules\Payments\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tickets\app\Transformers\TicketResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusValue = is_object($this->status) ? ($this->status->value ?? '') : (string) $this->status;

        return [
            'id' => $this->uuid,
            'orderNumber' => $this->order_number,
            'status' => $statusValue,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'serviceFee' => (float) $this->service_fee,
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'customerName' => $this->customer_name,
            'customerEmail' => $this->customer_email,
            'customerPhone' => $this->customer_phone,
            'reservedUntil' => $this->reserved_until?->toISOString(),
            'paidAt' => $this->paid_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'event' => $this->whenLoaded('event', function () {
                return [
                    'id' => $this->event->uuid,
                    'title' => $this->event->title,
                    'slug' => $this->event->slug,
                    'venue' => $this->event->location?->venue_name ?? $this->event->venue_name,
                ];
            }),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
        ];
    }
}
