<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Auth\app\Events\UserRegisteredEvent;
use Modules\Auth\app\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can register a new user', function () {
    $this->withoutExceptionHandling();
    Event::fake([UserRegisteredEvent::class]);

    $response = $this->postJson('/api/v1/auth/register', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '+1234567890',
        'timezone' => 'America/New_York',
        'account_type' => 'organizer',
        'organizer_type' => 'individual',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['message', 'data' => ['user', 'organizer', 'token']]);

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'phone' => '+1234567890',
        'timezone' => 'America/New_York',
    ]);

    $this->assertDatabaseHas('organizers', [
        'email' => 'john@example.com',
        'type' => 'individual',
    ]);

    Event::assertDispatched(UserRegisteredEvent::class);
});

it('cannot register with duplicate email', function () {
    User::factory()->create(['email' => 'john@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'account_type' => 'customer',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('cannot register with weak password', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('can register as an organization with a custom organization name', function () {
    Event::fake([UserRegisteredEvent::class]);

    $response = $this->postJson('/api/v1/auth/register', [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'jane@acmeevents.com',
        'account_type' => 'organizer',
        'organizer_type' => 'organization',
        'organization_name' => 'Acme Global Events Ltd',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.organizer.name', 'Acme Global Events Ltd')
        ->assertJsonPath('data.organizer.type', 'organization');

    $this->assertDatabaseHas('organizers', [
        'name' => 'Acme Global Events Ltd',
        'type' => 'organization',
    ]);
});
