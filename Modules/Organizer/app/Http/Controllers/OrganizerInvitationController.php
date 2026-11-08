<?php

namespace Modules\Organizer\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Organizer\app\Http\Requests\StoreOrganizerInvitationRequest;
use Modules\Organizer\app\Services\OrganizerInvitationService;
use Modules\Organizer\app\Transformers\OrganizerInvitationResource;

class OrganizerInvitationController extends Controller
{
    public function __construct(
        protected OrganizerInvitationService $invitationService
    ) {}

    public function index(Request $request, string $organizerUuid): JsonResponse
    {
        $organizer = $request->attributes->get('active_organizer');
        $invitations = $this->invitationService->getAllInvitations($organizer);

        return ApiResponse::success(
            'Organizer invitations retrieved.',
            OrganizerInvitationResource::collection($invitations)->response()->getData(true)
        );
    }

    public function store(StoreOrganizerInvitationRequest $request, string $organizerUuid): JsonResponse
    {
        $organizer = $request->attributes->get('active_organizer');
        $requesterRole = $request->attributes->get('active_organizer_role');

        $this->invitationService->createInvitation(
            $organizer,
            $request->user(),
            $requesterRole,
            $request->validated('email'),
            $request->validated('role'),
            $request->validated('permissions')
        );

        return ApiResponse::success(
            'Invitation created successfully.',
            status: 201
        );
    }

    public function resend(Request $request, string $organizerUuid, string $invitationUuid): JsonResponse
    {
        $organizer = $request->attributes->get('active_organizer');
        $requesterRole = $request->attributes->get('active_organizer_role');

        $invitation = $this->invitationService->resendInvitation(
            $organizer,
            $requesterRole,
            $invitationUuid,
            $request->user()
        );

        return ApiResponse::success(
            'Invitation resent successfully.',
            new OrganizerInvitationResource($invitation)
        );
    }

    public function destroy(Request $request, string $organizerUuid, string $invitationUuid): JsonResponse
    {
        $organizer = $request->attributes->get('active_organizer');
        $requesterRole = $request->attributes->get('active_organizer_role');

        $this->invitationService->revokeInvitation(
            $organizer,
            $requesterRole,
            $invitationUuid,
            $request->user()
        );

        return ApiResponse::success('Invitation revoked successfully.');
    }
}
