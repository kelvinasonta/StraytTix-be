<?php

namespace Modules\Organizer\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Modules\Audit\app\Services\AuditService;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Http\Requests\StoreOrganizerMemberRequest;
use Modules\Organizer\app\Http\Requests\UpdateOrganizerMemberRequest;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerMember;
use Modules\Organizer\app\Transformers\OrganizerMemberResource;

class OrganizerMemberController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(Request $request, string $organizerUuid)
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $members = OrganizerMember::where('organizer_id', $organizer->id)
            ->with(['user'])
            ->latest()
            ->paginate(15);

        return ApiResponse::success(
            'Organizer members retrieved.',
            OrganizerMemberResource::collection($members)->response()->getData(true)
        );
    }

    public function store(StoreOrganizerMemberRequest $request, string $organizerUuid)
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');
        /** @var OrganizerMemberRole $requesterRole */
        $requesterRole = $request->attributes->get('active_organizer_role');

        $targetRole = OrganizerMemberRole::from($request->validated('role'));

        if (! $requesterRole->canManageRole($targetRole)) {
            return ApiResponse::error("You do not have permission to assign the '{$targetRole->value}' role.", 403);
        }

        $userToAdd = User::where('email', $request->validated('email'))->firstOrFail();

        $existing = OrganizerMember::where('organizer_id', $organizer->id)
            ->where('user_id', $userToAdd->id)
            ->first();

        if ($existing) {
            return ApiResponse::error('User is already a member of this organizer.', 422);
        }

        if ($userToAdd->account_type !== 'organizer') {
            $userToAdd->update(['account_type' => 'organizer']);
        }

        $member = OrganizerMember::create([
            'organizer_id' => $organizer->id,
            'user_id' => $userToAdd->id,
            'role' => $targetRole,
        ]);

        $member->load(['user']);

        $this->auditService->log(
            action: 'member.added',
            description: "User '{$userToAdd->name}' ({$userToAdd->email}) was added as {$targetRole->value}",
            organizer: $organizer,
            user: $request->user(),
            metadata: [
                'member_id' => $member->id,
                'user_id' => $userToAdd->id,
                'role' => $targetRole->value,
            ]
        );

        return ApiResponse::success(
            'Member added successfully.',
            new OrganizerMemberResource($member),
            201
        );
    }

    public function update(UpdateOrganizerMemberRequest $request, string $organizerUuid, string $memberUuid)
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');
        /** @var OrganizerMemberRole $requesterRole */
        $requesterRole = $request->attributes->get('active_organizer_role');

        $member = OrganizerMember::where('organizer_id', $organizer->id)
            ->where(function ($query) use ($memberUuid) {
                $query->where('id', $memberUuid)
                    ->orWhereHas('user', fn ($q) => $q->where('uuid', $memberUuid));
            })
            ->firstOrFail();

        if ($member->user_id === $organizer->user_id) {
            return ApiResponse::error('Cannot modify the primary owner role.', 422);
        }

        $newRole = OrganizerMemberRole::from($request->validated('role'));

        if (! $requesterRole->canManageRole($member->role) || ! $requesterRole->canManageRole($newRole)) {
            return ApiResponse::error('You do not have permission to modify this member or assign this role.', 403);
        }

        $member->update(['role' => $newRole]);
        $member->load(['user']);

        $this->auditService->log(
            action: 'member.role_updated',
            description: "Role for member '{$member->user->name}' was updated to {$newRole->value}",
            organizer: $organizer,
            user: $request->user(),
            metadata: [
                'member_id' => $member->id,
                'user_id' => $member->user_id,
                'new_role' => $newRole->value,
            ]
        );

        return ApiResponse::success(
            'Member role updated successfully.',
            new OrganizerMemberResource($member)
        );
    }

    public function destroy(Request $request, string $organizerUuid, string $memberUuid)
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');
        /** @var OrganizerMemberRole $requesterRole */
        $requesterRole = $request->attributes->get('active_organizer_role');

        $member = OrganizerMember::where('organizer_id', $organizer->id)
            ->where(function ($query) use ($memberUuid) {
                $query->where('id', $memberUuid)
                    ->orWhereHas('user', fn ($q) => $q->where('uuid', $memberUuid));
            })
            ->firstOrFail();

        if ($member->user_id === $organizer->user_id) {
            return ApiResponse::error('Cannot remove the owner of the organizer.', 422);
        }

        if (! $requesterRole->canManageRole($member->role)) {
            return ApiResponse::error('You do not have permission to remove this member.', 403);
        }

        $memberName = $member->user?->name ?? 'Unknown';
        $memberUserId = $member->user_id;
        $memberId = $member->id;

        $member->delete();

        $this->auditService->log(
            action: 'member.removed',
            description: "Member '{$memberName}' was removed from organizer",
            organizer: $organizer,
            user: $request->user(),
            metadata: [
                'member_id' => $memberId,
                'user_id' => $memberUserId,
            ]
        );

        return ApiResponse::success('Member removed successfully.');
    }
}
