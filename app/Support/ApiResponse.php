<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ApiResponse
{
    public static function success(string $message, mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json(self::payload(true, $message, $data, null, [], $meta), $status);
    }

    public static function error(string $message, int $status = 400, ?string $errorCode = null, array $errors = [], array $meta = []): JsonResponse
    {
        return response()->json(self::payload(false, $message, null, $errorCode, $errors, $meta), $status);
    }

    protected static function payload(bool $success, string $message, mixed $data, ?string $errorCode = null, array $errors = [], array $meta = []): array
    {
        $resolvedData = $data instanceof JsonResource ? $data->resolve(request()) : $data;

        if (is_array($resolvedData)) {
            $resolvedData = Arr::undot($resolvedData);
        }

        $payload = [
            'success' => $success,
            'message' => $message,
            'code' => $errorCode,
            'data' => $resolvedData,
            'errors' => ! empty($errors) ? (object) $errors : null,
            'meta' => ! empty($meta) ? (object) $meta : null,
        ];

        return array_filter($payload, function ($value) {
            if (is_null($value)) {
                return false;
            }

            if (is_array($value) && empty($value)) {
                return false;
            }

            if ($value instanceof \stdClass && count(get_object_vars($value)) === 0) {
                return false;
            }

            return true;
        });
    }
}

/*

<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ApiResponse
{
    public static function success(string $message,
    mixed $data = null,
    int $status = 200,
    array $meta = []): JsonResponse
    {
        return response()->json(self::payload(true, $message, $data, null, [], $meta), $status);
    }

    public static function error(string $message,
    int $status = 400,
    ?string $errorCode = null,
    array $errors = [],
    array $meta = []): JsonResponse
    {
        return response()->json(self::payload(false, $message, null, $errorCode, $errors, $meta), $status);
    }

    protected static function payload(bool $success, string $message, mixed $data, ?string $errorCode = null, array $errors = [], array $meta = []): array
    {
        $payload = [
            'success' => $success,
            'message' => $message,
            'code' => $errorCode ?? null,
            'data' => $data instanceof JsonResource ? $data->resolve(request()) : $data,
            'errors' => (object) $errors,
            'meta' => (object) $meta,
        ];

        if (is_array($payload['data'])) {
            $payload['data'] = Arr::undot($payload['data']);
        }

        return $payload;
    }
}
*/
