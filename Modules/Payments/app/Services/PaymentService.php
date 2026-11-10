<?php

declare(strict_types=1);

namespace Modules\Payments\app\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Audit\app\Services\AuditService;
use Modules\Payments\app\Enums\OrderStatus;
use Modules\Payments\app\Enums\PaymentGateway;
use Modules\Payments\app\Enums\PaymentStatus;
use Modules\Payments\app\Events\OrderPaidEvent;
use Modules\Payments\app\Exceptions\OrderException;
use Modules\Payments\app\Models\Order;
use Modules\Payments\app\Models\Payment;
use Modules\Tickets\app\Models\Ticket;

class PaymentService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Initialize payment for an order.
     *
     * @return array<string, mixed>
     */
    public function initializePayment(Order $order, string $gateway = 'paystack', ?string $callbackUrl = null): array
    {
        if ($order->isExpired()) {
            throw new OrderException('Order ticket reservation has expired. Please create a new order.');
        }

        if ($order->isPaid()) {
            return [
                'status' => 'paid',
                'gateway' => 'free',
                'payment_url' => null,
                'reference' => null,
            ];
        }

        // Handle completely free orders
        if ((float) $order->total <= 0.00 || $gateway === 'free') {
            $this->fulfillFreeOrder($order);

            return [
                'status' => 'paid',
                'gateway' => 'free',
                'payment_url' => null,
                'reference' => null,
            ];
        }

        $reference = 'PAY-'.strtoupper(Str::random(12));
        $resolvedGateway = PaymentGateway::tryFrom(strtolower($gateway)) ?? PaymentGateway::Paystack;

        $payment = Payment::create([
            'order_id' => $order->id,
            'reference' => $reference,
            'gateway' => $resolvedGateway,
            'amount' => $order->total,
            'currency' => $order->currency,
            'status' => PaymentStatus::Pending,
        ]);

        $paymentUrl = $this->generateGatewayUrl($payment, $order, $callbackUrl);

        return [
            'status' => 'pending',
            'gateway' => $resolvedGateway->value,
            'reference' => $payment->reference,
            'payment_url' => $paymentUrl,
            'amount' => (float) $order->total,
            'currency' => $order->currency,
        ];
    }

    /**
     * Verify payment by reference and fulfill the order if successful.
     */
    public function verifyPayment(string $reference, array $gatewayData = []): Order
    {
        $payment = Payment::with('order.items.ticketType', 'order.event', 'order.organizer', 'order.user')
            ->where('reference', $reference)
            ->firstOrFail();

        if ($payment->status === PaymentStatus::Successful) {
            return $payment->order;
        }

        // If gateway is Paystack and secret key exists, optionally verify against Paystack API
        $secretKey = config('paystack.secret_key');
        if ($payment->gateway === PaymentGateway::Paystack && ! empty($secretKey) && ! app()->environment('testing')) {
            try {
                $response = Http::withToken($secretKey)
                    ->get(config('paystack.payment_url')."/transaction/verify/{$reference}");

                if ($response->successful() && $response->json('data.status') === 'success') {
                    $gatewayData = array_merge($gatewayData, $response->json('data') ?? []);
                } else {
                    $payment->update([
                        'status' => PaymentStatus::Failed,
                        'gateway_response' => $response->json() ?? [],
                    ]);
                    $payment->order->update(['status' => OrderStatus::Failed]);

                    throw new OrderException('Payment verification failed on gateway.');
                }
            } catch (\Exception $e) {
                if ($e instanceof OrderException) {
                    throw $e;
                }
                throw new OrderException('Failed to communicate with payment gateway: '.$e->getMessage());
            }
        }

        return $this->fulfillOrder($payment->order, $payment, $gatewayData);
    }

    /**
     * Fulfill order and generate tickets inside a database transaction.
     */
    public function fulfillOrder(Order $order, Payment $payment, array $gatewayData = []): Order
    {
        return DB::transaction(function () use ($order, $payment, $gatewayData): Order {
            // Check if already fulfilled
            if ($order->status === OrderStatus::Paid) {
                return $order->load(['items.ticketType', 'tickets.ticketType', 'payments']);
            }

            $payment->update([
                'status' => PaymentStatus::Successful,
                'paid_at' => now(),
                'gateway_response' => $gatewayData,
            ]);

            $order->update([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
            ]);

            // Issue tickets for each order item
            foreach ($order->items as $item) {
                $ticketType = $item->ticketType;

                for ($i = 0; $i < $item->quantity; $i++) {
                    Ticket::create([
                        'order_id' => $order->id,
                        'event_id' => $order->event_id,
                        'user_id' => $order->user_id,
                        'ticket_type_id' => $item->ticket_type_id,
                        'attendee_name' => $item->attendee_name ?: $order->customer_name,
                        'attendee_email' => $item->attendee_email ?: $order->customer_email,
                        'price_paid' => $item->unit_price,
                        'currency' => $item->currency,
                        'status' => 'valid',
                        'ticket_number' => 'TKT-'.strtoupper(Str::random(8)),
                        'qr_code' => 'EVT-'.$order->event->uuid.'-TKT-'.Str::uuid(),
                    ]);
                }

                if ($ticketType) {
                    $ticketType->increment('sold_quantity', $item->quantity);
                    $newReserved = max(0, $ticketType->reserved_quantity - $item->quantity);
                    $ticketType->update(['reserved_quantity' => $newReserved]);
                }
            }

            $this->auditService->log(
                action: 'order.paid',
                description: "Order '{$order->order_number}' was marked as paid and tickets were issued",
                organizer: $order->organizer,
                user: $order->user,
                metadata: [
                    'order_id' => $order->id,
                    'order_uuid' => $order->uuid,
                    'order_number' => $order->order_number,
                    'total' => (float) $order->total,
                    'payment_reference' => $payment->reference,
                ]
            );

            // Dispatch OrderPaidEvent for attendee tickets email & organizer sale alert
            event(new OrderPaidEvent($order, $payment));

            return $order->fresh(['items.ticketType', 'tickets.ticketType', 'payments']);
        });
    }

    /**
     * Fulfill a completely free order (0 cost).
     */
    public function fulfillFreeOrder(Order $order): Order
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'reference' => 'FREE-'.strtoupper(Str::random(10)),
            'gateway' => PaymentGateway::Free,
            'amount' => 0.00,
            'currency' => $order->currency,
            'status' => PaymentStatus::Successful,
            'paid_at' => now(),
        ]);

        return $this->fulfillOrder($order, $payment, ['note' => 'Free ticket registration']);
    }

    /**
     * Generate checkout payment URL for the chosen gateway.
     */
    protected function generateGatewayUrl(Payment $payment, Order $order, ?string $callbackUrl = null): string
    {
        $secretKey = config('paystack.secret_key');
        $resolvedCallback = $callbackUrl ?? config('app.frontend_url', config('app.url'))."/checkout/callback?reference={$payment->reference}";

        if ($payment->gateway === PaymentGateway::Paystack && ! empty($secretKey) && ! app()->environment('testing')) {
            try {
                $response = Http::withToken($secretKey)->post(config('paystack.payment_url').'/transaction/initialize', [
                    'email' => $order->customer_email,
                    'amount' => (int) round(((float) $order->total) * 100), // in kobo
                    'reference' => $payment->reference,
                    'currency' => $order->currency,
                    'callback_url' => $resolvedCallback,
                    'metadata' => [
                        'order_uuid' => $order->uuid,
                        'order_number' => $order->order_number,
                        'customer_name' => $order->customer_name,
                    ],
                ]);

                if ($response->successful() && $response->json('data.authorization_url')) {
                    return (string) $response->json('data.authorization_url');
                }
            } catch (\Exception) {
                // Fall back to direct callback URL
            }
        }

        // Return sandbox/mock redirect URL for local development or testing
        return $resolvedCallback;
    }
}
