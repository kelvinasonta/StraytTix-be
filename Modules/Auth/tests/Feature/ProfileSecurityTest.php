<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\app\Models\LoginAttempts;
use Modules\Auth\app\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('user can update their profile information', function () {
    $user = User::factory()->create([
        'first_name' => 'Original',
        'last_name' => 'Name',
    ]);

    $response = $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/profile', [
        'first_name' => 'Updated',
        'last_name' => 'User',
        'phone' => '+1987654321',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.firstName', 'Updated')
        ->assertJsonPath('data.lastName', 'User');

    expect($user->fresh()->first_name)->toBe('Updated');
});

test('user can update password with valid current password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldPassword123!'),
    ]);

    $response = $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/password', [
        'current_password' => 'OldPassword123!',
        'password' => 'NewSecurePassword123!',
        'password_confirmation' => 'NewSecurePassword123!',
    ]);

    $response->assertStatus(200);
    expect(Hash::check('NewSecurePassword123!', $user->fresh()->password))->toBeTrue();
});

test('user cannot update password with incorrect current password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldPassword123!'),
    ]);

    $response = $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/password', [
        'current_password' => 'WrongPassword!',
        'password' => 'NewSecurePassword123!',
        'password_confirmation' => 'NewSecurePassword123!',
    ]);

    $response->assertStatus(422);
});

test('user can list active sessions and logout of other devices', function () {
    $user = User::factory()->create();

    $token1 = $user->createToken('Device 1');
    $token2 = $user->createToken('Device 2');

    $response = $this->withHeader('Authorization', "Bearer {$token1->plainTextToken}")
        ->getJson('/api/v1/auth/sessions');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');

    $logoutResponse = $this->withHeader('Authorization', "Bearer {$token1->plainTextToken}")
        ->postJson('/api/v1/auth/logout-others');

    $logoutResponse->assertStatus(200);

    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->id)->toBe($token1->accessToken->id);
});

test('user can revoke a specific session by token id', function () {
    $user = User::factory()->create();

    $token1 = $user->createToken('Current Device');
    $token2 = $user->createToken('Old Laptop');

    $deleteResponse = $this->withHeader('Authorization', "Bearer {$token1->plainTextToken}")
        ->deleteJson("/api/v1/auth/sessions/{$token2->accessToken->id}");

    $deleteResponse->assertStatus(200)
        ->assertJsonPath('success', true);

    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->where('id', $token2->accessToken->id)->exists())->toBeFalse();
});

test('user can view their login history', function () {
    $user = User::factory()->create();

    LoginAttempts::create([
        'user_id' => $user->id,
        'email' => $user->email,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0 Chrome',
        'status' => true,
        'metadata' => [
            'device' => 'Desktop',
            'os' => 'macOS',
            'browser' => 'Chrome',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ],
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/sessions/history');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.device', 'Desktop')
        ->assertJsonPath('data.data.0.browser', 'Chrome')
        ->assertJsonPath('data.data.0.country', 'Nigeria');
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/auth/account');

    $response->assertStatus(200);
    expect(User::find($user->id))->toBeNull();
    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});
