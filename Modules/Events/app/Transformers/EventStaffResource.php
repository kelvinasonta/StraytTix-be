<?php

namespace Modules\Events\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventStaffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userName = $this->user ? $this->user->name : null;
        $userEmail = $this->user ? $this->user->email : null;
        $userUuid = $this->user ? $this->user->uuid : (string) $this->user_id;
        $assignerName = $this->assigner ? $this->assigner->name : null;

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event_uuid' => $this->event?->uuid,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'uuid' => $this->user->uuid,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'organizer_member_id' => $this->organizer_member_id,
            'role' => $this->role,
            'permissions' => $this->permissions ?? [],
            'notes' => $this->notes,
            'assigned_by' => $this->assigned_by,
            'assigner' => $this->whenLoaded('assigner', function () {
                return [
                    'id' => $this->assigner->id,
                    'uuid' => $this->assigner->uuid,
                    'name' => $this->assigner->name,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Convenient frontend aliases
            'memberId' => $userUuid,
            'memberName' => $userName ?? 'Staff Member',
            'memberEmail' => $userEmail ?? '',
            'duty' => $this->role === 'check_in' ? 'Check-in' : ($this->role === 'usher' ? 'Usher' : ucfirst($this->role ?? 'Staff')),
            'station' => $this->extractStationFromNotes($this->notes),
            'canScanTickets' => is_array($this->permissions) && in_array('scan_tickets', $this->permissions),
            'assignedBy' => $assignerName ?? 'Organizer Admin',
            'assignedAt' => $this->created_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    private function extractStationFromNotes(?string $notes): string
    {
        if (! $notes) {
            return 'Main Entrance';
        }
        if (preg_match('/Station:\s*([^|;\n]+)/i', $notes, $matches)) {
            return trim($matches[1]);
        }

        return $notes;
    }
}
