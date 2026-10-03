<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerMember;

class UserBatchSeeder extends Seeder
{
    public function run(): void
    {
        // Batch create 1000 customer users
        User::factory()->count(5)->create([
            'account_type' => 'customer',
        ]);

        // Batch create 1000 organizer users with their organization records
        User::factory()->count(10)->create([
            'account_type' => 'organizer',
        ])->each(function (User $user) {
            $organizer = Organizer::create([
                'user_id' => $user->id,
                'name' => "{$user->first_name}'s Events",
                'slug' => Str::slug("{$user->first_name}-events-{$user->id}"),
                'type' => OrganizerType::INDIVIDUAL,
                'email' => $user->email,
            ]);

            OrganizerMember::create([
                'organizer_id' => $organizer->id,
                'user_id' => $user->id,
                'role' => OrganizerMemberRole::OWNER,
            ]);
        });
    }
}
