<?php

namespace Modules\Organizer\app\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Actions\UpdateOrganizerAction;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Exceptions\OrganizerFetchFailed;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerMember;

class OrganizerService
{
    public function __construct(
        protected UpdateOrganizerAction $updateOrganizerAction
    ) {}

    /**
     * Get paginated organizers belonging to or accessible by the user.
     */
    public function getAllForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $organizerIds = OrganizerMember::where('user_id', $user->id)->pluck('organizer_id');

        return Organizer::where('user_id', $user->id)
            ->orWhereIn('id', $organizerIds)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get the organizer belonging to the given user or find by UUID/slug with authorization.
     */
    public function getForUser(User $user, ?string $uuid = null): Organizer
    {
        if ($uuid) {
            $organizer = Organizer::where('uuid', $uuid)->orWhere('slug', $uuid)->first();
        } else {
            $organizer = Organizer::where('user_id', $user->id)->first();
        }

        if (! $organizer) {
            throw new OrganizerFetchFailed;
        }

        return $organizer;
    }

    /**
     * Create a new organizer and assign the user as owner.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Organizer
    {
        return DB::transaction(function () use ($user, $data) {
            $organizer = Organizer::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'type' => $data['type'],
                'description' => $data['description'] ?? $data['bio'] ?? null,
                'logo' => $data['logo'] ?? $data['logo_url'] ?? null,
                'cover_photo' => $data['cover_photo'] ?? $data['cover_image'] ?? $data['banner_url'] ?? $data['bannerUrl'] ?? null,
                'email' => $data['email'] ?? $user->email,
                'phone' => $data['phone'] ?? null,
                'website' => $data['website'] ?? null,
            ]);

            OrganizerMember::firstOrCreate([
                'user_id' => $user->id,
                'organizer_id' => $organizer->id,
            ], [
                'role' => OrganizerMemberRole::OWNER,
            ]);

            if ($user->account_type !== 'organizer') {
                $user->update(['account_type' => 'organizer']);
            }

            return $organizer;
        });
    }

    /**
     * Update an organizer belonging to the user.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, string $uuid, array $data): Organizer
    {
        $organizer = Organizer::where('uuid', $uuid)->orWhere('slug', $uuid)->first();

        if (! $organizer) {
            throw new OrganizerFetchFailed;
        }

        if ($organizer->user_id !== $user->id) {
            throw new OrganizerFetchFailed;
        }

        // Role verification # only owner, admin, editor can update

        $organizer = $this->updateOrganizerAction->handle($organizer, $data);
        $organizer->load(['owner'])->loadCount('members');

        return $organizer;
    }

    /**
     * Soft delete an organizer.
     */
    public function delete(User $user, string $uuid): bool
    {
        $organizer = Organizer::where('uuid', $uuid)->orWhere('slug', $uuid)->first();

        if (! $organizer) {
            throw new OrganizerFetchFailed;
        }

        if ($organizer->user_id !== $user->id) {
            throw new OrganizerFetchFailed;
        }

        return (bool) $organizer->delete();
    }
}
