<?php

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

it('returns ok on liveness ping endpoint', function () {
    $response = $this->getJson(route('api.health.ping'));

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
        ])
        ->assertJsonStructure([
            'status',
            'timestamp',
        ]);
});

it('returns healthy status with system components on health check', function () {
    $response = $this->getJson(route('api.health.check'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'timestamp',
            'environment',
            'version',
            'total_latency_ms',
            'checks' => [
                'database',
                'cache',
                'storage',
            ],
        ]);
});

it('returns 503 unhealthy when database connection fails', function () {
    DB::shouldReceive('connection->getPdo')
        ->andThrow(new Exception('Database unreachable'));

    $response = $this->getJson(route('api.health.check'));

    $response->assertStatus(503)
        ->assertJson([
            'status' => 'unhealthy',
            'checks' => [
                'database' => [
                    'status' => 'unhealthy',
                ],
            ],
        ]);
});
