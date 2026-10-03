<?php

namespace Modules\Audit\app\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Audit\app\Models\ActivityLog;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Models\Organizer;

class AuditService
{
    /**
     * Record an activity log entry.
     */
    public function log(
        string $action,
        ?string $description = null,
        ?Organizer $organizer = null,
        ?User $user = null,
        array $metadata = []
    ): ActivityLog {
        $resolvedUser = $user ?? (auth()->check() ? auth()->user() : null);

        $resolvedOrganizer = $organizer;
        if (! $resolvedOrganizer && request()) {
            $resolvedOrganizer = request()->attributes->get('active_organizer');
        }

        $ipAddress = request() ? request()->ip() : null;
        $userAgent = request() ? request()->userAgent() : null;

        return ActivityLog::create([
            'user_id' => $resolvedUser?->id,
            'organizer_id' => $resolvedOrganizer?->id,
            'organization_id' => $resolvedOrganizer?->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => empty($metadata) ? null : $metadata,
        ]);
    }

    /**
     * Get paginated activity logs for an organizer with optional filters.
     */
    public function getOrganizerLogs(Organizer $organizer, array $filters = []): LengthAwarePaginator
    {
        $query = ActivityLog::forOrganizer($organizer)
            ->with(['user']);

        if (! empty($filters['action'])) {
            $query->byAction($filters['action']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                    ->orWhere('action', 'like', $search);
            });
        }

        if (! empty($filters['startDate'])) {
            $query->where('created_at', '>=', $filters['startDate']);
        }

        if (! empty($filters['endDate'])) {
            $query->where('created_at', '<=', $filters['endDate']);
        }

        $perPage = min(max((int) ($filters['perPage'] ?? 15), 1), 100);

        return $query->latest('id')->paginate($perPage);
    }
}
