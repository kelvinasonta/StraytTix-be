<?php

namespace Modules\Auth\app\Services;

use Modules\Auth\app\Exceptions\InvitationTokenInvalid;
use Modules\Organizer\app\Models\OrganizerInvitation;

class AcceptInvitationService
{
    public function verify(string $token): OrganizerInvitation
    {
        $tokenHash = hash('sha256', $token);

        $invitation = OrganizerInvitation::where('token', $tokenHash)
            ->orWhere('token', $token)
            ->first();

        if (! $invitation || ! $invitation->isPending()) {
            throw new InvitationTokenInvalid;
        }

        return $invitation;
    }
}
