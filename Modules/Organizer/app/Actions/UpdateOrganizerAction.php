<?php

namespace Modules\Organizer\app\Actions;

use Modules\Organizer\app\Models\Organizer;

class UpdateOrganizerAction
{
    /**
     * Update an organizer instance with validated data.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Organizer $organizer, array $data): Organizer
    {
        // Normalize cover photo
        if (! array_key_exists('cover_photo', $data)) {
            $cover = $data['cover_image'] ?? $data['banner_url'] ?? $data['bannerUrl'] ?? null;
            if ($cover !== null) {
                $data['cover_photo'] = $cover;
            }
        }

        // Normalize bio to description
        if (! array_key_exists('description', $data) && array_key_exists('bio', $data)) {
            $data['description'] = $data['bio'];
        }

        // Normalize logo
        if (! array_key_exists('logo', $data) && array_key_exists('logo_url', $data)) {
            $data['logo'] = $data['logo_url'];
        }

        // Remove aliases that are not table columns
        unset($data['cover_image'], $data['banner_url'], $data['bannerUrl'], $data['bio'], $data['logo_url']);

        $organizer->update($data);

        return $organizer;
    }
}
