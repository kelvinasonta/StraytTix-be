<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Auth\app\Actions\CreateEmailVerificationAction;
use Modules\Auth\app\Events\EmailVerifiedEvent;
use Modules\Auth\app\Events\ResendEmailVerificationEvent;
use Modules\Auth\app\Models\EmailVerification;
use Modules\Auth\app\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('user can verify their email with a valid token', function () {
    Event::fake([EmailVerifiedEvent::class]);

    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $createAction = app(CreateEmailVerificationAction::class);
    $result = $createAction->handle([
        'user_id' => $user->id,
        'expires_at' => now()->addMinutes(30),
    ]);

    $response = $this->postJson('/api/v1/auth/email/verify', [
        'token' => $result['token'],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', $user->email);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(EmailVerifiedEvent::class);

    $this->assertDatabaseMissing('email_verifications', [
        'user_id' => $user->id,
    ]);
});

test('email verification fails with invalid token', function () {
    $response = $this->postJson('/api/v1/auth/email/verify', [
        'token' => 'invalid-token-here',
    ]);

    $response->assertStatus(400);
});

test('email verification fails when token is expired', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $rawToken = 'valid-token-string-here';
    EmailVerification::create([
        'user_id' => $user->id,
        'token' => hash('sha256', $rawToken),
        'expires_at' => now()->subMinutes(5),
    ]);

    $response = $this->postJson('/api/v1/auth/email/verify', [
        'token' => $rawToken,
    ]);

    $response->assertStatus(400);
});

test('resend email verification generates matching token and allows successful verification', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $dispatchedToken = null;
    Event::listen(ResendEmailVerificationEvent::class, function ($event) use (&$dispatchedToken) {
        $dispatchedToken = $event->emailData['token'];
    });

    $resendResponse = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/email/resend');

    $resendResponse->assertStatus(200);
    expect($dispatchedToken)->not()->toBeNull();

    // Verify token exists in database as SHA256 hash
    $this->assertDatabaseHas('email_verifications', [
        'user_id' => $user->id,
        'token' => hash('sha256', $dispatchedToken),
    ]);

    // Now verify with the dispatched token
    $verifyResponse = $this->postJson('/api/v1/auth/email/verify', [
        'token' => $dispatchedToken,
    ]);

    $verifyResponse->assertStatus(200)
        ->assertJsonPath('success', true);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('resending verification fails if email is already verified', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/email/resend');

    $response->assertStatus(422)
        ->assertJsonPath('code', 'EMAIL_ALREADY_VERIFIED');
});

test('resending verification is rate limited to 15 minutes', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    // First send succeeds
    $res1 = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/email/resend');
    $res1->assertStatus(200);

    // Immediate second send fails due to rate limiting
    $res2 = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/email/resend');
    $res2->assertStatus(429);
});
