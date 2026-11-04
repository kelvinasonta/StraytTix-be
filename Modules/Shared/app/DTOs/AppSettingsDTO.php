<?php

declare(strict_types=1);

namespace Modules\Shared\app\DTOs;

class AppSettingsDTO
{
    public function __construct(
        public readonly string $sitename,
        public readonly string $site_tagline,
        public readonly ?string $favicon = null,
        public readonly ?string $logo = null,
        public readonly ?string $maintenance_message = null,
        public readonly ?string $support_email = null,
        public readonly ?string $timezone = 'UTC',
        public readonly ?string $currency_id = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sitename: isset($data['sitename']) ? trim((string) $data['sitename']) : (string) config('app.name', 'EventsOS'),
            site_tagline: isset($data['site_tagline']) ? trim((string) $data['site_tagline']) : '',
            favicon: ! empty($data['favicon']) ? (string) $data['favicon'] : null,
            logo: ! empty($data['logo']) ? (string) $data['logo'] : null,
            maintenance_message: ! empty($data['maintenance_message']) ? (string) $data['maintenance_message'] : null,
            support_email: ! empty($data['support_email']) ? (string) $data['support_email'] : null,
            timezone: ! empty($data['timezone']) ? (string) $data['timezone'] : 'UTC',
            currency_id: ! empty($data['currency_id']) ? (string) $data['currency_id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sitename' => $this->sitename,
            'site_tagline' => $this->site_tagline,
            'favicon' => $this->favicon,
            'logo' => $this->logo,
            'maintenance_message' => $this->maintenance_message,
            'support_email' => $this->support_email,
            'timezone' => $this->timezone,
            'currency_id' => $this->currency_id,
        ];
    }
}
