<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;
use Modules\Events\app\Models\Event;
use Modules\Notification\app\Services\MailerService;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Modules\Payments\app\Enums\OrderStatus;
use Modules\Payments\app\Enums\PaymentGateway;
use Modules\Payments\app\Enums\PaymentStatus;
use Modules\Payments\app\Events\OrderPaidEvent;
use Modules\Payments\app\Models\Order;
use Modules\Payments\app\Models\OrderItem;
use Modules\Payments\app\Models\Payment;
use Modules\Payments\app\Services\Mail\SendOrderConfirmationEmailService;
use Modules\Payments\app\Services\Mail\SendOrganizerSaleNotificationEmailService;
use Modules\Payments\app\Services\PaymentService;
use Modules\Tickets\app\Models\TicketType;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('dispatches OrderPaidEvent when an order is fulfilled', function () {
    EventFacade::fake([OrderPaidEvent::class]);

    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'VibeFest',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $event = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'AfroBeats Night',
        'description' => 'Great night out',
        'event_country' => 'Nigeria',
        'event_type' => EventType::PHYSICAL,
        'event_format' => 'Concert',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Standard Pass',
        'type' => 'paid',
        'price' => 1000.00,
        'currency' => 'NGN',
        'ticket_capacity' => 'limited',
        'quantity' => 100,
        'status' => 'active',
    ]);

    $order = Order::create([
        'event_id' => $event->id,
        'organizer_id' => $organizer->id,
        'order_number' => 'ORD-123456',
        'status' => OrderStatus::Pending,
        'subtotal' => 1000.00,
        'discount' => 0.00,
        'service_fee' => 0.00,
        'total' => 1000.00,
        'currency' => 'NGN',
        'customer_name' => 'Jane Attendee',
        'customer_email' => 'attendee@example.com',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'ticket_type_id' => $ticketType->id,
        'quantity' => 1,
        'unit_price' => 1000.00,
        'total_price' => 1000.00,
        'currency' => 'NGN',
        'attendee_name' => 'Jane Attendee',
        'attendee_email' => 'attendee@example.com',
    ]);

    $payment = Payment::create([
        'order_id' => $order->id,
        'reference' => 'PAY-REF-123456',
        'amount' => 1000.00,
        'currency' => 'NGN',
        'gateway' => PaymentGateway::Paystack,
        'status' => PaymentStatus::Pending,
    ]);

    $paymentService = app(PaymentService::class);
    $paymentService->fulfillOrder($order, $payment);

    EventFacade::assertDispatched(OrderPaidEvent::class, function ($e) use ($order, $payment) {
        return $e->order->id === $order->id && $e->payment->id === $payment->id;
    });
});

it('sends order confirmation email with ticket details to attendee', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'VibeFest',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $event = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'AfroBeats Night',
        'description' => 'Great night out',
        'event_country' => 'Nigeria',
        'event_type' => EventType::PHYSICAL,
        'event_format' => 'Concert',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Standard Pass',
        'type' => 'paid',
        'price' => 5000.00,
        'currency' => 'NGN',
        'ticket_capacity' => 'limited',
        'quantity' => 100,
        'status' => 'active',
    ]);

    $order = Order::create([
        'event_id' => $event->id,
        'organizer_id' => $organizer->id,
        'order_number' => 'ORD-123456',
        'status' => OrderStatus::Pending,
        'subtotal' => 5000.00,
        'discount' => 0.00,
        'service_fee' => 0.00,
        'total' => 5000.00,
        'currency' => 'NGN',
        'customer_name' => 'David Buyer',
        'customer_email' => 'buyer@example.com',
    ]);

    $payment = Payment::create([
        'order_id' => $order->id,
        'reference' => 'PAY-REF-999999',
        'amount' => 5000.00,
        'currency' => 'NGN',
        'gateway' => PaymentGateway::Paystack,
        'status' => PaymentStatus::Successful,
    ]);

    $mailerService = Mockery::mock(MailerService::class);
    $mailerService->shouldReceive('send')
        ->once()
        ->with(
            'buyer@example.com',
            'order_confirmation',
            Mockery::on(function ($payload) {
                return $payload['orderNumber'] === 'ORD-123456'
                    && $payload['customerName'] === 'David Buyer'
                    && $payload['eventTitle'] === 'AfroBeats Night';
            })
        );

    $service = new SendOrderConfirmationEmailService($mailerService);
    $service->execute($order, $payment);
});

it('sends sale notification email to organizer', function () {
    $owner = User::factory()->create(['account_type' => 'organizer', 'email' => 'owner@vibefest.com']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'email' => 'owner@vibefest.com',
        'name' => 'VibeFest',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $event = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'AfroBeats Night',
        'description' => 'Great night out',
        'event_country' => 'Nigeria',
        'event_type' => EventType::PHYSICAL,
        'event_format' => 'Concert',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    $order = Order::create([
        'event_id' => $event->id,
        'organizer_id' => $organizer->id,
        'order_number' => 'ORD-123456',
        'status' => OrderStatus::Pending,
        'subtotal' => 5000.00,
        'discount' => 0.00,
        'service_fee' => 0.00,
        'total' => 5000.00,
        'currency' => 'NGN',
        'customer_name' => 'David Buyer',
        'customer_email' => 'buyer@example.com',
    ]);

    $payment = Payment::create([
        'order_id' => $order->id,
        'reference' => 'PAY-REF-888888',
        'amount' => 5000.00,
        'currency' => 'NGN',
        'gateway' => PaymentGateway::Paystack,
        'status' => PaymentStatus::Successful,
    ]);

    $mailerService = Mockery::mock(MailerService::class);
    $mailerService->shouldReceive('send')
        ->once()
        ->with(
            'owner@vibefest.com',
            'organizer_ticket_sale',
            Mockery::on(function ($payload) {
                return $payload['orderNumber'] === 'ORD-123456'
                    && $payload['buyerName'] === 'David Buyer'
                    && $payload['organizerName'] === 'VibeFest';
            })
        );

    $service = new SendOrganizerSaleNotificationEmailService($mailerService);
    $service->execute($order, $payment);
});
