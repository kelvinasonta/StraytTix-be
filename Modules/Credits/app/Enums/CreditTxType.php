<?php

namespace Modules\Credits\app\Enums;

enum CreditTxType: string
{
    case CREDIT = 'CREDIT';
    case DEBIT = 'DEBIT';
    case HOLD = 'HOLD';
    case RELEASE = 'RELEASE';
}
