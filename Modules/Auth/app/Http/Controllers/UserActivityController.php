<?php

declare(strict_types=1);

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Audit\app\Services\AuditService;
use Modules\Audit\app\Transformers\ActivityLogResource;

class UserActivityController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get paginated activity logs for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $filters = [
            'action' => $request->query('action'),
            'search' => $request->query('search'),
            'start_date' => $request->query('start_date') ?? $request->query('startDate'),
            'end_date' => $request->query('end_date') ?? $request->query('endDate'),
            'per_page' => $request->query('per_page') ?? $request->query('perPage', 15),
        ];

        $logs = $this->auditService->getUserLogs($user, $filters);

        $transformed = ActivityLogResource::collection($logs)->response()->getData(true);

        return ApiResponse::success('User activities retrieved successfully.', $transformed);
    }
}
