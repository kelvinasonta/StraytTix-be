<?php

declare(strict_types=1);

namespace Modules\Payments\app\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Failed = 'failed';
}
