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

test('staff can undo check in using ticket identifier', function () {
    $this->ticket->update([
        'checked_in_at' => now()->subMinutes(10),
        'status' => 'used',
    ]);

    $response = $this->actingAs($this->staffUser)
        ->postJson("/api/v1/organizers/{$this->organizer->uuid}/events/{$this->event->uuid}/check-in/undo", [
            'identifier' => 'TKT-TEST1234',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'valid')
        ->assertJsonPath('data.checkedInAt', null);

    $fresh = $this->ticket->fresh();
    expect($fresh->checked_in_at)->toBeNull()
        ->and($fresh->status)->toBe('valid');
});

test('staff can undo check in using ticket uuid endpoint', function () {
    $this->ticket->update([
        'checked_in_at' => now()->subMinutes(10),
        'status' => 'used',
    ]);

    $response = $this->actingAs($this->staffUser)
        ->postJson("/api/v1/organizers/{$this->organizer->uuid}/events/{$this->event->uuid}/attendees/{$this->ticket->uuid}/undo-check-in");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'valid')
        ->assertJsonPath('data.checkedInAt', null);

    expect($this->ticket->fresh()->checked_in_at)->toBeNull();
});

test('undo check in fails if ticket is not checked in', function () {
    expect($this->ticket->checked_in_at)->toBeNull();

    $response = $this->actingAs($this->staffUser)
        ->postJson("/api/v1/organizers/{$this->organizer->uuid}/events/{$this->event->uuid}/check-in/undo", [
            'identifier' => 'TKT-TEST1234',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Ticket is not currently checked in.');
});

test('gate statistics endpoint returns correct counts and recent check ins', function () {
    // Ticket 1: Checked in
    $this->ticket->update([
        'checked_in_at' => now()->subMinutes(15),
        'status' => 'used',
    ]);

    // Ticket 2: Checked in more recently
    $ticket2 = Ticket::create([
        'event_id' => $this->event->id,
        'user_id' => $this->customer->id,
        'ticket_type_id' => $this->ticketType->id,
        'ticket_number' => 'TKT-SECOND222',
        'price_paid' => 10000.00,
        'currency' => 'KES',
        'attendee_name' => 'Amina Bello',
        'attendee_email' => 'amina@example.com',
        'status' => 'used',
        'checked_in_at' => now()->subMinutes(5),
    ]);

    // Ticket 3: Pending check-in
    $ticket3 = Ticket::create([
        'event_id' => $this->event->id,
        'user_id' => $this->customer->id,
        'ticket_type_id' => $this->ticketType->id,
        'ticket_number' => 'TKT-THIRD333',
        'price_paid' => 10000.00,
        'currency' => 'KES',
        'attendee_name' => 'Chidi Okonkwo',
        'attendee_email' => 'chidi@example.com',
        'status' => 'valid',
        'checked_in_at' => null,
    ]);

    $response = $this->actingAs($this->staffUser)
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/events/{$this->event->uuid}/check-in/stats");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_tickets', 3)
        ->assertJsonPath('data.checked_in_count', 2)
        ->assertJsonPath('data.pending_count', 1)
        ->assertJsonPath('data.check_in_rate', 66.67)
        ->assertJsonCount(2, 'data.recent_check_ins')
        ->assertJsonPath('data.recent_check_ins.0.ticket_number', 'TKT-SECOND222')
        ->assertJsonPath('data.recent_check_ins.0.attendee_name', 'Amina Bello')
        ->assertJsonPath('data.recent_check_ins.0.ticket_tier', 'Standard Access')
        ->assertJsonPath('data.recent_check_ins.1.ticket_number', 'TKT-TEST1234');
});

test('unauthorized users cannot access check-in stats or undo check-in', function () {
    $unauthorizedUser = User::factory()->create(['account_type' => 'customer']);

    $responseStats = $this->actingAs($unauthorizedUser)
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/events/{$this->event->uuid}/check-in/stats");

    $responseStats->assertStatus(403);

    $responseUndo = $this->actingAs($unauthorizedUser)
        ->postJson("/api/v1/organizers/{$this->organizer->uuid}/events/{$this->event->uuid}/check-in/undo", [
            'identifier' => 'TKT-TEST1234',
        ]);

    $responseUndo->assertStatus(403);
});
