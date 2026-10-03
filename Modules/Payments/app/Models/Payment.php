<?php

declare(strict_types=1);

namespace Modules\Payments\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Payments\app\Enums\PaymentGateway;
use Modules\Payments\app\Enums\PaymentStatus;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'uuid',
        'order_id',
        'reference',
        'gateway',
        'amount',
        'currency',
        'status',
        'channel',
        'gateway_response',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'gateway' => PaymentGateway::class,
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }

            if (empty($payment->reference)) {
                $payment->reference = 'PAY-'.strtoupper(Str::random(12));
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
