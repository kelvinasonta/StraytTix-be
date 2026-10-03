<?php

declare(strict_types=1);

namespace Modules\Organizer\app\Enum;

enum OrganizerVerificationStatus: string
{
    case UNVERIFIED = 'unverified';
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';
}
