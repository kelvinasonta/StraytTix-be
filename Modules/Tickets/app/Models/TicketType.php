<?php

namespace Modules\Tickets\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Events\app\Models\Event;

class TicketType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ticket_types';

    protected $fillable = [
        'uuid',
        'event_id',
        'name',
        'ticket_type',
        'group_size',
        'type',
        'description',
        'ticket_capacity',
        'quantity',
        'price',
        'currency',
        'sold_quantity',
        'reserved_quantity',
        'sale_starts_at',
        'sale_ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'group_size' => 'integer',
            'sold_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TicketType $ticketType) {
            if (empty($ticketType->uuid)) {
                $ticketType->uuid = (string) Str::uuid();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Check if this ticket type is currently on sale and has inventory.
     */
    public function isAvailable(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now();
        if ($this->sale_starts_at && $now->lt($this->sale_starts_at)) {
            return false;
        }
        if ($this->sale_ends_at && $now->gt($this->sale_ends_at)) {
            return false;
        }

        if ($this->ticket_capacity === 'limited' && $this->quantity !== null) {
            return ($this->sold_quantity + $this->reserved_quantity) < $this->quantity;
        }

        return true;
    }

    /**
     * Get remaining available ticket quantity.
     */
    public function remainingQuantity(): ?int
    {
        if ($this->ticket_capacity === 'unlimited' || $this->quantity === null) {
            return null;
        }

        return max(0, $this->quantity - ($this->sold_quantity + $this->reserved_quantity));
    }

    /**
     * Check if a specific requested quantity is available.
     */
    public function hasAvailableCapacity(int $requestedQty): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        if ($this->ticket_capacity === 'limited' && $this->quantity !== null) {
            $remaining = $this->remainingQuantity() ?? 0;

            return $remaining >= $requestedQty;
        }

        return true;
    }
}
