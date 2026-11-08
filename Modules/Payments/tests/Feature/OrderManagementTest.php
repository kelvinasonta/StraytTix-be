<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Modules\Payments\app\Models\Order;
use Modules\Tickets\app\Models\TicketType;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['account_type' => 'organizer']);
    $this->organizer = Organizer::create([
        'user_id' => $this->owner->id,
        'name' => 'Tech Events Africa',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $this->customer = User::factory()->create(['account_type' => 'customer']);

    $this->event = Event::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->owner->id,
        'title' => 'Lagos AI & Tech Summit 2026',
        'description' => 'Annual premier tech summit in Lagos.',
        'event_country' => 'Nigeria',
        'event_type' => EventType::PHYSICAL,
        'event_format' => 'Conference',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    $this->vipTicket = TicketType::create([
        'event_id' => $this->event->id,
        'name' => 'VIP Access',
        'type' => 'paid',
        'price' => 50000.00,
        'currency' => 'NGN',
        'ticket_capacity' => 'limited',
        'quantity' => 50,
        'status' => 'active',
    ]);

    $this->freeTicket = TicketType::create([
        'event_id' => $this->event->id,
        'name' => 'Student Free Pass',
        'type' => 'free',
        'price' => 0.00,
        'currency' => 'NGN',
        'ticket_capacity' => 'limited',
        'quantity' => 100,
        'status' => 'active',
    ]);
});

test('customer can create an order for paid tickets and hold reservation', function () {
    $response = $this->actingAs($this->customer)
        ->postJson("/api/v1/events/{$this->event->uuid}/orders", [
            'items' => [
                [
                    'ticket_type_uuid' => $this->vipTicket->uuid,
                    'quantity' => 2,
                    'attendee_name' => 'Jane Doe',
                    'attendee_email' => 'jane@example.com',
                ],
            ],
            'customer_name' => 'John Customer',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '+2348012345678',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.subtotal', 100000)
        ->assertJsonPath('data.total', 100000)
        ->assertJsonPath('data.currency', 'NGN')
        ->assertJsonCount(1, 'data.items');

    $this->assertDatabaseHas('orders', [
        'user_id' => $this->customer->id,
        'event_id' => $this->event->id,
        'status' => 'pending',
        'total' => 100000.00,
    ]);

    // Check reservation increment
    expect($this->vipTicket->fresh()->reserved_quantity)->toBe(2);
});

test('free ticket order is fulfilled immediately and tickets are issued', function () {
    $response = $this->actingAs($this->customer)
        ->postJson("/api/v1/events/{$this->event->uuid}/orders", [
            'items' => [
                [
                    'ticket_type_uuid' => $this->freeTicket->uuid,
                    'quantity' => 1,
                    'attendee_name' => 'Alice Tech',
                    'attendee_email' => 'alice@example.com',
                ],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.total', 0)
        ->assertJsonCount(1, 'data.tickets');

    expect($this->freeTicket->fresh()->sold_quantity)->toBe(1);
    expect($this->freeTicket->fresh()->reserved_quantity)->toBe(0);

    $this->assertDatabaseHas('tickets', [
        'user_id' => $this->customer->id,
        'ticket_type_id' => $this->freeTicket->id,
        'attendee_name' => 'Alice Tech',
        'status' => 'valid',
    ]);
});

test('order fails if requested quantity exceeds capacity', function () {
    $response = $this->actingAs($this->customer)
        ->postJson("/api/v1/events/{$this->event->uuid}/orders", [
            'items' => [
                [
                    'ticket_type_uuid' => $this->vipTicket->uuid,
                    'quantity' => 100, // max is 50
                ],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('customer can initialize payment and verify payment to fulfill order', function () {
    $orderRes = $this->actingAs($this->customer)
        ->postJson("/api/v1/events/{$this->event->uuid}/orders", [
            'items' => [
                [
                    'ticket_type_uuid' => $this->vipTicket->uuid,
                    'quantity' => 1,
                ],
            ],
        ]);

    $orderUuid = $orderRes->json('data.id');

    // 1. Initialize payment
    $initRes = $this->actingAs($this->customer)
        ->postJson("/api/v1/orders/{$orderUuid}/payments", [
            'gateway' => 'paystack',
        ]);

    $initRes->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'pending');

    $reference = $initRes->json('data.reference');
    expect($reference)->not->toBeEmpty();

    // 2. Verify payment
    $verifyRes = $this->actingAs($this->customer)
        ->getJson("/api/v1/payments/verify/{$reference}");

    $verifyRes->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonCount(1, 'data.tickets');

    $this->assertDatabaseHas('orders', [
        'uuid' => $orderUuid,
        'status' => 'paid',
    ]);

    expect($this->vipTicket->fresh()->sold_quantity)->toBe(1);
    expect($this->vipTicket->fresh()->reserved_quantity)->toBe(0);
});

test('organizer can view and filter orders for an event', function () {
    // Create an order
    $order = Order::create([
        'user_id' => $this->customer->id,
        'event_id' => $this->event->id,
        'organizer_id' => $this->organizer->id,
        'status' => 'paid',
        'subtotal' => 50000.00,
        'total' => 50000.00,
        'currency' => 'NGN',
        'customer_name' => 'Emeka Okafor',
        'customer_email' => 'emeka@example.com',
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/events/{$this->event->uuid}/orders");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.customerName', 'Emeka Okafor');
});

test('organizer can export orders as CSV', function () {
    Order::create([
        'user_id' => $this->customer->id,
        'event_id' => $this->event->id,
        'organizer_id' => $this->organizer->id,
        'status' => 'paid',
        'subtotal' => 50000.00,
        'total' => 50000.00,
        'currency' => 'NGN',
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/events/{$this->event->uuid}/orders/export");

    $response->assertStatus(200)
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('Order Number')
        ->toContain('Ada Lovelace')
        ->toContain('ada@example.com');
});

test('unauthenticated guest can create an order, initialize payment, and verify payment', function () {
    // 1. Create order as guest (no auth)
    $response = $this->postJson("/api/v1/events/{$this->event->uuid}/orders", [
        'items' => [
            [
                'ticket_type_uuid' => $this->vipTicket->uuid,
                'quantity' => 1,
                'attendee_name' => 'Guest Attendee',
                'attendee_email' => 'guest@example.com',
            ],
        ],
        'customer_name' => 'Guest Attendee',
        'customer_email' => 'guest@example.com',
        'customer_phone' => '+2348099887766',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.total', 50000)
        ->assertJsonPath('data.customerEmail', 'guest@example.com');

    $orderUuid = $response->json('data.id');
    $guestToken = $response->json('data.guestToken');
    expect($guestToken)->not->toBeEmpty();

    $this->assertDatabaseHas('orders', [
        'uuid' => $orderUuid,
        'user_id' => null,
        'customer_email' => 'guest@example.com',
        'status' => 'pending',
    ]);

    // 2. Unauthorized access without guest token is rejected with 403
    $unauthShowRes = $this->getJson("/api/v1/orders/{$orderUuid}");
    $unauthShowRes->assertStatus(403);

    // 3. Guest can view order using guest token
    $showRes = $this->withHeader('X-Guest-Token', $guestToken)
        ->getJson("/api/v1/orders/{$orderUuid}");

    $showRes->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $orderUuid);

    // 4. Guest can initialize payment using guest token
    $initRes = $this->withHeader('X-Guest-Token', $guestToken)
        ->postJson("/api/v1/orders/{$orderUuid}/payments", [
            'gateway' => 'paystack',
        ]);

    $initRes->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'pending');

    $reference = $initRes->json('data.reference');
    expect($reference)->not->toBeEmpty();

    // 5. Guest can verify payment by gateway reference
    $verifyRes = $this->getJson("/api/v1/payments/verify/{$reference}");

    $verifyRes->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonCount(1, 'data.tickets');

    $this->assertDatabaseHas('orders', [
        'uuid' => $orderUuid,
        'status' => 'paid',
        'user_id' => null,
    ]);

    $this->assertDatabaseHas('tickets', [
        'order_id' => Order::where('uuid', $orderUuid)->value('id'),
        'user_id' => null,
        'attendee_name' => 'Guest Attendee',
        'attendee_email' => 'guest@example.com',
        'status' => 'valid',
    ]);
});

test('unauthenticated guest can reserve free tickets directly', function () {
    $response = $this->postJson("/api/v1/events/{$this->event->uuid}/orders", [
        'items' => [
            [
                'ticket_type_uuid' => $this->freeTicket->uuid,
                'quantity' => 2,
                'attendee_name' => 'Free Guest',
                'attendee_email' => 'freeguest@example.com',
            ],
        ],
        'customer_name' => 'Free Guest',
        'customer_email' => 'freeguest@example.com',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.total', 0)
        ->assertJsonCount(2, 'data.tickets');

    $this->assertDatabaseHas('orders', [
        'user_id' => null,
        'customer_email' => 'freeguest@example.com',
        'status' => 'paid',
    ]);

    $this->assertDatabaseHas('tickets', [
        'user_id' => null,
        'ticket_type_id' => $this->freeTicket->id,
        'attendee_email' => 'freeguest@example.com',
        'status' => 'valid',
    ]);
});
