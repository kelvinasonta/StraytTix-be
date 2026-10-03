<?php

namespace Modules\Organizer\app\Actions;

use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerMember;

class AddOrganizerMemberAction
{
    public function handle(Organizer $organizer, User $user, OrganizerMemberRole $role = OrganizerMemberRole::STAFF): OrganizerMember
    {
        return OrganizerMember::create([
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }
}
