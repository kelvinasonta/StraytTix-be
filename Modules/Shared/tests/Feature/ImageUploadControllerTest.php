<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\app\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('authenticated user can upload an event banner via HTTP endpoint', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $file = UploadedFile::fake()->image('banner.jpg', 1200, 600);

    $response = $this->postJson('/api/v1/uploads/images', [
        'image' => $file,
        'preset' => 'event_banner',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Image uploaded successfully.',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'url',
                'path',
                'disk',
                'filename',
                'mime_type',
                'extension',
                'size_bytes',
                'width',
                'height',
            ],
        ]);
});

test('unauthenticated user cannot upload images', function () {
    $file = UploadedFile::fake()->image('banner.jpg', 800, 600);

    $response = $this->postJson('/api/v1/uploads/images', [
        'image' => $file,
    ]);

    $response->assertStatus(401);
});

test('rejects SVG file upload via HTTP endpoint to protect against XSS', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert("XSS")</script></svg>';
    $file = UploadedFile::fake()->createWithContent('malicious.svg', $svgContent);

    $response = $this->postJson('/api/v1/uploads/images', [
        'image' => $file,
    ]);

    $response->assertStatus(422);
});

test('allows deleting an uploaded image via HTTP endpoint', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $file = UploadedFile::fake()->image('delete_me.png', 400, 400);

    $uploadRes = $this->postJson('/api/v1/uploads/images', [
        'image' => $file,
    ]);

    $uploadRes->assertStatus(201);
    $path = $uploadRes->json('data.path');

    expect(Storage::disk('public')->exists($path))->toBeTrue();

    $deleteRes = $this->deleteJson('/api/v1/uploads/images', [
        'path' => $path,
    ]);

    $deleteRes->assertStatus(200);
    expect(Storage::disk('public')->exists($path))->toBeFalse();
});
