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
        array $metadata = [],
        ?string $targetType = null,
        ?string $targetId = null,
        ?string $reason = null
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
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'description' => $description,
            'reason' => $reason,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => empty($metadata) ? null : $metadata,
        ]);
    }

    /**
     * Get paginated platform-wide activity and audit logs for administrators.
     */
    public function getAdminLogs(array $filters = []): LengthAwarePaginator
    {
        $query = ActivityLog::query()->with(['user', 'organizer']);

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['target_type'])) {
            $query->where('target_type', $filters['target_type']);
        }

        if (! empty($filters['target_id'])) {
            $query->where('target_id', $filters['target_id']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                    ->orWhere('action', 'like', $search)
                    ->orWhere('reason', 'like', $search);
            });
        }

        if (! empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->latest('id')->paginate($perPage);
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

    /**
     * Get paginated activity logs for an individual user (self-service user audit trail).
     */
    public function getUserLogs(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = ActivityLog::forUser($user);

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

        if (! empty($filters['startDate']) || ! empty($filters['start_date'])) {
            $startDate = $filters['startDate'] ?? $filters['start_date'];
            $query->where('created_at', '>=', $startDate);
        }

        if (! empty($filters['endDate']) || ! empty($filters['end_date'])) {
            $endDate = $filters['endDate'] ?? $filters['end_date'];
            $query->where('created_at', '<=', $endDate);
        }

        $perPage = min(max((int) ($filters['perPage'] ?? $filters['per_page'] ?? 15), 1), 100);

        return $query->latest('id')->paginate($perPage);
    }
}
