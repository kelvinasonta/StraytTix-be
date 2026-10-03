<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Modules\Tickets\app\Models\Ticket;
use Modules\Tickets\app\Models\TicketType;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['account_type' => 'organizer']);
    $this->organizer = Organizer::create([
        'user_id' => $this->owner->id,
        'name' => 'African Tech Network',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $this->user = User::factory()->create(['account_type' => 'customer']);

    $this->event = Event::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->owner->id,
        'title' => 'Lagos Design Summit 2026',
        'description' => 'A premier design gathering.',
        'event_country' => 'Nigeria',
        'event_type' => EventType::PHYSICAL,
        'event_format' => 'Conference',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);
});

test('organizer can create a ticket type for an event', function () {
    $response = $this->actingAs($this->owner)
        ->postJson("/api/v1/events/{$this->event->uuid}/ticket-types", [
            'name' => 'VIP All-Access',
            'type' => 'paid',
            'description' => 'Access to all stages and backstage networking.',
            'ticket_capacity' => 'limited',
            'quantity' => 100,
            'price' => 50000.00,
            'currency' => 'NGN',
            'status' => 'active',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'VIP All-Access')
        ->assertJsonPath('data.type', 'paid')
        ->assertJsonPath('data.price', 50000)
        ->assertJsonPath('data.quantity', 100);

    $this->assertDatabaseHas('ticket_types', [
        'event_id' => $this->event->id,
        'name' => 'VIP All-Access',
    ]);
});

test('anyone can list available ticket types for an event', function () {
    TicketType::create([
        'event_id' => $this->event->id,
        'name' => 'Early Bird',
        'type' => 'paid',
        'price' => 25000.00,
        'currency' => 'NGN',
        'quantity' => 50,
        'status' => 'active',
    ]);

    TicketType::create([
        'event_id' => $this->event->id,
        'name' => 'Free RSVP',
        'type' => 'free',
        'price' => 0.00,
        'currency' => 'NGN',
        'ticket_capacity' => 'unlimited',
        'status' => 'active',
    ]);

    $response = $this->getJson("/api/v1/events/{$this->event->uuid}/ticket-types");

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $data = $response->json('data');
    expect($data)->toHaveCount(2);
});

test('organizer can update a ticket type', function () {
    $ticketType = TicketType::create([
        'event_id' => $this->event->id,
        'name' => 'Regular Pass',
        'type' => 'paid',
        'price' => 15000.00,
        'currency' => 'NGN',
        'quantity' => 200,
        'status' => 'active',
    ]);

    $response = $this->actingAs($this->owner)
        ->putJson("/api/v1/ticket-types/{$ticketType->uuid}", [
            'name' => 'Standard Pass',
            'price' => 18000.00,
            'quantity' => 250,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Standard Pass')
        ->assertJsonPath('data.price', 18000)
        ->assertJsonPath('data.quantity', 250);

    expect($ticketType->fresh()->name)->toBe('Standard Pass');
});

test('organizer can delete a ticket type', function () {
    TicketType::create([
        'event_id' => $this->event->id,
        'name' => 'Main Pass',
        'type' => 'paid',
        'price' => 5000.00,
        'currency' => 'NGN',
        'status' => 'active',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $this->event->id,
        'name' => 'Temp Pass',
        'type' => 'free',
        'price' => 0.00,
        'currency' => 'NGN',
        'status' => 'active',
    ]);

    $response = $this->actingAs($this->owner)
        ->deleteJson("/api/v1/ticket-types/{$ticketType->uuid}");

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('ticket_types', ['id' => $ticketType->id]);
});

test('organizer cannot delete the last ticket type of a published event', function () {
    $ticketType = TicketType::create([
        'event_id' => $this->event->id,
        'name' => 'Sole Pass',
        'type' => 'free',
        'price' => 0.00,
        'currency' => 'NGN',
        'status' => 'active',
    ]);

    $response = $this->actingAs($this->owner)
        ->deleteJson("/api/v1/ticket-types/{$ticketType->uuid}");

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('user can reserve/issue a ticket for an event', function () {
    $ticketType = TicketType::create([
        'event_id' => $this->event->id,
        'name' => 'Free Community Pass',
        'type' => 'free',
        'price' => 0.00,
        'currency' => 'NGN',
        'quantity' => 100,
        'status' => 'active',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/tickets', [
            'ticket_type_uuid' => $ticketType->uuid,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'valid');

    expect($response->json('data.ticketNumber'))->toStartWith('TKT-');

    $this->assertDatabaseHas('tickets', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'ticket_type_id' => $ticketType->id,
    ]);

    expect($ticketType->fresh()->sold_quantity)->toBe(1);
});

test('user cannot issue ticket for sold out ticket type', function () {
    $ticketType = TicketType::create([
        'event_id' => $this->event->id,
        'name' => 'Exclusive Seat',
        'type' => 'paid',
        'price' => 100000.00,
        'currency' => 'NGN',
        'ticket_capacity' => 'limited',
        'quantity' => 1,
        'sold_quantity' => 1, // Already sold out
        'status' => 'active',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/tickets', [
            'ticket_type_uuid' => $ticketType->uuid,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'TICKET_UNAVAILABLE');
});

test('user can view their list of tickets', function () {
    $ticketType = TicketType::create([
        'event_id' => $this->event->id,
        'name' => 'General Admission',
        'type' => 'free',
        'price' => 0.00,
        'currency' => 'NGN',
        'status' => 'active',
    ]);

    Ticket::create([
        'event_id' => $this->event->id,
        'user_id' => $this->user->id,
        'ticket_type_id' => $ticketType->id,
        'price_paid' => 0.00,
        'currency' => 'NGN',
        'status' => 'valid',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/tickets');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
});

test('user can view single ticket details', function () {
    $ticketType = TicketType::create([
        'event_id' => $this->event->id,
        'name' => 'General Admission',
        'type' => 'free',
        'price' => 0.00,
        'currency' => 'NGN',
        'status' => 'active',
    ]);

    $ticket = Ticket::create([
        'event_id' => $this->event->id,
        'user_id' => $this->user->id,
        'ticket_type_id' => $ticketType->id,
        'price_paid' => 0.00,
        'currency' => 'NGN',
        'status' => 'valid',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/tickets/{$ticket->uuid}");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $ticket->uuid)
        ->assertJsonPath('data.status', 'valid');
});
