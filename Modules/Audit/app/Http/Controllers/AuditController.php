<?php

namespace Modules\Audit\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Audit\app\Models\ActivityLog;
use Modules\Audit\app\Services\AuditService;
use Modules\Audit\app\Transformers\ActivityLogResource;
use Modules\Organizer\app\Models\Organizer;

class AuditController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of audit logs for the active organizer.
     */
    public function index(Request $request, string $organizerUuid): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $logs = $this->auditService->getOrganizerLogs(
            $organizer,
            $request->only(['action', 'search', 'startDate', 'endDate', 'perPage'])
        );

        return ApiResponse::success(
            'Audit logs retrieved successfully.',
            ActivityLogResource::collection($logs)->response()->getData(true)
        );
    }

    /**
     * Show details of a single audit log entry.
     */
    public function show(Request $request, string $organizerUuid, int|string $id): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $log = ActivityLog::forOrganizer($organizer)
            ->with(['user'])
            ->findOrFail($id);

        return ApiResponse::success(
            'Audit log details retrieved.',
            new ActivityLogResource($log)
        );
    }
}
