<?php

namespace Modules\Events\app\DTOs;

class EventLineupDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $role = null,
        public readonly ?string $bio = null,
        public readonly ?string $avatarUrl = null,
        public readonly ?array $links = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $links = array_map(
            fn (array $link) => EventLineupLinkDTO::fromArray($link),
            $data['links'] ?? []
        );

        return new self(
            name: $data['name'] ?? null,
            role: $data['role'] ?? null,
            bio: $data['bio'] ?? null,
            avatarUrl: $data['avatarUrl'] ?? $data['avatar_url'] ?? null,
            links: $links,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'role' => $this->role,
            'bio' => $this->bio,
            'avatarUrl' => $this->avatarUrl,
            'links' => $this->links ? array_map(function ($link) {
                if ($link instanceof EventLineupLinkDTO) {
                    return [
                        'platform' => $link->platform,
                        'url' => $link->url,
                    ];
                }

                return (array) $link;
            }, $this->links) : null,
        ];
    }
}
