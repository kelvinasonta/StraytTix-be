<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Enum\UserStatus;
use Modules\Auth\app\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('users table aligns with updated schema fields', function () {
    $user = User::factory()->create([
        'first_name' => 'Kelvin',
        'last_name' => 'Developer',
        'email' => 'kelvin@example.com',
        'status' => UserStatus::ACTIVE,
    ]);

    expect($user->id)->toBeInt()
        ->and($user->name)->toBe('Kelvin Developer')
        ->and($user->email)->toBe('kelvin@example.com')
        ->and($user->status)->toBe(UserStatus::ACTIVE)
        ->and($user->status->value)->toBe('active');
});

test('user model automatically derives name when first_name and last_name are provided', function () {
    $user = User::create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.doe@example.com',
        'password' => 'secret1234',
        'status' => UserStatus::ACTIVE,
    ]);

    expect($user->name)->toBe('Jane Doe');
});
