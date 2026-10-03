<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Auth\app\Enum\UserStatus;
use Modules\Auth\app\Events\ForgotPasswordEvent;
use Modules\Auth\app\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can request a password reset link for an active user', function () {
    Event::fake();

    $user = User::factory()->create([
        'email' => 'user@example.com',
        'status' => UserStatus::ACTIVE,
    ]);

    $response = $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'user@example.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Forgot password email sent successfully',
        ]);

    $this->assertDatabaseHas('password_reset_tokens', [
        'user_id' => $user->id,
    ]);

    Event::assertDispatched(ForgotPasswordEvent::class);
});

it('handles password reset request for non-existent email gracefully', function () {
    Event::fake();

    $response = $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Forgot password email sent successfully',
        ]);

    Event::assertNotDispatched(ForgotPasswordEvent::class);
});

it('validates email format for forgot password', function () {
    $response = $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'invalid-email-format',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('prevents inactive user from requesting password reset link', function () {
    $user = User::factory()->create([
        'email' => 'inactive@example.com',
        'status' => UserStatus::INACTIVE,
    ]);

    $response = $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'inactive@example.com',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'code' => 'USER_NOT_ACTIVE',
        ]);
});
