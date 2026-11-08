<?php

declare(strict_types=1);

namespace Modules\Payments\app\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Models\Organizer;
use Modules\Payments\app\Enums\OrderStatus;
use Modules\Payments\app\Exceptions\OrderException;
use Modules\Payments\app\Models\Order;
use Modules\Payments\app\Models\OrderItem;
use Modules\Tickets\app\Models\TicketType;

class OrderService
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Create a new order with reserved ticket quantities.
     *
     * @param  array<int, array{ticket_type_uuid: string, quantity: int, attendee_name?: string|null, attendee_email?: string|null}>  $itemsData
     * @param  array{customer_name?: string|null, customer_email?: string|null, customer_phone?: string|null}  $customerData
     */
    public function createOrder(
        ?User $buyer,
        Event $event,
        array $itemsData,
        array $customerData = [],
        ?string $notes = null
    ): Order {
        $statusValue = is_object($event->status) ? ($event->status->value ?? '') : (string) $event->status;
        if (strtolower($statusValue) !== 'published') {
            throw new OrderException('Orders can only be placed for published events.');
        }

        if (empty($itemsData)) {
            throw new OrderException('Order must contain at least one ticket item.');
        }

        return DB::transaction(function () use ($buyer, $event, $itemsData, $customerData, $notes): Order {
            $subtotal = 0.00;
            $preparedItems = [];
            $currency = 'NGN';

            foreach ($itemsData as $item) {
                $quantity = (int) ($item['quantity'] ?? 1);
                if ($quantity <= 0) {
                    throw new OrderException('Ticket quantity must be at least 1.');
                }

                $ticketTypeUuid = $item['ticket_type_uuid'] ?? '';
                /** @var TicketType|null $ticketType */
                $ticketType = TicketType::where('uuid', $ticketTypeUuid)
                    ->where('event_id', $event->id)
                    ->lockForUpdate()
                    ->first();

                if (! $ticketType) {
                    throw new OrderException("Ticket type not found for this event: {$ticketTypeUuid}");
                }

                if (! $ticketType->hasAvailableCapacity($quantity)) {
                    throw new OrderException("Not enough available tickets for '{$ticketType->name}'.");
                }

                $currency = $ticketType->currency ?: 'NGN';
                $unitPrice = (float) $ticketType->price;
                $itemTotal = $unitPrice * $quantity;
                $subtotal += $itemTotal;

                // Hold reservation for the ticket
                $ticketType->increment('reserved_quantity', $quantity);

                $preparedItems[] = [
                    'ticket_type' => $ticketType,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $itemTotal,
                    'currency' => $currency,
                    'attendee_name' => $item['attendee_name'] ?? null,
                    'attendee_email' => $item['attendee_email'] ?? null,
                ];
            }

            $discount = 0.00;
            $serviceFee = 0.00;
            $total = max(0.00, $subtotal - $discount + $serviceFee);

            $customerName = $customerData['customer_name'] ?? ($buyer?->name ?? 'Guest Buyer');
            $customerEmail = $customerData['customer_email'] ?? ($buyer?->email ?? null);
            $customerPhone = $customerData['customer_phone'] ?? null;

            if (empty($customerEmail)) {
                throw new OrderException('Customer email is required for ticket delivery.');
            }

            // Generate secure guest token for unauthenticated access & verification
            $guestToken = $buyer ? null : bin2hex(random_bytes(24));

            $order = Order::create([
                'user_id' => $buyer?->id,
                'event_id' => $event->id,
                'organizer_id' => $event->organizer_id,
                'status' => OrderStatus::Pending,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'service_fee' => $serviceFee,
                'total' => $total,
                'currency' => $currency,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'guest_token' => $guestToken,
                'notes' => $notes,
                'reserved_until' => now()->addMinutes(15),
            ]);

            foreach ($preparedItems as $prep) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $prep['ticket_type']->id,
                    'quantity' => $prep['quantity'],
                    'unit_price' => $prep['unit_price'],
                    'total_price' => $prep['total_price'],
                    'currency' => $prep['currency'],
                    'attendee_name' => $prep['attendee_name'] ?: $customerName,
                    'attendee_email' => $prep['attendee_email'] ?: $customerEmail,
                ]);
            }

            // If totally free, immediately fulfill order and issue tickets
            if ($total <= 0.00) {
                $this->paymentService->fulfillFreeOrder($order);
            }

            return $order->fresh(['items.ticketType', 'event.organizer', 'tickets.ticketType']);
        });
    }

    /**
     * Get a specific order by UUID.
     */
    public function getOrder(string $orderUuid): Order
    {
        return Order::with(['items.ticketType', 'event.organizer', 'event.location', 'tickets.ticketType', 'payments'])
            ->where('uuid', $orderUuid)
            ->firstOrFail();
    }

    /**
     * Get orders for the authenticated customer.
     */
    public function getUserOrders(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Order::with(['items.ticketType', 'event.location', 'tickets'])
            ->where('user_id', $user->id)
            ->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = (int) ($filters['per_page'] ?? $filters['perPage'] ?? 15);

        return $query->paginate($perPage);
    }

    /**
     * Get orders for an organizer, optionally filtered by event.
     */
    public function getOrganizerOrders(Organizer $organizer, ?string $eventUuid = null, array $filters = []): LengthAwarePaginator
    {
        $query = Order::with(['items.ticketType', 'event', 'user', 'tickets'])
            ->where('organizer_id', $organizer->id)
            ->latest();

        if ($eventUuid) {
            $event = Event::where('uuid', $eventUuid)->where('organizer_id', $organizer->id)->firstOrFail();
            $query->where('event_id', $event->id);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', $search)
                    ->orWhere('customer_name', 'like', $search)
                    ->orWhere('customer_email', 'like', $search);
            });
        }

        $perPage = (int) ($filters['per_page'] ?? $filters['perPage'] ?? 15);

        return $query->paginate($perPage);
    }

    /**
     * Export organizer orders as CSV.
     */
    public function exportOrdersCsv(Organizer $organizer, ?string $eventUuid = null): string
    {
        $query = Order::with(['event'])
            ->where('organizer_id', $organizer->id)
            ->latest();

        if ($eventUuid) {
            $event = Event::where('uuid', $eventUuid)->where('organizer_id', $organizer->id)->firstOrFail();
            $query->where('event_id', $event->id);
        }

        $orders = $query->get();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, [
            'Order Number',
            'Event',
            'Customer Name',
            'Customer Email',
            'Customer Phone',
            'Status',
            'Subtotal',
            'Discount',
            'Service Fee',
            'Total',
            'Currency',
            'Created At',
            'Paid At',
        ]);

        foreach ($orders as $order) {
            $statusValue = is_object($order->status) ? ($order->status->value ?? '') : (string) $order->status;
            fputcsv($output, [
                $order->order_number,
                $order->event ? $order->event->title : 'N/A',
                $order->customer_name,
                $order->customer_email,
                $order->customer_phone ?? '',
                $statusValue,
                $order->subtotal,
                $order->discount,
                $order->service_fee,
                $order->total,
                $order->currency,
                $order->created_at ? $order->created_at->toIso8601String() : '',
                $order->paid_at ? $order->paid_at->toIso8601String() : '',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv ?: '';
    }

    /**
     * Cancel pending orders that have exceeded their reservation window.
     */
    public function cancelExpiredReservations(): int
    {
        $expiredOrders = Order::with('items.ticketType')
            ->where('status', OrderStatus::Pending)
            ->where('reserved_until', '<', now())
            ->get();

        $count = 0;

        foreach ($expiredOrders as $order) {
            DB::transaction(function () use ($order): void {
                foreach ($order->items as $item) {
                    if ($item->ticketType) {
                        $newReserved = max(0, $item->ticketType->reserved_quantity - $item->quantity);
                        $item->ticketType->update(['reserved_quantity' => $newReserved]);
                    }
                }

                $order->update([
                    'status' => OrderStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);
            });

            $count++;
        }

        return $count;
    }
}
