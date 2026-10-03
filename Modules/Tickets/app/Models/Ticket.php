<?php

namespace Modules\Tickets\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Models\Event;
use Modules\Payments\app\Models\Order;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tickets';

    protected $fillable = [
        'uuid',
        'event_id',
        'user_id',
        'ticket_type_id',
        'order_id',
        'ticket_number',
        'price_paid',
        'currency',
        'attendee_name',
        'attendee_email',
        'status',
        'checked_in_at',
        'qr_code',
    ];

    protected function casts(): array
    {
        return [
            'price_paid' => 'decimal:2',
            'checked_in_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            if (empty($ticket->uuid)) {
                $ticket->uuid = (string) Str::uuid();
            }

            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = 'TKT-'.strtoupper(Str::random(8));
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
