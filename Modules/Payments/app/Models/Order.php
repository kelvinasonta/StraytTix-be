<?php

declare(strict_types=1);

namespace Modules\Payments\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Models\Organizer;
use Modules\Payments\app\Enums\OrderStatus;
use Modules\Tickets\app\Models\Ticket;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'orders';

    protected $fillable = [
        'uuid',
        'order_number',
        'user_id',
        'event_id',
        'organizer_id',
        'status',
        'subtotal',
        'discount',
        'service_fee',
        'total',
        'currency',
        'customer_name',
        'customer_email',
        'customer_phone',
        'guest_token',
        'notes',
        'reserved_until',
        'paid_at',
        'cancelled_at',
        'refunded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'reserved_until' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }

            if (empty($order->order_number)) {
                $order->order_number = 'ORD-'.strtoupper(Str::random(8));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->isPending() && $this->reserved_until !== null && $this->reserved_until->isPast();
    }
}
