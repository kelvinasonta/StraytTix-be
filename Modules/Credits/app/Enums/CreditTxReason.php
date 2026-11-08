<?php

namespace Modules\Credits\app\Enums;

enum CreditTxReason: string
{
    case TOPUP = 'TOPUP';
    case EMAIL_BROADCAST = 'EMAIL_BROADCAST';
    case SMS_BROADCAST = 'SMS_BROADCAST';
    case CUSTOM_DOMAIN_SUB = 'CUSTOM_DOMAIN_SUB';
    case SCANNER_PASS = 'SCANNER_PASS';
    case FAILED_DISPATCH_REFUND = 'FAILED_DISPATCH_REFUND';
    case ADMIN_ADJUSTMENT = 'ADMIN_ADJUSTMENT';
}
