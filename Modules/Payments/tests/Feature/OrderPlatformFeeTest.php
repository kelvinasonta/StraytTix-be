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
use Modules\Payments\app\Services\OrderService;
use Modules\Shared\app\Contracts\AppSettingsServiceInterface;
use Modules\Shared\app\DTOs\AppSettingsDTO;
use Modules\Tickets\app\Models\TicketType;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('calculates platform service fee dynamically from app settings', function () {
    $settingsService = app(AppSettingsServiceInterface::class);

    // Set 5% fee + 100 flat fee per ticket (passthrough = true)
    $settingsService->updateSettings(new AppSettingsDTO(
        sitename: 'EventsOS',
        site_tagline: 'Ticket Platform',
        ticket_fee_percentage: 5.0,
        ticket_fee_fixed: 100.0,
        platform_fee_percentage: 5.0,
        platform_fee_fixed: 100.0,
        platform_fee_passthrough: true
    ));

    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Tech Events Africa',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $event = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'Lagos AI & Tech Summit 2026',
        'description' => 'Annual premier tech summit in Lagos.',
        'event_country' => 'Nigeria',
        'event_type' => EventType::PHYSICAL,
        'event_format' => 'Conference',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    // Ticket price: 10,000 NGN
    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Regular Pass',
        'type' => 'paid',
        'price' => 10000.00,
        'currency' => 'NGN',
        'ticket_capacity' => 'limited',
        'quantity' => 100,
        'status' => 'active',
    ]);

    $orderService = app(OrderService::class);
    $order = $orderService->createOrder(
        buyer: null,
        event: $event,
        itemsData: [
            [
                'ticket_type_uuid' => $ticketType->uuid,
                'quantity' => 2, // Subtotal: 20,000 NGN
            ],
        ],
        customerData: [
            'customer_name' => 'Alice Buyer',
            'customer_email' => 'alice@example.com',
        ]
    );

    // Expected fee: 5% of 20,000 = 1,000 + (100 * 2) = 1,200 NGN
    // Expected total: 20,000 + 1,200 = 21,200 NGN
    expect((float) $order->subtotal)->toBe(20000.0)
        ->and((float) $order->service_fee)->toBe(1200.0)
        ->and((float) $order->total)->toBe(21200.0);
});

it('admin can change platform fee and immediate next order uses updated fee', function () {
    $settingsService = app(AppSettingsServiceInterface::class);

    // Update fee to 0% fee and 0 flat
    $settingsService->updateSettings(new AppSettingsDTO(
        sitename: 'EventsOS',
        site_tagline: 'Zero Fee Promo',
        ticket_fee_percentage: 0.0,
        ticket_fee_fixed: 0.0,
        platform_fee_percentage: 0.0,
        platform_fee_fixed: 0.0,
        platform_fee_passthrough: true
    ));

    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Tech Events Africa',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $event = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'Lagos AI & Tech Summit 2026',
        'description' => 'Annual premier tech summit in Lagos.',
        'event_country' => 'Nigeria',
        'event_type' => EventType::PHYSICAL,
        'event_format' => 'Conference',
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
        'quantity' => 50,
        'status' => 'active',
    ]);

    $orderService = app(OrderService::class);
    $order = $orderService->createOrder(
        buyer: null,
        event: $event,
        itemsData: [
            [
                'ticket_type_uuid' => $ticketType->uuid,
                'quantity' => 1,
            ],
        ],
        customerData: [
            'customer_name' => 'Bob Buyer',
            'customer_email' => 'bob@example.com',
        ]
    );

    expect((float) $order->subtotal)->toBe(5000.0)
        ->and((float) $order->service_fee)->toBe(0.0)
        ->and((float) $order->total)->toBe(5000.0);
});
