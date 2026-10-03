<?php

declare(strict_types=1);

namespace Modules\Payments\app\Enums;

enum PaymentGateway: string
{
    case Paystack = 'paystack';
    case Stripe = 'stripe';
    case Free = 'free';
    case Offline = 'offline';
}
