<?php

namespace Modules\Credits\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Credits\app\Models\CreditLedger;

/**
 * @mixin CreditLedger
 */
class CreditLedgerResource extends JsonResource
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
            'uuid' => $this->uuid,
            'wallet_id' => $this->wallet_id,
            'organizer_id' => $this->organizer_id,
            'event_id' => $this->event_id,
            'type' => $this->type?->value ?? $this->type,
            'reason' => $this->reason?->value ?? $this->reason,
            'amount' => (int) $this->amount,
            'balance_before' => (int) $this->balance_before,
            'balance_after' => (int) $this->balance_after,
            'idempotency_key' => $this->idempotency_key,
            'reference' => $this->reference,
            'description' => $this->description,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
