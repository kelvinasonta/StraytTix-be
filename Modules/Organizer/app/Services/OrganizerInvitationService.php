<?php

namespace Modules\Organizer\app\Services;

use App\Services\Cache\BaseCacheManager;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Audit\app\Services\AuditService;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Events\OrganizerInvitationCreatedEvent;
use Modules\Organizer\app\Exceptions\OrganizerInvitationAlreadyAcceptedException;
use Modules\Organizer\app\Exceptions\OrganizerInvitationFailedException;
use Modules\Organizer\app\Exceptions\OrganizerInvitationNotFoundException;
use Modules\Organizer\app\Exceptions\OrganizerInvitationUnauthorizedException;
use Modules\Organizer\app\Exceptions\UserAlreadyOrganizerMemberException;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerInvitation;
use Modules\Organizer\app\Models\OrganizerMember;

class OrganizerInvitationService extends BaseCacheManager
{
    public function __construct(
        private AuditService $auditService,
    ) {}

    /**
     * Get paginated pending invitations for an organizer with tagged caching.
     */
    public function getAllInvitations(Organizer $organizer, int $perPage = 10): LengthAwarePaginator
    {
        $page = (int) request()->get('page', 1);
        $cacheKey = "organizer:{$organizer->id}:invitations:page:{$page}:limit:{$perPage}";
        $tags = $this->getCacheTags($organizer);

        $cachedData = $this->rememberWithTags(
            $tags,
            $cacheKey,
            function () use ($organizer, $perPage) {
                $paginator = OrganizerInvitation::where('organizer_id', $organizer->id)
                    ->whereNull('accepted_at')
                    ->latest()
                    ->paginate($perPage);

                return [
                    'items' => $paginator->getCollection()->map(fn (OrganizerInvitation $item) => $item->getAttributes())->all(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'path' => $paginator->path(),
                    'page_name' => $paginator->getPageName(),
                ];
            }
        );

        $items = collect($cachedData['items'] ?? [])->map(function (array $attributes) {
            $invitation = new OrganizerInvitation;
            $invitation->exists = true;

            return $invitation->newFromBuilder($attributes);
        });

        return new LengthAwarePaginator(
            $items,
            $cachedData['total'] ?? 0,
            $cachedData['per_page'] ?? $perPage,
            $cachedData['current_page'] ?? $page,
            [
                'path' => $cachedData['path'] ?? request()->url(),
                'pageName' => $cachedData['page_name'] ?? 'page',
            ]
        );
    }

    /**
     * Get a single invitation by UUID for an organizer with tagged caching.
     */
    public function getInvitationByUuid(Organizer $organizer, string $invitationUuid): OrganizerInvitation
    {
        $cacheKey = "organizer:{$organizer->id}:invitations:uuid:{$invitationUuid}";
        $tags = $this->getCacheTags($organizer);

        $attributes = $this->rememberWithTags(
            $tags,
            $cacheKey,
            fn () => OrganizerInvitation::where('organizer_id', $organizer->id)
                ->where('uuid', $invitationUuid)
                ->firstOrFail()
                ->getAttributes()
        );

        $invitation = new OrganizerInvitation;
        $invitation->exists = true;

        return $invitation->newFromBuilder($attributes);
    }

    /**
     * Clear all cached invitations for an organizer.
     */
    public function clearInvitationsCache(Organizer $organizer): void
    {
        $page = (int) request()->get('page', 1);
        $fallbackKeys = [
            "organizer:{$organizer->id}:invitations:page:{$page}:limit:10",
        ];

        $this->flushTags("organizer:{$organizer->id}:invitations", $fallbackKeys);
    }

    /**
     * Get the cache tags for an organizer's invitations.
     *
     * @return array<int, string>
     */
    public function getCacheTags(Organizer $organizer): array
    {
        return [
            'organizers',
            "organizer:{$organizer->id}:invitations",
        ];
    }

    // OrganizerInvitationExpiredException
    // OrganizerInvitationInvalidException
    // OrganizerInvitationAlreadyAcceptedException
    // OrganizerInvitationAlreadyDeclinedException
    // OrganizerInvitationNotFoundException
    //

    /**
     * Create or update a team invitation.
     */
    public function createInvitation(
        Organizer $organizer,
        User $inviter,
        OrganizerMemberRole $requesterRole,
        string $email,
        string $roleString
    ): void {
        $targetRole = OrganizerMemberRole::from($roleString);
        if (! $requesterRole->canManageRole($targetRole)) {
            throw new OrganizerInvitationUnauthorizedException;
        }

        $email = strtolower(trim($email));

        // Check if user is already a member
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            $isAlreadyMember = OrganizerMember::where('organizer_id', $organizer->id)
                ->where('user_id', $existingUser->id)
                ->exists();

            if ($isAlreadyMember) {
                throw new UserAlreadyOrganizerMemberException;
            }
        }

        $invitationToken = Str::random(40);
        $invitationTokenHashed = hash('sha256', $invitationToken);

        try {
            $invitation = DB::transaction(function () use ($organizer, $inviter, $targetRole, $email, $invitationTokenHashed) {
                $invitation = OrganizerInvitation::create(
                    [
                        'organizer_id' => $organizer->id,
                        'email' => $email,
                        'role' => $targetRole,
                        'invited_by' => $inviter->id,
                        'token' => $invitationTokenHashed,
                        'expires_at' => now()->addDays(7),
                        'accepted_at' => null,
                    ]
                );

                $this->auditService->log(
                    action: 'organizer.invitation.created',
                    description: "Organizer invitation for '{$invitation->email}' was created",
                    organizer: $organizer,
                    user: $inviter,
                    metadata: [
                        'invitation_id' => $invitation->id,
                        'invitation_uuid' => $invitation->uuid,
                    ]
                );

                return $invitation;
            });

            $emailData = [
                'email' => $invitation->email,
                'organizer_name' => $organizer->name,
                'invitation_token' => $invitationToken,
                'expires_at' => $invitation->expires_at,
            ];

            event(new OrganizerInvitationCreatedEvent($emailData));

            $this->clearInvitationsCache($organizer);
        } catch (\Exception $exception) {
            Log::error('Failed to create organizer invitation: '.$exception->getMessage());
            throw new OrganizerInvitationFailedException;
        }
    }

    /**
     * Resend an existing invitation.
     */
    public function resendInvitation(
        Organizer $organizer,
        OrganizerMemberRole $requesterRole,
        string $invitationUuid,
        ?User $user = null
    ): OrganizerInvitation {
        $invitation = OrganizerInvitation::where('organizer_id', $organizer->id)
            ->where('uuid', $invitationUuid)
            ->first();

        if (! $invitation) {
            throw new OrganizerInvitationNotFoundException;
        }

        if (! $requesterRole->canManageRole($invitation->role)) {
            throw new OrganizerInvitationUnauthorizedException;
        }

        if ($invitation->accepted_at !== null) {
            throw new OrganizerInvitationAlreadyAcceptedException;
        }

        $invitationToken = Str::random(40);
        $invitationTokenHashed = hash('sha256', $invitationToken);

        try {
            DB::transaction(function () use ($invitation, $invitationTokenHashed, $organizer, $user) {
                $invitation->update([
                    'token' => $invitationTokenHashed,
                    'expires_at' => now()->addDays(7),
                ]);

                $this->auditService->log(
                    action: 'organizer.invitation.resent',
                    description: "Organizer invitation for '{$invitation->email}' was resent",
                    organizer: $organizer,
                    user: $user,
                    metadata: [
                        'invitation_id' => $invitation->id,
                        'invitation_uuid' => $invitation->uuid,
                    ]
                );
            });

            $emailData = [
                'email' => $invitation->email,
                'organizer_name' => $organizer->name,
                'invitation_token' => $invitationToken,
                'expires_at' => $invitation->expires_at,
            ];

            event(new OrganizerInvitationCreatedEvent($emailData));

            $this->clearInvitationsCache($organizer);

            return $invitation;
        } catch (\Exception $exception) {
            Log::error('Failed to resend organizer invitation: '.$exception->getMessage());
            throw new OrganizerInvitationFailedException;
        }
    }

    /**
     * Revoke / delete a pending invitation.
     */
    public function revokeInvitation(
        Organizer $organizer,
        OrganizerMemberRole $requesterRole,
        string $invitationUuid,
        ?User $user = null
    ): bool {
        $invitation = OrganizerInvitation::where('organizer_id', $organizer->id)
            ->where('uuid', $invitationUuid)
            ->first();

        if (! $invitation) {
            throw new OrganizerInvitationNotFoundException;
        }

        if (! $requesterRole->canManageRole($invitation->role)) {
            throw new OrganizerInvitationUnauthorizedException;
        }

        if ($invitation->accepted_at !== null) {
            throw new OrganizerInvitationAlreadyAcceptedException;
        }

        $invitationEmail = $invitation->email;
        $invitationId = $invitation->id;

        try {
            DB::transaction(function () use ($invitation, $invitationEmail, $invitationId, $invitationUuid, $organizer, $user) {
                $invitation->delete();

                $this->auditService->log(
                    action: 'organizer.invitation.revoked',
                    description: "Organizer invitation for '{$invitationEmail}' was revoked",
                    organizer: $organizer,
                    user: $user,
                    metadata: [
                        'invitation_id' => $invitationId,
                        'invitation_uuid' => $invitationUuid,
                    ]
                );
            });

            $this->clearInvitationsCache($organizer);

            return true;
        } catch (\Exception $exception) {
            Log::error('Failed to revoke organizer invitation: '.$exception->getMessage());
            throw new OrganizerInvitationFailedException;
        }
    }
}
