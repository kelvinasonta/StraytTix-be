<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerMember;
use Modules\Tickets\app\Models\Ticket;
use Modules\Tickets\app\Models\TicketType;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['account_type' => 'organizer']);
    $this->organizer = Organizer::create([
        'user_id' => $this->owner->id,
        'name' => 'African Summit Group',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $this->staffUser = User::factory()->create(['account_type' => 'organizer']);
    OrganizerMember::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->staffUser->id,
        'role' => OrganizerMemberRole::STAFF,
        'status' => 'active',
    ]);

    $this->customer = User::factory()->create(['account_type' => 'customer']);

    $this->event = Event::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->owner->id,
        'title' => 'Nairobi Innovation Fair 2026',
        'description' => 'Showcasing the top innovations in Africa.',
        'event_country' => 'Kenya',
        'event_type' => EventType::PHYSICAL,
        'event_format' => 'Exhibition',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    $this->ticketType = TicketType::create([
        'event_id' => $this->event->id,
        'name' => 'Standard Access',
        'type' => 'paid',
        'price' => 10000.00,
        'currency' => 'KES',
        'status' => 'active',
    ]);

    $this->ticket = Ticket::create([
        'event_id' => $this->event->id,
        'user_id' => $this->customer->id,
        'ticket_type_id' => $this->ticketType->id,
        'ticket_number' => 'TKT-TEST1234',
        'price_paid' => 10000.00,
        'currency' => 'KES',
        'attendee_name' => 'Kwame Mensah',
        'attendee_email' => 'kwame@example.com',
        'status' => 'valid',
        'qr_code' => 'EVT-TEST-TKT-QRCODE123',
    ]);
});

test('organizer and staff can view attendees for an event', function () {
    $response = $this->actingAs($this->staffUser)
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/events/{$this->event->uuid}/attendees");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.ticketNumber', 'TKT-TEST1234')
        ->assertJsonPath('data.data.0.attendeeName', 'Kwame Mensah')
        ->assertJsonPath('data.data.0.attendeeEmail', 'kwame@example.com');
});

test('organizer can export attendees as CSV', function () {
    $response = $this->actingAs($this->owner)
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/events/{$this->event->uuid}/attendees/export");

    $response->assertStatus(200)
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('Ticket Number')
        ->toContain('Kwame Mensah')
        ->toContain('TKT-TEST1234')
        ->toContain('kwame@example.com');
});

test('staff can check in attendee using ticket number', function () {
    expect($this->ticket->fresh()->checked_in_at)->toBeNull();

    $response = $this->actingAs($this->staffUser)
        ->postJson("/api/v1/organizers/{$this->organizer->uuid}/events/{$this->event->uuid}/check-in", [
            'identifier' => 'TKT-TEST1234',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.attendeeName', 'Kwame Mensah')
        ->assertJsonPath('data.status', 'used');

    $freshTicket = $this->ticket->fresh();
    expect($freshTicket->checked_in_at)->not->toBeNull()
        ->and($freshTicket->status)->toBe('used');
});

test('staff can check in attendee using QR code', function () {
    $response = $this->actingAs($this->staffUser)
        ->postJson("/api/v1/organizers/{$this->organizer->uuid}/events/{$this->event->uuid}/check-in", [
            'identifier' => 'EVT-TEST-TKT-QRCODE123',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.attendeeName', 'Kwame Mensah');

    expect($this->ticket->fresh()->checked_in_at)->not->toBeNull();
});

test('check in fails if ticket has already been checked in', function () {
    $this->ticket->update([
        'checked_in_at' => now()->subHour(),
        'status' => 'used',
    ]);

    $response = $this->actingAs($this->staffUser)
        ->postJson("/api/v1/organizers/{$this->organizer->uuid}/events/{$this->event->uuid}/check-in", [
            'identifier' => 'TKT-TEST1234',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('check in fails if ticket belongs to a different event or does not exist', function () {
    $response = $this->actingAs($this->staffUser)
        ->postJson("/api/v1/organizers/{$this->organizer->uuid}/events/{$this->event->uuid}/check-in", [
            'identifier' => 'NON-EXISTENT-TICKET',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});
