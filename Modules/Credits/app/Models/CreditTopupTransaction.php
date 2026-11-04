<?php

namespace Modules\Credits\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CreditTopupTransaction extends Model
{
    use HasFactory;

    protected $table = 'credit_topup_transactions';

    protected $fillable = [
        'uuid',
        'wallet_id',
        'provider',
        'provider_ref',
        'amount_kobo',
        'credits_awarded',
        'status',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount_kobo' => 'integer',
        'credits_awarded' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (CreditTopupTransaction $transaction) {
            if (empty($transaction->uuid)) {
                $transaction->uuid = (string) Str::uuid();
            }
        });
    }

    public function isSuccess(): bool
    {
        return $this->status === 'SUCCESS';
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(OrganizerCreditWallet::class, 'wallet_id');
    }
}
