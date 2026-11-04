<?php

declare(strict_types=1);

namespace Modules\Admin\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admin\app\Transformers\AdminAuditLogResource;
use Modules\Audit\app\Models\ActivityLog;
use Modules\Audit\app\Services\AuditService;

class AdminAuditLogController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List all platform activity and administrative audit logs with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'action',
            'target_type',
            'target_id',
            'user_id',
            'search',
            'start_date',
            'end_date',
            'per_page',
        ]);

        $logs = $this->auditService->getAdminLogs($filters);

        return ApiResponse::success(
            'Audit logs retrieved successfully.',
            AdminAuditLogResource::collection($logs)->response()->getData(true)
        );
    }

    /**
     * Get details of a single audit log entry.
     */
    public function show(int $id): JsonResponse
    {
        $log = ActivityLog::with(['user', 'organizer'])->findOrFail($id);

        return ApiResponse::success(
            'Audit log details retrieved successfully.',
            new AdminAuditLogResource($log)
        );
    }
}
