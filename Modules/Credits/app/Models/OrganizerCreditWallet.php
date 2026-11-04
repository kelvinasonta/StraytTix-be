<?php

namespace Modules\Credits\app\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Organizer\app\Models\Organizer;

class OrganizerCreditWallet extends Model
{
    use HasFactory;

    protected $table = 'organizer_credit_wallets';

    protected $fillable = [
        'uuid',
        'organizer_id',
        'balance',
        'reserved_balance',
        'currency',
        'version',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'balance' => 'integer',
        'reserved_balance' => 'integer',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrganizerCreditWallet $wallet) {
            if (empty($wallet->uuid)) {
                $wallet->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get available balance (balance - reserved_balance).
     */
    protected function availableBalance(): Attribute
    {
        return Attribute::make(
            get: fn (): int => max(0, (int) $this->balance - (int) $this->reserved_balance),
        );
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CreditLedger::class, 'wallet_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(CreditReservation::class, 'wallet_id');
    }

    public function topups(): HasMany
    {
        return $this->hasMany(CreditTopupTransaction::class, 'wallet_id');
    }
}
