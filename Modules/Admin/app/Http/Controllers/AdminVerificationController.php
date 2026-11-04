<?php

declare(strict_types=1);

namespace Modules\Admin\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admin\app\Http\Requests\ReviewVerificationRequest;
use Modules\Audit\app\Services\AuditService;
use Modules\Organizer\app\Services\OrganizerVerificationService;
use Modules\Organizer\app\Transformers\OrganizerVerificationResource;

class AdminVerificationController extends Controller
{
    public function __construct(
        protected OrganizerVerificationService $verificationService,
        protected AuditService $auditService
    ) {}

    /**
     * List all verification requests with optional status filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $verifications = $this->verificationService->listVerifications(
            $request->only(['status', 'per_page'])
        );

        return ApiResponse::success(
            'Verification requests retrieved successfully.',
            OrganizerVerificationResource::collection($verifications)->response()->getData(true)
        );
    }

    /**
     * Get details of a single verification request.
     */
    public function show(string $uuid): JsonResponse
    {
        $verification = $this->verificationService->findByUuid($uuid);

        return ApiResponse::success(
            'Verification details retrieved successfully.',
            new OrganizerVerificationResource($verification)
        );
    }

    /**
     * Review (approve or reject) a verification request.
     */
    public function review(ReviewVerificationRequest $request, string $uuid): JsonResponse
    {
        $verification = $this->verificationService->findByUuid($uuid);
        $action = $request->input('action');

        if ($action === 'approve') {
            $updated = $this->verificationService->approve($verification, $request->user());
            $message = 'Organizer verification approved successfully.';
            $this->auditService->log(
                action: 'organizer.verification_approved',
                description: "Approved KYC verification for organizer [{$verification->organizer_id}] ({$verification->business_name}).",
                user: $request->user(),
                targetType: 'OrganizerVerification',
                targetId: $verification->uuid,
            );
        } else {
            $reason = (string) $request->input('reason');
            $updated = $this->verificationService->reject(
                verification: $verification,
                reviewer: $request->user(),
                reason: $reason
            );
            $message = 'Organizer verification rejected.';
            $this->auditService->log(
                action: 'organizer.verification_rejected',
                description: "Rejected KYC verification for organizer [{$verification->organizer_id}] ({$verification->business_name}).",
                user: $request->user(),
                targetType: 'OrganizerVerification',
                targetId: $verification->uuid,
                reason: $reason,
            );
        }

        return ApiResponse::success(
            $message,
            new OrganizerVerificationResource($updated)
        );
    }
}
