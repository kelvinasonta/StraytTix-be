<?php

declare(strict_types=1);

namespace Modules\Shared\app\Enum;

enum SystemFeature: string
{
    // Authentication & User lifecycle
    case AUTH_REGISTRATION = 'auth-registration';
    case AUTH_PASSWORD_RESET = 'auth-password-reset';
    case AUTH_SOCIAL_LOGIN = 'auth-social-login';
    case AUTH_TWO_FACTOR = 'auth-two-factor';
    case AUTH_SESSION_MANAGEMENT = 'auth-session-management';

    // System & Network
    case API_RATE_LIMITING = 'api-rate-limiting';

    public function label(): string
    {
        return match ($this) {
            self::AUTH_REGISTRATION => 'User Registration',
            self::AUTH_PASSWORD_RESET => 'Password Reset',
            self::AUTH_SOCIAL_LOGIN => 'Social / OAuth Login',
            self::AUTH_TWO_FACTOR => 'Two-Factor Authentication (2FA)',
            self::AUTH_SESSION_MANAGEMENT => 'Session & Device Management',
            self::API_RATE_LIMITING => 'API Rate Limiting',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AUTH_REGISTRATION => 'Allow public registration for new attendees and organizers.',
            self::AUTH_PASSWORD_RESET => 'Allow users to request password reset emails and update forgotten passwords.',
            self::AUTH_SOCIAL_LOGIN => 'Allow authentication via third-party identity providers.',
            self::AUTH_TWO_FACTOR => 'Require or support two-factor authentication for extra login security.',
            self::AUTH_SESSION_MANAGEMENT => 'Allow users to view active sessions, login history, and terminate other devices.',
            self::API_RATE_LIMITING => 'Enforce global rate limiting across public and authenticated API routes.',
        };
    }

    /**
     * Category grouping for UI display.
     */
    public function category(): string
    {
        return match ($this) {
            self::AUTH_REGISTRATION,
            self::AUTH_PASSWORD_RESET,
            self::AUTH_SOCIAL_LOGIN,
            self::AUTH_TWO_FACTOR,
            self::AUTH_SESSION_MANAGEMENT => 'auth',
            self::API_RATE_LIMITING => 'system',
        };
    }

    /**
     * Default state if not explicitly configured in storage.
     */
    public function defaultActive(): bool
    {
        return match ($this) {
            self::AUTH_REGISTRATION,
            self::AUTH_PASSWORD_RESET,
            self::AUTH_SESSION_MANAGEMENT,
            self::API_RATE_LIMITING => true,
            self::AUTH_SOCIAL_LOGIN,
            self::AUTH_TWO_FACTOR => false,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
