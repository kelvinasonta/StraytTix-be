<?php

declare(strict_types=1);

namespace Modules\Payments\app\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Payments\app\Models\Order;
use Modules\Payments\app\Models\Payment;

class OrderPaidEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Order $order,
        public Payment $payment
    ) {}
}
