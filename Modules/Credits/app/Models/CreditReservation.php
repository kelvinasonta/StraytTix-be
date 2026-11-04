<?php

namespace Modules\Credits\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Credits\app\Enums\ReservationStatus;

class CreditReservation extends Model
{
    use HasFactory;

    protected $table = 'credit_reservations';

    protected $fillable = [
        'uuid',
        'wallet_id',
        'amount',
        'status',
        'reference_type',
        'reference_id',
        'expires_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ReservationStatus::class,
        'amount' => 'integer',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CreditReservation $reservation) {
            if (empty($reservation->uuid)) {
                $reservation->uuid = (string) Str::uuid();
            }
        });
    }

    public function isPending(): bool
    {
        return $this->status === ReservationStatus::PENDING;
    }

    public function isCommitted(): bool
    {
        return $this->status === ReservationStatus::COMMITTED;
    }

    public function isReleased(): bool
    {
        return $this->status === ReservationStatus::RELEASED;
    }

    public function isExpired(): bool
    {
        return $this->status === ReservationStatus::EXPIRED || ($this->isPending() && $this->expires_at->isPast());
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(OrganizerCreditWallet::class, 'wallet_id');
    }
}
