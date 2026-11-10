<?php

namespace Modules\Organizer\app\Actions;

use Illuminate\Support\Str;
use Modules\Auth\app\DTOs\RegisterDTO;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerMember;

class CreateOrganizerAction
{
    public function handle(User $user, RegisterDTO $registerDto): Organizer
    {

        $organizerName = ! empty($registerDto->organizationName)
            ? $registerDto->organizationName
            : trim("{$user->first_name} {$user->last_name}");

        $organizer = Organizer::create([
            'user_id' => $user->id,
            'name' => $organizerName,
            'type' => $registerDto->organizerType,
            'slug' => Str::slug($organizerName),
            'email' => $user->email,
        ]);

        OrganizerMember::create([
            'user_id' => $user->id,
            'organizer_id' => $organizer->id,
            'role' => 'owner',
        ]);

        return $organizer;
    }
}
