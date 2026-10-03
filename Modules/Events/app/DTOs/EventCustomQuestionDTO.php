<?php

declare(strict_types=1);

namespace Modules\Events\app\DTOs;

class EventCustomQuestionDTO
{
    public function __construct(
        public readonly string $question,
        public readonly string $type = 'Text',
        public readonly ?string $options = null,
        public readonly bool $is_required = false,
        public readonly int $sort_order = 0,
        public readonly ?string $uuid = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $options = $data['options'] ?? null;
        if (is_array($options)) {
            $options = implode(', ', $options);
        }

        return new self(
            question: $data['question'],
            type: $data['type'] ?? 'Text',
            options: $options,
            is_required: (bool) ($data['isRequired'] ?? $data['is_required'] ?? false),
            sort_order: (int) ($data['sortOrder'] ?? $data['sort_order'] ?? 0),
            uuid: $data['id'] ?? $data['uuid'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [
            'question' => $this->question,
            'type' => $this->type,
            'options' => $this->options,
            'is_required' => $this->is_required,
            'sort_order' => $this->sort_order,
        ];

        if (! empty($this->uuid)) {
            $data['uuid'] = $this->uuid;
        }

        return $data;
    }
}
