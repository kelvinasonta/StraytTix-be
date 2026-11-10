<?php

declare(strict_types=1);

namespace Modules\Shared\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthCheckController extends Controller
{
    /**
     * Liveness probe (cheap ping to check if application is responding).
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ], 200);
    }

    /**
     * Readiness probe and comprehensive system health check.
     * Evaluates Database, Cache, Redis (if configured), Storage, and Queue.
     */
    public function check(): JsonResponse
    {
        $startTime = microtime(true);
        $checks = [];
        $healthy = true;

        // 1. Database Connectivity & Query Latency Check
        try {
            $dbStart = microtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $dbDuration = round((microtime(true) - $dbStart) * 1000, 2);

            $checks['database'] = [
                'status' => 'healthy',
                'latency_ms' => $dbDuration,
                'connection' => config('database.default'),
            ];
        } catch (Throwable $e) {
            $healthy = false;
            $checks['database'] = [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: '.$e->getMessage(),
            ];
        }

        // 2. Cache Store Check
        try {
            $cacheStart = microtime(true);
            $testKey = 'health_check_test_'.uniqid();
            Cache::put($testKey, 'ok', 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);
            $cacheDuration = round((microtime(true) - $cacheStart) * 1000, 2);

            if ($retrieved === 'ok') {
                $checks['cache'] = [
                    'status' => 'healthy',
                    'latency_ms' => $cacheDuration,
                    'store' => config('cache.default'),
                ];
            } else {
                $healthy = false;
                $checks['cache'] = [
                    'status' => 'unhealthy',
                    'message' => 'Cache write succeeded but read returned unexpected value.',
                    'store' => config('cache.default'),
                ];
            }
        } catch (Throwable $e) {
            $healthy = false;
            $checks['cache'] = [
                'status' => 'unhealthy',
                'message' => 'Cache check failed: '.$e->getMessage(),
                'store' => config('cache.default'),
            ];
        }

        // 3. Redis Check (if redis is configured or used for cache/queue)
        $isRedisUsed = config('cache.default') === 'redis'
            || config('queue.default') === 'redis'
            || config('session.driver') === 'redis';

        if ($isRedisUsed) {
            try {
                $redisStart = microtime(true);
                Redis::ping();
                $redisDuration = round((microtime(true) - $redisStart) * 1000, 2);

                $checks['redis'] = [
                    'status' => 'healthy',
                    'latency_ms' => $redisDuration,
                ];
            } catch (Throwable $e) {
                $healthy = false;
                $checks['redis'] = [
                    'status' => 'unhealthy',
                    'message' => 'Redis ping failed: '.$e->getMessage(),
                ];
            }
        }

        // 4. Storage Disk Write & Read Check
        try {
            $storageStart = microtime(true);
            $diskName = config('filesystems.default', 'local');
            $disk = Storage::disk($diskName);
            $testFile = 'health_check_'.uniqid().'.txt';

            $disk->put($testFile, 'health-ok');
            $content = $disk->get($testFile);
            $disk->delete($testFile);
            $storageDuration = round((microtime(true) - $storageStart) * 1000, 2);

            if ($content === 'health-ok') {
                $checks['storage'] = [
                    'status' => 'healthy',
                    'disk' => $diskName,
                    'latency_ms' => $storageDuration,
                ];
            } else {
                $healthy = false;
                $checks['storage'] = [
                    'status' => 'unhealthy',
                    'disk' => $diskName,
                    'message' => 'Storage read returned unexpected content.',
                ];
            }
        } catch (Throwable $e) {
            $healthy = false;
            $checks['storage'] = [
                'status' => 'unhealthy',
                'disk' => config('filesystems.default', 'local'),
                'message' => 'Storage check failed: '.$e->getMessage(),
            ];
        }

        $totalDuration = round((microtime(true) - $startTime) * 1000, 2);

        $payload = [
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'version' => config('app.version', '1.0.0'),
            'total_latency_ms' => $totalDuration,
            'checks' => $checks,
        ];

        return response()->json($payload, $healthy ? 200 : 503);
    }
}
