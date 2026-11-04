<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\app\Enum\UserStatus;
use Modules\Auth\app\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can login a user and return a token', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('Password123!'),
        'status' => UserStatus::ACTIVE,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['message', 'data' => ['token']]);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('cannot login with invalid credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('Password123!'),
        'status' => UserStatus::ACTIVE,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'WrongPassword123!',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'code' => 'INVALID_CREDENTIALS',
        ]);

    expect($user->fresh()->last_login_at)->toBeNull();
});

it('requires valid email and password format', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'not-an-email',
        'password' => 'short',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});
