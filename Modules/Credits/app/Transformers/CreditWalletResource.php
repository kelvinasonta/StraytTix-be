<?php

namespace Modules\Credits\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Credits\app\Models\OrganizerCreditWallet;

/**
 * @mixin OrganizerCreditWallet
 */
class CreditWalletResource extends JsonResource
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
            'organizer_id' => $this->organizer_id,
            'balance' => (int) $this->balance,
            'reserved_balance' => (int) $this->reserved_balance,
            'available_balance' => (int) $this->available_balance,
            'currency' => $this->currency,
            'version' => (int) $this->version,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
