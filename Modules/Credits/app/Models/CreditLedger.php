<?php

namespace Modules\Credits\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Credits\app\Enums\CreditTxReason;
use Modules\Credits\app\Enums\CreditTxType;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Models\Organizer;

class CreditLedger extends Model
{
    use HasFactory;

    protected $table = 'credit_ledger';

    protected $fillable = [
        'uuid',
        'wallet_id',
        'organizer_id',
        'event_id',
        'type',
        'reason',
        'amount',
        'balance_before',
        'balance_after',
        'idempotency_key',
        'reference',
        'description',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => CreditTxType::class,
        'reason' => CreditTxReason::class,
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (CreditLedger $entry) {
            if (empty($entry->uuid)) {
                $entry->uuid = (string) Str::uuid();
            }
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(OrganizerCreditWallet::class, 'wallet_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
