<?php

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\app\Http\Requests\AcceptInvitationRequest;
use Modules\Auth\app\Http\Requests\AcceptInvitationTokenRequest;
use Modules\Auth\app\Models\User;
use Modules\Auth\app\Services\AcceptInvitationService;
use Modules\Auth\app\Transformers\UserResource;
use Modules\Organizer\app\Models\OrganizerMember;
use Modules\Organizer\app\Services\OrganizerInvitationService;
use Modules\Organizer\app\Transformers\OrganizerResource;

class AcceptInvitationController extends Controller
{
    public function __construct(
        private AcceptInvitationService $acceptInvitationService,
        private OrganizerInvitationService $organizerInvitationService,
    ) {}

    public function verify(AcceptInvitationTokenRequest $request): JsonResponse
    {
        $token = $request->validated('token');
        $invitation = $this->acceptInvitationService->verify($token);
        $userExists = User::where('email', $invitation->email)->exists();

        return ApiResponse::success(
            'Invitation token is valid.',
            [
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                'organizer_name' => $invitation->organizer?->name,
                'user_exists' => $userExists,
                'expires_at' => $invitation->expires_at,
            ],
            200
        );
    }

    public function store(AcceptInvitationRequest $request): JsonResponse
    {
        $token = $request->validated('token');
        $invitation = $this->acceptInvitationService->verify($token);

        [$user, $authToken] = DB::transaction(function () use ($request, $invitation) {
            $user = User::where('email', $invitation->email)->first();

            if (! $user) {
                $user = User::create([
                    'first_name' => $request->validated('first_name'),
                    'last_name' => $request->validated('last_name'),
                    'email' => $invitation->email,
                    'password' => Hash::make($request->validated('password')),
                    'account_type' => 'organizer',
                    'email_verified_at' => now(),
                ]);
            } else {
                if ($user->account_type !== 'organizer') {
                    $user->update(['account_type' => 'organizer']);
                }
            }

            OrganizerMember::updateOrCreate(
                [
                    'organizer_id' => $invitation->organizer_id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $invitation->role,
                    'permissions' => $invitation->permissions,
                ]
            );

            $invitation->update(['accepted_at' => now()]);

            $this->organizerInvitationService->clearInvitationsCache($invitation->organizer);

            $token = $user->createToken('invitation_accepted')->plainTextToken;

            return [$user, $token];
        });

        $organizer = $invitation->organizer->fresh(['members']);

        return ApiResponse::success(
            'Invitation accepted successfully.',
            [
                'user' => new UserResource($user),
                'organizer' => new OrganizerResource($organizer, true, $user),
                'token' => $authToken,
            ],
            200
        );
    }
}
