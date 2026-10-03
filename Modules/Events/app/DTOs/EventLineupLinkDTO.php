<?php

namespace Modules\Events\app\DTOs;

class EventLineupLinkDTO
{
    public function __construct(
        public readonly ?string $platform = null,
        public readonly ?string $url = null,

    ) {}

    public static function fromArray(array $data): self
    {

        return new self(

            platform: $data['platform'],
            url: $data['url'],

        );
    }
}
