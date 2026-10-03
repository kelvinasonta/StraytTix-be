<?php

namespace Modules\Events\app\DTOs;

class EventScheduleItemDTO
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $startTime = null,
        public readonly ?string $endTime = null,
        public readonly ?string $schedule_date = null,
        public readonly ?int $sort_order = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            startTime: $data['startTime'] ?? $data['start_time'] ?? null,
            endTime: $data['endTime'] ?? $data['end_time'] ?? null,
            schedule_date: $data['schedule_date'] ?? $data['scheduleDate'] ?? $data['date'] ?? null,
            sort_order: isset($data['sort_order']) ? (int) $data['sort_order'] : (isset($data['sortOrder']) ? (int) $data['sortOrder'] : 0),
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'schedule_date' => $this->schedule_date,
            'sort_order' => $this->sort_order ?? 0,
        ];
    }
}
