<?php

declare(strict_types=1);

namespace Modules\Payments\app\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
