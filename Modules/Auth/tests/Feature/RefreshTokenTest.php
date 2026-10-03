<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('authenticated user can refresh their token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test Device');

    $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->postJson('/api/v1/auth/refresh');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['message', 'data' => ['user', 'token']]);

    // Old token should be deleted
    expect($user->tokens()->where('id', $token->accessToken->id)->first())->toBeNull();

    // New token should be valid for subsequent request
    $newToken = $response->json('data.token');
    $meResponse = $this->withHeader('Authorization', "Bearer {$newToken}")
        ->getJson('/api/v1/auth/me');

    $meResponse->assertStatus(200);
});

test('unauthenticated request to refresh token fails', function () {
    $response = $this->postJson('/api/v1/auth/refresh');

    $response->assertStatus(401);
});
