<?php

namespace Modules\Events\app\DTOs;

class EventScheduleDTO
{
    public function __construct(
        public readonly ?string $schedule_type = 'single',
        public readonly ?string $schedule_timezone = 'UTC',
        public readonly ?string $event_frequency = null,
        public readonly ?string $start_date = null,
        public readonly ?string $start_time = null,
        public readonly ?string $end_date = null,
        public readonly ?string $end_time = null,
        public readonly ?string $recurring_end_type = null,
        public readonly ?string $recurring_end_date = null,
        public readonly ?string $recurring_end_time = null,
        public readonly ?int $recurring_occurrences = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $recurringOccurrences = null;
        if (isset($data['recurringOccurrences'])) {
            $recurringOccurrences = (int) $data['recurringOccurrences'];
        } elseif (isset($data['recurring_occurrences'])) {
            $recurringOccurrences = (int) $data['recurring_occurrences'];
        }

        return new self(
            schedule_type: $data['scheduleType'] ?? $data['schedule_type'] ?? 'single',
            schedule_timezone: $data['scheduleTimezone'] ?? $data['schedule_timezone'] ?? 'UTC',
            event_frequency: $data['eventFrequency'] ?? $data['event_frequency'] ?? null,
            start_date: $data['startDate'] ?? $data['start_date'] ?? null,
            start_time: $data['startTime'] ?? $data['start_time'] ?? null,
            end_date: $data['endDate'] ?? $data['end_date'] ?? null,
            end_time: $data['endTime'] ?? $data['end_time'] ?? null,
            recurring_end_type: $data['recurringEndType'] ?? $data['recurring_end_type'] ?? $data['recurring_type'] ?? null,
            recurring_end_date: $data['recurringEndDate'] ?? $data['recurring_end_date'] ?? null,
            recurring_end_time: $data['recurringEndTime'] ?? $data['recurring_end_time'] ?? null,
            recurring_occurrences: $recurringOccurrences,
        );
    }

    public function toArray(): array
    {
        return [
            'schedule_type' => $this->schedule_type,
            'schedule_timezone' => $this->schedule_timezone,
            'event_frequency' => $this->event_frequency,
            'start_date' => $this->start_date,
            'start_time' => $this->start_time,
            'end_date' => $this->end_date,
            'end_time' => $this->end_time,
            'recurring_type' => $this->recurring_end_type,
            'recurring_end_date' => $this->recurring_end_date,
            'recurring_end_time' => $this->recurring_end_time,
            'recurring_occurrences' => $this->recurring_occurrences,
        ];
    }
}
