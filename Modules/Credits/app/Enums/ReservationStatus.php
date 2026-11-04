<?php

namespace Modules\Credits\app\Enums;

enum ReservationStatus: string
{
    case PENDING = 'PENDING';
    case COMMITTED = 'COMMITTED';
    case RELEASED = 'RELEASED';
    case EXPIRED = 'EXPIRED';
}
