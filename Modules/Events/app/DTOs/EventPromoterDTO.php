<?php

declare(strict_types=1);

namespace Modules\Events\app\DTOs;

class EventPromoterDTO
{
    public function __construct(
        public readonly string $code,
        public readonly float $discount = 0.0,
        public readonly string $discount_type = 'percentage',
        public readonly int $uses = 0,
        public readonly float $sales = 0.0,
        public readonly ?int $max_uses = null,
        public readonly string $status = 'active',
        public readonly int $sort_order = 0,
        public readonly ?string $uuid = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            discount: (float) ($data['discount'] ?? 0.0),
            discount_type: $data['discountType'] ?? $data['discount_type'] ?? 'percentage',
            uses: (int) ($data['uses'] ?? 0),
            sales: (float) ($data['sales'] ?? 0.0),
            max_uses: isset($data['maxUses']) ? (int) $data['maxUses'] : (isset($data['max_uses']) ? (int) $data['max_uses'] : null),
            status: $data['status'] ?? 'active',
            sort_order: (int) ($data['sortOrder'] ?? $data['sort_order'] ?? 0),
            uuid: $data['id'] ?? $data['uuid'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [
            'code' => $this->code,
            'discount' => $this->discount,
            'discount_type' => $this->discount_type,
            'uses' => $this->uses,
            'sales' => $this->sales,
            'max_uses' => $this->max_uses,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
        ];

        if (! empty($this->uuid)) {
            $data['uuid'] = $this->uuid;
        }

        return $data;
    }
}
