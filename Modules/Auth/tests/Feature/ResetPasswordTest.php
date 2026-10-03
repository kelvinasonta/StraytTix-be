<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Auth\app\Enum\UserStatus;
use Modules\Auth\app\Models\PasswordResetToken;
use Modules\Auth\app\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can successfully reset password with valid token', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('OldPassword123!'),
        'status' => UserStatus::ACTIVE,
    ]);

    $rawToken = Str::random(60);

    PasswordResetToken::create([
        'user_id' => $user->id,
        'token' => hash('sha256', $rawToken),
        'expires_at' => now()->addMinutes(30),
    ]);

    $response = $this->postJson('/api/v1/auth/password/reset', [
        'token' => $rawToken,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Password reset successful.',
        ]);

    $this->assertDatabaseMissing('password_reset_tokens', [
        'user_id' => $user->id,
    ]);

    $user->refresh();
    expect(Hash::check('NewPassword123!', $user->password))->toBeTrue();
});

it('fails to reset password with invalid token', function () {
    $response = $this->postJson('/api/v1/auth/password/reset', [
        'token' => 'invalid-token-string',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'code' => 'INVALID_RESET_TOKEN',
        ]);
});

it('fails to reset password with expired token', function () {
    $user = User::factory()->create([
        'status' => UserStatus::ACTIVE,
    ]);

    $rawToken = Str::random(60);

    PasswordResetToken::create([
        'user_id' => $user->id,
        'token' => hash('sha256', $rawToken),
        'expires_at' => now()->subMinute(),
    ]);

    $response = $this->postJson('/api/v1/auth/password/reset', [
        'token' => $rawToken,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'code' => 'EXPIRED_RESET_TOKEN',
        ]);

    $this->assertDatabaseMissing('password_reset_tokens', [
        'user_id' => $user->id,
    ]);
});

it('validates password strength and confirmation', function () {
    $response = $this->postJson('/api/v1/auth/password/reset', [
        'token' => 'some-token',
        'password' => 'weak',
        'password_confirmation' => 'mismatch',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});
