<?php

declare(strict_types=1);

namespace Modules\Organizer\app\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\app\Services\AuditService;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerVerificationStatus;
use Modules\Organizer\app\Events\OrganizerVerificationReviewedEvent;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerVerification;

class OrganizerVerificationService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get the latest verification record for an organizer.
     */
    public function getVerification(Organizer $organizer): ?OrganizerVerification
    {
        return OrganizerVerification::with(['requester', 'reviewer', 'organizer'])
            ->where('organizer_id', $organizer->id)
            ->latest()
            ->first();
    }

    /**
     * Submit a new verification request.
     *
     * @param  array<string, mixed>  $data
     */
    public function submitRequest(Organizer $organizer, User $requester, array $data): OrganizerVerification
    {
        if ($organizer->isVerified()) {
            throw ValidationException::withMessages([
                'verification' => ['This organization is already verified.'],
            ]);
        }

        $existingPending = OrganizerVerification::where('organizer_id', $organizer->id)
            ->where('status', OrganizerVerificationStatus::PENDING)
            ->exists();

        if ($existingPending) {
            throw ValidationException::withMessages([
                'verification' => ['A verification request is already pending review for this organization.'],
            ]);
        }

        return DB::transaction(function () use ($organizer, $requester, $data) {
            $verification = OrganizerVerification::create([
                'organizer_id' => $organizer->id,
                'requested_by' => $requester->id,
                'status' => OrganizerVerificationStatus::PENDING,
                'business_name' => $data['business_name'],
                'business_type' => $data['business_type'],
                'registration_number' => $data['registration_number'] ?? null,
                'tax_id' => $data['tax_id'] ?? null,
                'website_url' => $data['website_url'] ?? null,
                'official_email' => $data['official_email'] ?? null,
                'official_phone' => $data['official_phone'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'country' => $data['country'] ?? 'Nigeria',
                'documents' => $data['documents'] ?? [],
            ]);

            $organizer->update([
                'verification_status' => OrganizerVerificationStatus::PENDING,
            ]);

            $this->auditService->log(
                action: 'organizer.verification_requested',
                description: "Verification requested for organization '{$organizer->name}'",
                organizer: $organizer,
                user: $requester,
                metadata: [
                    'verification_id' => $verification->id,
                    'verification_uuid' => $verification->uuid,
                    'business_name' => $verification->business_name,
                ]
            );

            return $verification->fresh(['requester', 'reviewer', 'organizer']);
        });
    }

    /**
     * Approve a verification request.
     */
    public function approve(OrganizerVerification $verification, User $reviewer): OrganizerVerification
    {
        return DB::transaction(function () use ($verification, $reviewer) {
            $verification->update([
                'status' => OrganizerVerificationStatus::VERIFIED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $organizer = $verification->organizer;
            $organizer->update([
                'verification_status' => OrganizerVerificationStatus::VERIFIED,
                'verified_at' => now(),
            ]);

            $this->auditService->log(
                action: 'organizer.verification_approved',
                description: "Verification approved for organization '{$organizer->name}'",
                organizer: $organizer,
                user: $reviewer,
                metadata: [
                    'verification_id' => $verification->id,
                    'verification_uuid' => $verification->uuid,
                ]
            );

            event(new OrganizerVerificationReviewedEvent($verification, $reviewer, 'verified'));

            return $verification->fresh(['requester', 'reviewer', 'organizer']);
        });
    }

    /**
     * Reject a verification request with reason.
     */
    public function reject(OrganizerVerification $verification, User $reviewer, string $reason): OrganizerVerification
    {
        return DB::transaction(function () use ($verification, $reviewer, $reason) {
            $verification->update([
                'status' => OrganizerVerificationStatus::REJECTED,
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $organizer = $verification->organizer;
            $organizer->update([
                'verification_status' => OrganizerVerificationStatus::REJECTED,
            ]);

            $this->auditService->log(
                action: 'organizer.verification_rejected',
                description: "Verification rejected for organization '{$organizer->name}': {$reason}",
                organizer: $organizer,
                user: $reviewer,
                metadata: [
                    'verification_id' => $verification->id,
                    'verification_uuid' => $verification->uuid,
                    'reason' => $reason,
                ]
            );

            event(new OrganizerVerificationReviewedEvent($verification, $reviewer, 'rejected', $reason));

            return $verification->fresh(['requester', 'reviewer', 'organizer']);
        });
    }

    /**
     * List all verification requests (for admin review).
     *
     * @param  array<string, mixed>  $filters
     */
    public function listVerifications(array $filters = []): LengthAwarePaginator
    {
        $query = OrganizerVerification::with(['organizer', 'requester', 'reviewer'])->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Find a verification request by UUID.
     */
    public function findByUuid(string $uuid): OrganizerVerification
    {
        return OrganizerVerification::with(['organizer', 'requester', 'reviewer'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
