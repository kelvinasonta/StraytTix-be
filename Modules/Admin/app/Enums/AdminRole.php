<?php

declare(strict_types=1);

namespace Modules\Admin\app\Enums;

enum AdminRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case PLATFORM_ADMIN = 'platform_admin';
    case FINANCE_ADMIN = 'finance_admin';
    case KYC_REVIEWER = 'kyc_reviewer';
    case TRUST_SAFETY = 'trust_safety';
    case SUPPORT_AGENT = 'support_agent';
    case READ_ONLY_ANALYST = 'read_only_analyst';

    /**
     * Get default permissions for this role.
     *
     * @return array<int, string>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SUPER_ADMIN => ['*'],
            self::PLATFORM_ADMIN => [
                'dashboard.view',
                'users.view',
                'users.suspend',
                'organizers.view',
                'organizers.verify',
                'events.moderate',
                'tickets.view',
                'orders.view',
                'refunds.review',
                'payouts.view',
                'reports.export',
                'audit_logs.view',
                'system_settings.manage',
            ],
            self::FINANCE_ADMIN => [
                'dashboard.view',
                'orders.view',
                'refunds.review',
                'refunds.approve',
                'payouts.view',
                'payouts.approve',
                'reports.export',
                'audit_logs.view',
            ],
            self::KYC_REVIEWER => [
                'dashboard.view',
                'organizers.view',
                'organizers.verify',
                'audit_logs.view',
            ],
            self::TRUST_SAFETY => [
                'dashboard.view',
                'users.view',
                'users.suspend',
                'events.moderate',
                'audit_logs.view',
            ],
            self::SUPPORT_AGENT => [
                'dashboard.view',
                'users.view',
                'organizers.view',
                'events.moderate',
                'tickets.view',
                'orders.view',
            ],
            self::READ_ONLY_ANALYST => [
                'dashboard.view',
                'reports.export',
                'audit_logs.view',
            ],
        };
    }
}
