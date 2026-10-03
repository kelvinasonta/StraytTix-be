<?php

declare(strict_types=1);

namespace Modules\Organizer\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Organizer\app\Http\Requests\RequestVerificationRequest;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Services\OrganizerVerificationService;
use Modules\Organizer\app\Transformers\OrganizerVerificationResource;

class OrganizerVerificationController extends Controller
{
    public function __construct(
        protected OrganizerVerificationService $verificationService
    ) {}

    /**
     * Get the current verification status and details for an organizer.
     */
    public function show(Request $request, string $organizerUuid): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $verification = $this->verificationService->getVerification($organizer);

        $statusValue = $organizer->verification_status instanceof \BackedEnum
            ? $organizer->verification_status->value
            : ($organizer->verification_status ?? 'unverified');

        return ApiResponse::success(
            'Verification status retrieved successfully.',
            [
                'verification_status' => $statusValue,
                'is_verified' => $organizer->isVerified(),
                'verified_at' => $organizer->verified_at?->toISOString(),
                'latest_verification' => $verification ? new OrganizerVerificationResource($verification) : null,
            ]
        );
    }

    /**
     * Submit a new verification request for an organizer.
     */
    public function store(RequestVerificationRequest $request, string $organizerUuid): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $verification = $this->verificationService->submitRequest(
            organizer: $organizer,
            requester: $request->user(),
            data: $request->validated()
        );

        return ApiResponse::success(
            'Verification request submitted successfully and is pending review.',
            new OrganizerVerificationResource($verification),
            201
        );
    }
}
