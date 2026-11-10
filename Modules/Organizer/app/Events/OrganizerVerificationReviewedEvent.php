<?php

declare(strict_types=1);

namespace Modules\Organizer\app\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Models\OrganizerVerification;

class OrganizerVerificationReviewedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public OrganizerVerification $verification,
        public User $reviewer,
        public string $status, // 'verified' or 'rejected'
        public ?string $reason = null
    ) {}
}
